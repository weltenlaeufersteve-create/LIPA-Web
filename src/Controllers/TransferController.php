<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Transfer;
use App\Models\Account;
use App\Models\Activity;

final class TransferController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = ['date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? ''];
        return render('transfers/index', ['rows'=>Transfer::all($f), 'f'=>$f], 'Transfers');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('transfers/form', ['t'=>null, 'error'=>null, 'accounts'=>Account::all(true)], 'New transfer');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('transfers/form', ['t'=>$_POST, 'error'=>$error, 'accounts'=>Account::all(true)], 'New transfer'); }
        $d = $this->fields($_POST);
        $d['created_by'] = Auth::user()['id'] ?? null;
        $id = Transfer::create($d);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'transfer', $id, 'Transfer ' . number_format($d['amount_tzs'], 2) . ' TZS');
        header('Location: /transfers'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $t = Transfer::find($id);
        if (!$t) { http_response_code(404); return 'Not found'; }
        return render('transfers/form', ['t'=>$t, 'error'=>null, 'accounts'=>Account::all(true)], 'Edit transfer');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Transfer::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('transfers/form', ['t'=>array_merge($_POST,['id'=>$id]), 'error'=>$error, 'accounts'=>Account::all(true)], 'Edit transfer'); }
        Transfer::update($id, $this->fields($_POST));
        Activity::log(Auth::user()['id'] ?? null, 'update', 'transfer', $id, 'Updated transfer');
        header('Location: /transfers'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Transfer::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'transfer', $id, 'Deleted transfer');
        header('Location: /transfers'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'date'=>$in['date'] ?? date('Y-m-d'),
            'from_account_id'=>$in['from_account_id'] ?? null,
            'to_account_id'=>$in['to_account_id'] ?? null,
            'amount_tzs'=>(float)($in['amount_tzs'] ?? 0),
            'description'=>trim($in['description'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (empty($in['from_account_id']) || empty($in['to_account_id'])) return 'Both accounts are required.';
        if ((int)$in['from_account_id'] === (int)$in['to_account_id']) return 'From and To must be different accounts.';
        if (!is_numeric($in['amount_tzs'] ?? null) || (float)$in['amount_tzs'] <= 0) return 'Amount must be greater than zero.';
        return null;
    }
}
