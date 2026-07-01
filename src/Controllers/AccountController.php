<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Account;
use App\Models\Activity;

final class AccountController
{
    public function index(): string
    {
        Auth::requireRole('admin','viewer'); // viewer may VIEW; writes stay admin-only
        return render('accounts/index', ['accounts'=>Account::all()], 'Accounts');
    }

    public function create(): string
    {
        Auth::requireRole('admin');
        return render('accounts/form', ['a'=>null, 'error'=>null], 'New account');
    }

    public function store(): string
    {
        Auth::requireRole('admin');
        $error = $this->validate($_POST);
        if ($error) { return render('accounts/form', ['a'=>$_POST, 'error'=>$error], 'New account'); }
        $id = Account::create($this->fields($_POST));
        Activity::log(Auth::user()['id'] ?? null, 'create', 'account', $id, 'Created account ' . trim($_POST['name'] ?? ''));
        header('Location: /accounts'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin');
        $a = Account::find($id);
        if (!$a) { http_response_code(404); return 'Not found'; }
        return render('accounts/form', ['a'=>$a, 'error'=>null], 'Edit account');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin');
        if (!Account::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('accounts/form', ['a'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit account'); }
        Account::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'account', $id, 'Updated account');
        header('Location: /accounts'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin');
        Account::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'account', $id, 'Deleted account');
        header('Location: /accounts'); exit;
    }

    private function fields(array $in): array
    {
        $type = in_array($in['type'] ?? '', ['bank','cash','other'], true) ? $in['type'] : 'bank';
        return [
            'name'=>trim($in['name'] ?? ''), 'type'=>$type,
            'opening_balance'=>(float)($in['opening_balance'] ?? 0),
            'opening_balance_date'=>trim($in['opening_balance_date'] ?? '') ?: null,
        ];
    }

    private function validate(array $in): ?string
    {
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        if (($in['opening_balance'] ?? '') !== '' && !is_numeric($in['opening_balance'])) return 'Opening balance must be a number.';
        return null;
    }
}
