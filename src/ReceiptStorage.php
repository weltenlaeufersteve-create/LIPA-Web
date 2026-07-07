<?php
namespace App;

final class ReceiptStorage
{
    public const DIR = __DIR__ . '/../storage/receipts';
    private const ALLOWED = ['pdf','jpg','jpeg','png'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function validate(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Receipt upload failed.';
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'Receipt must be 10 MB or smaller.';
        }
        if (!in_array(self::extension($file['name'] ?? ''), self::ALLOWED, true)) {
            return 'Receipt must be a PDF, JPG, or PNG.';
        }
        return null;
    }

    public static function store(array $file, string $prefix, int $id): string
    {
        if (!is_dir(self::DIR)) { mkdir(self::DIR, 0775, true); }
        $ext = self::extension($file['name']);
        $basename = sprintf('%s_%d_%s.%s', $prefix, $id, bin2hex(random_bytes(6)), $ext);
        $dest = self::DIR . '/' . $basename;
        // move_uploaded_file in web context; fall back to rename for tests/CLI.
        if (is_uploaded_file($file['tmp_name'])) {
            move_uploaded_file($file['tmp_name'], $dest);
        } else {
            rename($file['tmp_name'], $dest);
        }
        return $basename;
    }

    public static function path(string $basename): string
    {
        return self::DIR . '/' . basename($basename);
    }

    /**
     * Emit a print-ready response for a stored receipt.
     * Images render a minimal page that auto-triggers the print dialog; PDFs redirect to the
     * inline view so the browser's built-in PDF viewer handles printing.
     */
    public static function printResponse(string $basename, string $inlineUrl, string $title): void
    {
        if (self::extension($basename) === 'pdf') {
            header('Location: ' . $inlineUrl); // browser's PDF viewer handles printing
            return;
        }
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $u = htmlspecialchars($inlineUrl, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
           . '<title>' . $t . '</title>'
           . '<style>html,body{margin:0}img{display:block;max-width:100%;height:auto;margin:0 auto}'
           . '@page{margin:10mm}</style></head>'
           . '<body onload="window.print()"><img src="' . $u . '" alt="' . $t . '"></body></html>';
    }
}
