<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Setting;
use App\Models\Activity;

final class SettingController
{
    private const KEYS = ['org_name','org_address','org_email','tax_id','ngo_number','base_currency'];

    public function index(): string
    {
        Auth::requireRole('admin');
        return render('settings/index', ['s'=>Setting::all(), 'saved'=>isset($_GET['saved'])], 'Settings');
    }

    public function save(): string
    {
        Auth::requireRole('admin');
        foreach (self::KEYS as $k) {
            Setting::set($k, trim($_POST[$k] ?? ''));
        }
        if (isset($_POST['accent_color'])) {
            Setting::set('accent_color', \App\hex_color($_POST['accent_color']));
        }
        if (!empty($_FILES['logo']['name']) && ($_FILES['logo']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','svg'], true)) {
                $dir = dirname(__DIR__, 2) . '/public/uploads';
                if (!is_dir($dir)) { mkdir($dir, 0775, true); }
                $name = 'logo.' . $ext;
                $tmp = $_FILES['logo']['tmp_name'];
                if (is_uploaded_file($tmp)) { move_uploaded_file($tmp, "$dir/$name"); } else { rename($tmp, "$dir/$name"); }
                Setting::set('logo', $name);
            }
        }
        Activity::log(Auth::user()['id'] ?? null, 'update', 'settings', null, 'Updated settings');
        header('Location: /settings?saved=1'); exit;
    }
}
