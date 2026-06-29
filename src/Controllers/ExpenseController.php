<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Expense;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Category;
use App\Models\Activity;

final class ExpenseController
{
    private function filters(): array
    {
        return [
            'date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? '',
            'project_id'=>$_GET['project_id'] ?? '', 'category_id'=>$_GET['category_id'] ?? '',
            'account_id'=>$_GET['account_id'] ?? '',
        ];
    }

    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = $this->filters();
        return render('expenses/index', [
            'rows'=>Expense::all($f), 'total'=>Expense::totalTzs($f), 'f'=>$f,
            'projects'=>Project::all(), 'categories'=>Category::all('expense'),
            'accounts'=>\App\Models\Account::all(true),
        ], 'Expenses');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('expenses/form', $this->formData(null, null), 'New expense');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('expenses/form', $this->formData($_POST, $error), 'New expense'); }
        $d = $this->fields($_POST);
        $d['created_by'] = Auth::user()['id'] ?? null;
        $id = Expense::create($d);
        $this->maybeStoreReceipt($id);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'expense', $id, 'Created expense (' . number_format($d['amount_tzs'], 2) . ' TZS)');
        header('Location: /expenses'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $row = Expense::find($id);
        if (!$row) { http_response_code(404); return 'Not found'; }
        return render('expenses/form', $this->formData($row, null), 'Edit expense');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Expense::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('expenses/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit expense'); }
        Expense::update($id, $this->fields($_POST));
        $this->maybeStoreReceipt($id);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'expense', $id, 'Updated expense');
        header('Location: /expenses'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Expense::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'expense', $id, 'Deleted expense');
        header('Location: /expenses'); exit;
    }

    private function formData(?array $row, ?string $error): array
    {
        return [
            'r'=>$row, 'error'=>$error,
            'contacts'=>Contact::all('vendor'),
            'projects'=>Project::all(),
            'categories'=>Category::all('expense'),
            'accounts'=>\App\Models\Account::all(true),
        ];
    }

    private function fields(array $in): array
    {
        return [
            'date'=>$in['date'] ?? date('Y-m-d'),
            'contact_id'=>$in['contact_id'] ?? null,
            'project_id'=>$in['project_id'] ?? null,
            'category_id'=>$in['category_id'] ?? null,
            'description'=>trim($in['description'] ?? ''),
            'amount_tzs'=>(float)($in['amount_tzs'] ?? 0),
            'reference'=>trim($in['reference'] ?? ''),
            'notes'=>trim($in['notes'] ?? ''),
            'account_id'=>$in['account_id'] ?? null,
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (empty($in['account_id'])) return 'An account is required.';
        if (!is_numeric($in['amount_tzs'] ?? null) || (float)$in['amount_tzs'] <= 0) return 'Amount must be greater than zero.';
        return null;
    }

    private function maybeStoreReceipt(int $id): void
    {
        if (empty($_FILES['receipt']['name'])) { return; }
        if (\App\ReceiptStorage::validate($_FILES['receipt']) !== null) { return; }
        $name = \App\ReceiptStorage::store($_FILES['receipt'], 'expense', $id);
        Expense::setReceipt($id, $name);
    }

    public function receipt(int $id): never
    {
        Auth::requireRole('admin','editor','viewer');
        $row = Expense::find($id);
        if (!$row || empty($row['receipt_path'])) { http_response_code(404); echo 'Not found'; exit; }
        $path = \App\ReceiptStorage::path($row['receipt_path']);
        if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }
        $ext = \App\ReceiptStorage::extension($path);
        header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'image/' . $ext));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path); exit;
    }
}
