<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Project;

final class ProjectController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor');
        return render('projects/index', ['projects'=>Project::all()], 'Projects');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('projects/form', ['p'=>null, 'error'=>null], 'New project');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('projects/form', ['p'=>$_POST, 'error'=>$error], 'New project'); }
        Project::create($this->fields($_POST));
        header('Location: /projects'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $p = Project::find($id);
        if (!$p) { http_response_code(404); return 'Not found'; }
        return render('projects/form', ['p'=>$p, 'error'=>null], 'Edit project');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Project::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('projects/form', ['p'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit project'); }
        Project::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        header('Location: /projects'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Project::delete($id);
        header('Location: /projects'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'name'=>trim($in['name'] ?? ''), 'code'=>trim($in['code'] ?? ''),
            'description'=>trim($in['description'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        return null;
    }
}
