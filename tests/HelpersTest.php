<?php
namespace Tests;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function test_hex_color_accepts_valid_and_rejects_invalid(): void
    {
        $this->assertSame('#C0175B', \App\hex_color('#C0175B'));
        $this->assertSame('#0e7c7b', \App\hex_color('#0e7c7b'));
        $this->assertSame('#C0175B', \App\hex_color('red'));            // fallback
        $this->assertSame('#C0175B', \App\hex_color('#FFF'));           // 3-digit rejected
        $this->assertSame('#C0175B', \App\hex_color('#12345g'));        // non-hex rejected
        $this->assertSame('#000000', \App\hex_color(null, '#000000'));  // custom fallback
        $this->assertSame('#C0175B', \App\hex_color('#c0175b"><script>')); // injection rejected
    }

    public function test_ini_bytes_parses_size_strings(): void
    {
        $this->assertSame(8 * 1024 * 1024, \App\ini_bytes('8M'));
        $this->assertSame(64 * 1024 * 1024, \App\ini_bytes('64M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, \App\ini_bytes('2G'));
        $this->assertSame(512 * 1024, \App\ini_bytes('512K'));
        $this->assertSame(8388608, \App\ini_bytes('8388608')); // plain bytes
        $this->assertSame(0, \App\ini_bytes(''));
    }

    public function test_role_label_maps_enum_to_display(): void
    {
        $this->assertSame('Admin', \App\role_label('admin'));
        $this->assertSame('Coordinator', \App\role_label('editor'));
        $this->assertSame('Accountant', \App\role_label('viewer'));
        $this->assertSame('Something', \App\role_label('something'));
    }
}
