<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Category;

final class CategoryController
{
    public function index(): string
    {
        Auth::requireRole('admin');
        return render('categories/index', ['categories'=>Category::all()], 'Categories');
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
        Category::create($this->fields($_POST));
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
        header('Location: /categories'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin');
        Category::delete($id);
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
