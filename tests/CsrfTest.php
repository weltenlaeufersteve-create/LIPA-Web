<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Csrf;
use App\ForbiddenException;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function test_token_is_non_empty_and_stable_within_session(): void
    {
        $t = Csrf::token();
        $this->assertNotEmpty($t);
        $this->assertSame($t, Csrf::token());
    }

    public function test_field_contains_hidden_input_with_token(): void
    {
        $t = Csrf::token();
        $html = Csrf::field();
        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString($t, $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }

    public function test_check_passes_with_valid_token(): void
    {
        $_POST['_csrf'] = Csrf::token();
        Csrf::check();
        $this->assertTrue(true); // reached only if no exception
    }

    public function test_check_throws_with_wrong_token(): void
    {
        Csrf::token();
        $_POST['_csrf'] = 'not-the-token';
        $this->expectException(ForbiddenException::class);
        Csrf::check();
    }

    public function test_check_throws_when_no_session_token(): void
    {
        $_POST['_csrf'] = 'anything';
        $this->expectException(ForbiddenException::class);
        Csrf::check();
    }

    public function test_inject_adds_token_to_post_forms_only(): void
    {
        Csrf::token();
        $html = '<form method="post" action="/x"><button>Go</button></form>'
              . '<form method="get" action="/search"><input name="q"></form>';
        $out = Csrf::inject($html);
        // one token added (to the POST form), none to the GET form
        $this->assertSame(1, substr_count($out, 'name="_csrf"'));
        // token sits immediately after the opening <form ...> tag
        $this->assertStringContainsString('action="/x"><input type="hidden" name="_csrf"', $out);
    }
}
