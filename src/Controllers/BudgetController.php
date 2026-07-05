<?php
namespace App\Controllers;

use App\Auth;
use App\Budget\ScenarioCalc;
use App\Models\BudgetScenario;
use App\Models\Project;
use App\Models\Activity;

final class BudgetController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $rows = [];
        foreach (BudgetScenario::all() as $s) {
            $id = (int)$s['id'];
            $products = BudgetScenario::products($id);
            $calc = ScenarioCalc::compute($s, $products, BudgetScenario::items($id), BudgetScenario::allocations($id));
            $rows[] = ['s'=>$s, 'products'=>count($products), 'calc'=>$calc];
        }
        return render('budget/index', ['rows'=>$rows], 'Budget');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('budget/form', $this->formData(null), 'New scenario');
    }

    public function show(int $id): string
    {
        Auth::requireRole('admin','editor','viewer');
        $s = BudgetScenario::find($id);
        if (!$s) { http_response_code(404); return 'Not found'; }
        return render('budget/form', $this->formData($s), $s['name']);
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('budget/form', $this->formData(null, $error), 'New scenario'); }
        $id = BudgetScenario::create($this->scenarioFields($_POST) + ['created_by'=>Auth::user()['id'] ?? null]);
        $this->saveChildren($id);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'budget', $id, 'Created scenario ' . trim($_POST['name'] ?? ''));
        header('Location: /budget/' . $id); exit;
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!BudgetScenario::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('budget/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit scenario'); }
        BudgetScenario::update($id, $this->scenarioFields($_POST));
        $this->saveChildren($id);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'budget', $id, 'Updated scenario');
        header('Location: /budget/' . $id); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        BudgetScenario::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'budget', $id, 'Deleted scenario');
        header('Location: /budget'); exit;
    }

    public function print(int $id): string
    {
        Auth::requireRole('admin','editor','viewer');
        $s = BudgetScenario::find($id);
        if (!$s) { http_response_code(404); return 'Not found'; }
        $products = [];
        foreach (BudgetScenario::products($id) as $p) {
            $p['materials'] = BudgetScenario::materials((int)$p['id']);
            $products[] = $p;
        }
        $items = BudgetScenario::items($id);
        $allocations = BudgetScenario::allocations($id);
        $calc = ScenarioCalc::compute($s, $products, $items, $allocations);
        $set = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/budget/print.php';
        return ob_get_clean();
    }

    // ---- helpers ----
    private function scenarioFields(array $in): array
    {
        return [
            'name'=>trim($in['name'] ?? ''),
            'description'=>trim($in['description'] ?? ''),
            'project_id'=>$in['project_id'] ?? null,
            'status'=>in_array($in['status'] ?? '', ['draft','active','archived'], true) ? $in['status'] : 'draft',
            'funded_amount'=>$in['funded_amount'] ?? 0, // sanitised (comma-stripped) in the model
            'include_first_batch'=>!empty($in['include_first_batch']),
        ];
    }

    private function saveChildren(int $id): void
    {
        BudgetScenario::setProducts($id, $this->productRows($_POST));
        $one = $this->rows($_POST, 'ot_', ['name'=>'name','amount'=>'amount','notes'=>'notes']);
        foreach ($one as &$r) { $r['item_type'] = 'one_time'; } unset($r);
        $fix = $this->rows($_POST, 'mf_', ['name'=>'name','amount'=>'amount','notes'=>'notes']);
        foreach ($fix as &$r) { $r['item_type'] = 'monthly_fixed'; } unset($r);
        BudgetScenario::setItems($id, array_merge($one, $fix));
        BudgetScenario::setAllocations($id, $this->rows($_POST, 'al_', ['name'=>'name','monthly_amount'=>'amount']));
    }

    /** Parse products (positional p_* arrays) + each product's materials (nested p_mat_*[i][]). */
    private function productRows(array $post): array
    {
        $names = $post['p_name'] ?? [];
        $out = [];
        foreach ($names as $i => $_) {
            if (trim((string)($post['p_name'][$i] ?? '')) === '') { continue; }
            $matNames = $post['p_mat_name'][$i] ?? [];
            $matAmts  = $post['p_mat_amount'][$i] ?? [];
            $materials = [];
            foreach ($matNames as $j => $mn) {
                if (trim((string)$mn) === '') { continue; }
                $materials[] = ['name'=>$mn, 'amount'=>$matAmts[$j] ?? 0, 'sort'=>$j];
            }
            $out[] = [
                'name'=>$post['p_name'][$i],
                'unit_name'=>$post['p_unit'][$i] ?? 'unit',
                'sale_price'=>$post['p_price'][$i] ?? 0,
                'batch_yield'=>$post['p_yield'][$i] ?? 1,
                'units_low'=>$post['p_low'][$i] ?? 0,
                'units_mid'=>$post['p_mid'][$i] ?? 0,
                'units_high'=>$post['p_high'][$i] ?? 0,
                'notes'=>$post['p_notes'][$i] ?? '',
                'materials'=>$materials,
                'sort'=>$i,
            ];
        }
        return $out;
    }

    /** Zip parallel POST arrays prefix+field[] into row dicts; skip rows with an empty 'name'. */
    private function rows(array $post, string $prefix, array $map): array
    {
        $names = $post[$prefix . 'name'] ?? [];
        $out = [];
        foreach ($names as $i => $_) {
            $row = [];
            foreach ($map as $key => $field) {
                $arr = $post[$prefix . $field] ?? [];
                $row[$key] = $arr[$i] ?? '';
            }
            if (trim((string)$row['name']) === '') { continue; }
            $out[] = $row;
        }
        return $out;
    }

    private function validate(array $in): ?string
    {
        if (trim($in['name'] ?? '') === '') { return 'A scenario name is required.'; }
        return null;
    }

    private function formData(?array $row, ?string $error = null): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $products = [];
        if ($id) {
            foreach (BudgetScenario::products($id) as $p) {
                $p['materials'] = BudgetScenario::materials((int)$p['id']);
                $products[] = $p;
            }
        }
        $items = $id ? BudgetScenario::items($id) : [];
        $allocs = $id ? BudgetScenario::allocations($id) : [];
        $scen = $row ?: ['funded_amount'=>0];
        return [
            's'=>$row, 'error'=>$error, 'projects'=>Project::all(true),
            'products'=>$products,
            'one_time'=>array_values(array_filter($items, fn($i)=>$i['item_type']==='one_time')),
            'monthly_fixed'=>array_values(array_filter($items, fn($i)=>$i['item_type']==='monthly_fixed')),
            'allocations'=>$allocs,
            'calc'=>ScenarioCalc::compute($scen, $products, $items, $allocs),
        ];
    }
}
