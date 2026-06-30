<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\ImageStorage;

final class ImageStorageTest extends TestCase
{
    public function test_validate_accepts_jpg_png_rejects_others(): void
    {
        $ok = ['name'=>'p.jpg','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024];
        $this->assertNull(ImageStorage::validate($ok));
        $this->assertNull(ImageStorage::validate(['name'=>'p.PNG','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024]));
        $this->assertNotNull(ImageStorage::validate(['name'=>'p.gif','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024]));
        $this->assertNotNull(ImageStorage::validate(['name'=>'p.jpg','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>11*1024*1024]));
        $this->assertNotNull(ImageStorage::validate(['name'=>'p.jpg','tmp_name'=>'','error'=>UPLOAD_ERR_NO_FILE,'size'=>0]));
    }

    public function test_store_resizes_large_image_down(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'src') . '.jpg';
        $img = imagecreatetruecolor(3000, 2000);
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $name = ImageStorage::store(['name'=>'photo.jpg','tmp_name'=>$tmp], 'act');
        $path = ImageStorage::path($name);
        $this->assertFileExists($path);
        [$w, $h] = getimagesize($path);
        $this->assertLessThanOrEqual(1600, max($w, $h));
        $this->assertStringEndsWith('.jpg', $name);

        @unlink($tmp); @unlink($path);
    }
}
