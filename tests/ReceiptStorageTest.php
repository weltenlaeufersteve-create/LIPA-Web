<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\ReceiptStorage;

final class ReceiptStorageTest extends TestCase
{
    public function test_extension_lowercased(): void
    {
        $this->assertSame('pdf', ReceiptStorage::extension('Invoice.PDF'));
        $this->assertSame('jpg', ReceiptStorage::extension('a.b.JPG'));
    }

    public function test_validate_accepts_pdf(): void
    {
        $file = ['name'=>'r.pdf','type'=>'application/pdf','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024];
        $this->assertNull(ReceiptStorage::validate($file));
    }

    public function test_validate_rejects_bad_extension(): void
    {
        $file = ['name'=>'r.exe','type'=>'application/octet-stream','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024];
        $this->assertNotNull(ReceiptStorage::validate($file));
    }

    public function test_validate_rejects_too_large(): void
    {
        $file = ['name'=>'r.pdf','type'=>'application/pdf','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>11*1024*1024];
        $this->assertNotNull(ReceiptStorage::validate($file));
    }

    public function test_validate_rejects_upload_error(): void
    {
        $file = ['name'=>'r.pdf','type'=>'application/pdf','tmp_name'=>'','error'=>UPLOAD_ERR_NO_FILE,'size'=>0];
        $this->assertNotNull(ReceiptStorage::validate($file));
    }
}
