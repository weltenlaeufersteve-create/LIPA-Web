<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Router;
use App\NotFoundException;

final class RouterTest extends TestCase
{
    public function test_matches_static_route(): void
    {
        $r = new Router();
        $r->add('GET', '/users', fn() => 'list');
        $this->assertSame('list', $r->dispatch('GET', '/users'));
    }

    public function test_captures_named_param(): void
    {
        $r = new Router();
        $r->add('GET', '/users/:id/edit', fn($p) => 'edit-' . $p['id']);
        $this->assertSame('edit-7', $r->dispatch('GET', '/users/7/edit'));
    }

    public function test_method_matters(): void
    {
        $r = new Router();
        $r->add('POST', '/users', fn() => 'created');
        $this->assertSame('created', $r->dispatch('POST', '/users'));
        $this->expectException(NotFoundException::class);
        $r->dispatch('GET', '/users');
    }

    public function test_unknown_route_throws(): void
    {
        $r = new Router();
        $this->expectException(NotFoundException::class);
        $r->dispatch('GET', '/nope');
    }
}
