<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Database;

abstract class DatabaseTestCase extends TestCase
{
    protected static function loadSchema(): void
    {
        $sql = file_get_contents(dirname(__DIR__) . '/db/schema.sql');
        Database::pdo()->exec($sql);
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::loadSchema();
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['activity_log','transfers','income','expenses','activity_photos','activities','accounts','categories','projects','contacts','settings','users'] as $t) {
            $pdo->exec("TRUNCATE TABLE {$t}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
