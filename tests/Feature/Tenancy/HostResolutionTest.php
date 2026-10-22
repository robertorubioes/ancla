<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Resolucion de tenant por host, en los tres entornos.
 *
 * La convencion de dominios (§8 del estandar) pone la aplicacion en
 * app.<producto>.test en local, app.test.<dominio> en testing y
 * app.<dominio> en produccion. En los tres, "app" es el host de la
 * plataforma y no un tenant.
 */
class HostResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'acme',
            'subdomain' => 'acme',
        ]);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function entornos(): array
    {
        return [
            'local' => ['firmalum.test', 'app.firmalum.test'],
            'testing' => ['test.firmalum.com', 'app.test.firmalum.com'],
            'produccion' => ['firmalum.com', 'app.firmalum.com'],
        ];
    }

    #[DataProvider('entornos')]
    public function test_el_host_principal_resuelve_al_tenant_del_usuario(string $baseDomain, string $appHost): void
    {
        config(['app.base_domain' => $baseDomain]);

        $this->actingAs($this->user)
            ->get("http://{$appHost}/signing-processes")
            ->assertOk();
    }

    #[DataProvider('entornos')]
    public function test_el_subdominio_de_tenant_sigue_resolviendo(string $baseDomain, string $appHost): void
    {
        config(['app.base_domain' => $baseDomain]);

        $this->actingAs($this->user)
            ->get("http://acme.{$baseDomain}/signing-processes")
            ->assertOk();
    }

    public function test_un_subdominio_desconocido_no_resuelve(): void
    {
        config(['app.base_domain' => 'firmalum.test']);

        // Sin usuario: no hay de donde sacar el tenant.
        $this->get('http://noexiste.firmalum.test/signing-processes')
            ->assertRedirect();
    }

    public function test_los_subdominios_de_plataforma_no_se_buscan_como_tenant(): void
    {
        config(['app.base_domain' => 'firmalum.test']);

        // Si 'app' se buscase como tenant, esto seria 404 en lugar de 200.
        foreach (['app', 'www', 'api', 'admin'] as $reservado) {
            $this->actingAs($this->user)
                ->get("http://{$reservado}.firmalum.test/signing-processes")
                ->assertOk();
        }
    }
}
