<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Database;

final class DatabaseConnectionTest extends TestCase
{
    public function test_pdo_returns_working_connection(): void
    {
        $pdo = Database::pdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
        $value = $pdo->query('SELECT 1')->fetchColumn();
        $this->assertEquals(1, $value);
    }

    public function test_pdo_is_shared_instance(): void
    {
        $this->assertSame(Database::pdo(), Database::pdo());
    }
}
