<?php
namespace App;

final class ImageStorage
{
    public const DIR = __DIR__ . '/../storage/activity_photos';
    private const ALLOWED = ['jpg','jpeg','png'];
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_EDGE = 1600;

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function validate(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Photo upload failed.';
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'Photo must be 10 MB or smaller.';
        }
        if (!in_array(self::extension($file['name'] ?? ''), self::ALLOWED, true)) {
            return 'Photo must be a JPG or PNG.';
        }
        return null;
    }

    public static function store(array $file, string $prefix): string
    {
        if (!is_dir(self::DIR)) { mkdir(self::DIR, 0775, true); }
        $data = file_get_contents($file['tmp_name']);
        $src = imagecreatefromstring($data);
        if ($src === false) { throw new \RuntimeException('Unsupported image'); }

        $srcW = imagesx($src); $srcH = imagesy($src);
        $scale = min(1.0, self::MAX_EDGE / max($srcW, $srcH));
        $dstW = max(1, (int)round($srcW * $scale));
        $dstH = max(1, (int)round($srcH * $scale));

        $dst = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white); // flatten any transparency
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $basename = sprintf('%s_%s.jpg', $prefix, bin2hex(random_bytes(6)));
        imagejpeg($dst, self::DIR . '/' . $basename, 80);
        imagedestroy($src);
        imagedestroy($dst);
        return $basename;
    }

    public static function path(string $basename): string
    {
        return self::DIR . '/' . basename($basename);
    }
}
