<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Contact;
use App\Models\Activity;

final class ContactController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $type = $_GET['type'] ?? null;
        if (!in_array($type, ['donor','vendor'], true)) { $type = null; }
        return render('contacts/index', ['contacts'=>Contact::all($type), 'type'=>$type], 'Contacts');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('contacts/form', ['c'=>null, 'error'=>null], 'New contact');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('contacts/form', ['c'=>$_POST, 'error'=>$error], 'New contact'); }
        $newId = Contact::create($this->fields($_POST));
        Activity::log(Auth::user()['id'] ?? null, 'create', 'contact', $newId, 'Created contact ' . trim($_POST['name'] ?? ''));
        header('Location: /contacts'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $c = Contact::find($id);
        if (!$c) { http_response_code(404); return 'Not found'; }
        return render('contacts/form', ['c'=>$c, 'error'=>null], 'Edit contact');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Contact::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('contacts/form', ['c'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit contact'); }
        Contact::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'contact', $id, 'Updated contact');
        header('Location: /contacts'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Contact::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'contact', $id, 'Deleted contact');
        header('Location: /contacts'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'type'=>$in['type'] ?? 'donor', 'name'=>trim($in['name'] ?? ''),
            'email'=>trim($in['email'] ?? ''), 'phone'=>trim($in['phone'] ?? ''),
            'address'=>trim($in['address'] ?? ''), 'notes'=>trim($in['notes'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (!in_array($in['type'] ?? '', ['donor','vendor'], true)) return 'Type is invalid.';
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        if (($in['email'] ?? '') !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) return 'Email is invalid.';
        return null;
    }
}
