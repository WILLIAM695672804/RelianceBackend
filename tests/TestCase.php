<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Garde-fou : RefreshDatabase vide la base. Les tests ne s'exécutent que sur une base
     * dont le nom se termine par « _test » (voir phpunit.xml), jamais sur la base de développement.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.' . config('database.default') . '.database');
        if (!str_ends_with($database, '_test')) {
            $this->fail("Exécution refusée : la base « {$database} » n'est pas une base de test (suffixe _test requis).");
        }
    }
}
