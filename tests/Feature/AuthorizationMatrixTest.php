<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * Matrice d'autorisation générée à partir de la table de routage : chaque route de l'API est
 * exercée par chaque profil. Une route nouvellement ajoutée est couverte automatiquement, et
 * toute route publique non répertoriée fait échouer le test (revue de sécurité obligatoire).
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    /** Routes volontairement accessibles sans session. */
    private const PUBLIC_ROUTES = [
        'POST api/v1/auth/email/resend',
        'GET api/v1/auth/email/verify/{id}/{hash}',
        'GET api/v1/auth/kyc/documents/{id}/fichier', // protégée par URL signée HMAC
        'POST api/v1/auth/forgot-password',
        'POST api/v1/auth/login',
        'POST api/v1/auth/register',
        'POST api/v1/auth/reset-password',
        'GET api/v1/badges',
        'GET api/v1/badges/user/{id}',
        'POST api/v1/contact',
        'GET api/v1/posts/explore',
        'GET api/v1/posts/search',
        'GET api/v1/posts/{id}',
        'GET api/v1/posts/{postId}/comments',
        'GET api/v1/posts/{postId}/likers',
        'GET api/v1/proposals/stream',
        'GET api/v1/reviews/technician/{id}',
        'GET api/v1/skills',
        'GET api/v1/technicians',
        'GET api/v1/technicians/{id}/profile',
        'GET api/v1/users/{userId}/follow-counts',
        'GET api/v1/users/{userId}/followers',
        'GET api/v1/users/{userId}/following',
        'GET api/v1/users/{userId}/posts',
        'POST api/v1/webhooks/notchpay',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array<int, array{route: LaravelRoute, method: string, label: string, uri: string}> */
    private function apiRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $uri = preg_replace('/\{[^}]+\}/', '00000000-0000-0000-0000-000000000000', $route->uri());
            $routes[] = ['route' => $route, 'method' => $method, 'label' => "{$method} {$route->uri()}", 'uri' => '/' . $uri];
        }

        return $routes;
    }

    private function middlewareOf(LaravelRoute $route): array
    {
        return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
    }

    private function isPublic(LaravelRoute $route): bool
    {
        return !in_array('auth:sanctum', $this->middlewareOf($route), true);
    }

    public function test_les_routes_publiques_sont_exactement_celles_attendues(): void
    {
        $publiques = collect($this->apiRoutes())
            ->filter(fn ($r) => $this->isPublic($r['route']))
            ->pluck('label')->sort()->values()->all();

        $attendues = collect(self::PUBLIC_ROUTES)->sort()->values()->all();

        $this->assertSame($attendues, $publiques, 'Route publique ajoutée ou retirée : valider son exposition.');
    }

    public function test_toute_route_protegee_renvoie_401_sans_session(): void
    {
        $verifiees = 0;
        foreach ($this->apiRoutes() as $r) {
            if ($this->isPublic($r['route'])) {
                continue;
            }
            $status = $this->json($r['method'], $r['uri'])->status();
            $this->assertSame(401, $status, "{$r['label']} devrait renvoyer 401 sans session, reçu {$status}.");
            $verifiees++;
        }

        $this->assertGreaterThan(150, $verifiees);
    }

    public function test_toute_route_administrateur_est_interdite_aux_autres_roles(): void
    {
        $profils = [
            'client' => $this->makeUser('client'),
            'technicien' => $this->makeUser('technicien'),
            'agence' => $this->makeUser('agence'),
        ];

        $verifiees = 0;
        foreach ($this->apiRoutes() as $r) {
            $adminOnly = collect($this->middlewareOf($r['route']))->contains('role:admin');
            if (!$adminOnly) {
                continue;
            }
            foreach ($profils as $nom => $user) {
                $status = $this->actingAs($user, 'sanctum')->json($r['method'], $r['uri'])->status();
                $this->assertSame(403, $status, "{$r['label']} devrait être interdite au rôle {$nom}, reçu {$status}.");
            }
            $verifiees++;
        }

        $this->assertGreaterThanOrEqual(40, $verifiees);
    }

    public function test_aucune_route_ne_provoque_d_erreur_serveur_pour_un_administrateur_sans_donnees(): void
    {
        $admin = $this->makeUser('admin');
        $client = $this->makeUser('client');
        $anomalies = [];

        foreach ($this->apiRoutes() as $r) {
            if ($this->isPublic($r['route']) || str_contains($r['uri'], 'logout') || str_contains($r['uri'], 'stream')) {
                continue;
            }
            foreach ([$admin, $client] as $user) {
                $status = $this->actingAs($user, 'sanctum')->json($r['method'], $r['uri'])->status();
                if ($status >= 500) {
                    $anomalies[] = "{$r['label']} → {$status}";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($anomalies)), 'Routes renvoyant une erreur serveur pour une entrée vide.');
    }

    public function test_les_routes_a_email_verifie_refusent_un_compte_non_verifie(): void
    {
        $nonVerifie = $this->makeUser('client', ['email_verified_at' => null]);

        $verifiees = 0;
        foreach ($this->apiRoutes() as $r) {
            $gated = collect($this->middlewareOf($r['route']))->contains('verified');
            if (!$gated) {
                continue;
            }
            $status = $this->actingAs($nonVerifie, 'sanctum')->json($r['method'], $r['uri'])->status();
            $this->assertSame(403, $status, "{$r['label']} devrait exiger un e-mail vérifié, reçu {$status}.");
            $verifiees++;
        }

        $this->assertGreaterThan(0, $verifiees);
    }

    public function test_le_flux_sse_public_exige_un_jeton(): void
    {
        $this->get('/api/v1/proposals/stream')->assertStatus(401);
        $this->get('/api/v1/proposals/stream?token=invalide')->assertStatus(401);
    }

    public function test_un_identifiant_non_numerique_de_journal_d_audit_est_un_404(): void
    {
        $this->actingAs($this->makeUser('admin'), 'sanctum')
            ->getJson('/api/v1/audit-logs/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }
}
