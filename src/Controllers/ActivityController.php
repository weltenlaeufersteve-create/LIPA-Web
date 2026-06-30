<?php
namespace App\Controllers;

use App\Auth;
use App\ImageStorage;
use App\Models\ActivityItem;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Activity; // audit log

final class ActivityController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        return render('activities/index', ['rows'=>ActivityItem::all()], 'Activities');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('activities/form', $this->formData(null), 'New activity');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('activities/form', $this->formData(null, $error), 'New activity'); }
        $id = ActivityItem::create([
            'date'=>$_POST['date'], 'title'=>trim($_POST['title']),
            'description'=>trim($_POST['description'] ?? ''),
            'project_id'=>$_POST['project_id'] ?? null,
            'created_by'=>Auth::user()['id'] ?? null,
        ]);
        ActivityItem::setExpenses($id, $_POST['expense_ids'] ?? []);
        $this->storePhotos($id);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'activity', $id, 'Created activity ' . trim($_POST['title'] ?? ''));
        header('Location: /activities'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $a = ActivityItem::find($id);
        if (!$a) { http_response_code(404); return 'Not found'; }
        return render('activities/form', $this->formData($a), 'Edit activity');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!ActivityItem::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('activities/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit activity'); }
        ActivityItem::update($id, [
            'date'=>$_POST['date'], 'title'=>trim($_POST['title']),
            'description'=>trim($_POST['description'] ?? ''),
            'project_id'=>$_POST['project_id'] ?? null,
        ]);
        ActivityItem::setExpenses($id, $_POST['expense_ids'] ?? []);
        $this->storePhotos($id);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'activity', $id, 'Updated activity');
        header('Location: /activities'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        foreach (ActivityItem::photos($id) as $p) { @unlink(ImageStorage::path($p['filename'])); }
        ActivityItem::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'activity', $id, 'Deleted activity');
        header('Location: /activities'); exit;
    }

    public function photo(int $id, int $photoId): never
    {
        Auth::requireRole('admin','editor','viewer');
        $p = ActivityItem::findPhoto($photoId);
        if (!$p || (int)$p['activity_id'] !== $id) { http_response_code(404); echo 'Not found'; exit; }
        $path = ImageStorage::path($p['filename']);
        if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }
        header('Content-Type: image/jpeg');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path); exit;
    }

    public function deletePhoto(int $id, int $photoId): never
    {
        Auth::requireRole('admin','editor');
        $p = ActivityItem::findPhoto($photoId);
        if ($p && (int)$p['activity_id'] === $id) {
            @unlink(ImageStorage::path($p['filename']));
            ActivityItem::deletePhoto($photoId);
        }
        header('Location: /activities/' . $id . '/edit'); exit;
    }

    private function storePhotos(int $id): void
    {
        if (empty($_FILES['photos']['name'][0])) { return; }
        $slots = 5 - ActivityItem::photoCount($id);
        if ($slots <= 0) { return; }
        $files = $_FILES['photos'];
        $n = count($files['name']);
        for ($i = 0; $i < $n && $slots > 0; $i++) {
            $one = ['name'=>$files['name'][$i], 'tmp_name'=>$files['tmp_name'][$i],
                    'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
            if (empty($one['name']) || ImageStorage::validate($one) !== null) { continue; }
            $name = ImageStorage::store($one, 'act' . $id);
            ActivityItem::addPhoto($id, $name);
            $slots--;
        }
    }

    private function formData(?array $row, ?string $error = null): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        return [
            'a'=>$row, 'error'=>$error,
            'projects'=>Project::all(true),
            'photos'=> $id ? ActivityItem::photos($id) : [],
            'available'=> Expense::availableForActivity($id ?: null),
            'linked'=> $id ? array_map(fn($e)=>(int)$e['id'], ActivityItem::expenses($id)) : [],
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (trim($in['title'] ?? '') === '') return 'Title is required.';
        return null;
    }
}
