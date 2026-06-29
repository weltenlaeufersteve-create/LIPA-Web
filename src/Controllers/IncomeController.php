<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Income;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Category;
use App\Models\Activity;

final class IncomeController
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
        return render('income/index', [
            'rows'=>Income::all($f), 'total'=>Income::totalTzs($f), 'f'=>$f,
            'projects'=>Project::all(), 'categories'=>Category::all('income'),
            'accounts'=>\App\Models\Account::all(true),
        ], 'Income');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('income/form', $this->formData(null, null), 'New income');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('income/form', $this->formData($_POST, $error), 'New income'); }
        $d = $this->fields($_POST);
        $d['created_by'] = Auth::user()['id'] ?? null;
        $id = Income::create($d);
        $this->maybeStoreReceipt($id);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'income', $id, 'Created income (' . number_format($d['amount_tzs'], 2) . ' TZS)');
        header('Location: /income'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $row = Income::find($id);
        if (!$row) { http_response_code(404); return 'Not found'; }
        return render('income/form', $this->formData($row, null), 'Edit income');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Income::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('income/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit income'); }
        Income::update($id, $this->fields($_POST));
        $this->maybeStoreReceipt($id);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'income', $id, 'Updated income');
        header('Location: /income'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Income::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'income', $id, 'Deleted income');
        header('Location: /income'); exit;
    }

    private function formData(?array $row, ?string $error): array
    {
        return [
            'r'=>$row, 'error'=>$error,
            'contacts'=>Contact::all('donor'),
            'projects'=>Project::all(),
            'categories'=>Category::all('income'),
            'accounts'=>\App\Models\Account::all(true),
        ];
    }

    private function fields(array $in): array
    {
        $currency = ($in['currency'] ?? 'TZS') === 'USD' ? 'USD' : 'TZS';
        $amount = (float)($in['amount_original'] ?? 0);
        $rate = $currency === 'USD' ? (float)($in['exchange_rate'] ?? 1) : 1.0;
        return [
            'date'=>$in['date'] ?? date('Y-m-d'),
            'contact_id'=>$in['contact_id'] ?? null,
            'project_id'=>$in['project_id'] ?? null,
            'category_id'=>$in['category_id'] ?? null,
            'description'=>trim($in['description'] ?? ''),
            'currency'=>$currency,
            'amount_original'=>$amount,
            'exchange_rate'=>$rate,
            'amount_tzs'=>Income::tzsValue($amount, $rate),
            'reference'=>trim($in['reference'] ?? ''),
            'notes'=>trim($in['notes'] ?? ''),
            'account_id'=>$in['account_id'] ?? null,
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (empty($in['account_id'])) return 'An account is required.';
        if (!is_numeric($in['amount_original'] ?? null) || (float)$in['amount_original'] <= 0) return 'Amount must be greater than zero.';
        if (($in['currency'] ?? 'TZS') === 'USD' && (!is_numeric($in['exchange_rate'] ?? null) || (float)$in['exchange_rate'] <= 0)) {
            return 'Exchange rate must be greater than zero for USD.';
        }
        return null;
    }

    private function maybeStoreReceipt(int $id): void
    {
        if (empty($_FILES['receipt']['name'])) { return; }
        if (\App\ReceiptStorage::validate($_FILES['receipt']) !== null) { return; }
        $name = \App\ReceiptStorage::store($_FILES['receipt'], 'income', $id);
        Income::setReceipt($id, $name);
    }

    public function receipt(int $id): never
    {
        Auth::requireRole('admin','editor','viewer');
        $row = Income::find($id);
        if (!$row || empty($row['receipt_path'])) { http_response_code(404); echo 'Not found'; exit; }
        $path = \App\ReceiptStorage::path($row['receipt_path']);
        if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }
        $ext = \App\ReceiptStorage::extension($path);
        header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'image/' . $ext));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path); exit;
    }
}
