<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

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

    /**
     * Les hôtes et mandataires de confiance sont statiques sur Request : un test exécuté en environnement
     * « production » (TrustHosts actif) les fixerait pour tous les tests suivants.
     */
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        Request::setTrustedProxies([], Request::getTrustedHeaderSet());

        parent::tearDown();
    }
}
