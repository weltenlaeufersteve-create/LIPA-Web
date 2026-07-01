<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Category;
use App\Models\Activity;

final class CategoryController
{
    public function index(): string
    {
        Auth::requireRole('admin','viewer'); // viewer may VIEW; writes stay admin-only
        $type = $_GET['type'] ?? null;
        if (!in_array($type, ['income','expense'], true)) { $type = null; }
        return render('categories/index', ['categories'=>Category::all($type), 'type'=>$type], 'Categories');
    }

    public function create(): string
    {
        Auth::requireRole('admin');
        return render('categories/form', ['cat'=>null, 'error'=>null], 'New category');
    }

    public function store(): string
    {
        Auth::requireRole('admin');
        $error = $this->validate($_POST);
        if ($error) { return render('categories/form', ['cat'=>$_POST, 'error'=>$error], 'New category'); }
        $newId = Category::create($this->fields($_POST));
        Activity::log(Auth::user()['id'] ?? null, 'create', 'category', $newId, 'Created category ' . trim($_POST['name'] ?? ''));
        header('Location: /categories'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin');
        $cat = Category::find($id);
        if (!$cat) { http_response_code(404); return 'Not found'; }
        return render('categories/form', ['cat'=>$cat, 'error'=>null], 'Edit category');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin');
        if (!Category::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('categories/form', ['cat'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit category'); }
        Category::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'category', $id, 'Updated category');
        header('Location: /categories'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin');
        Category::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'category', $id, 'Deleted category');
        header('Location: /categories'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'type'=>$in['type'] ?? 'expense', 'name'=>trim($in['name'] ?? ''),
            'sort_order'=>(int)($in['sort_order'] ?? 0),
        ];
    }

    private function validate(array $in): ?string
    {
        if (!in_array($in['type'] ?? '', ['income','expense'], true)) return 'Type is invalid.';
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        return null;
    }
}
