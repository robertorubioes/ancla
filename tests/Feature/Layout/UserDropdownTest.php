<?php

declare(strict_types=1);

namespace Tests\Feature\Layout;

use App\Enums\UserRole;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El rol se muestra dentro del desplegable de usuario, no en la barra: ahi
 * quitaba ancho a la navegacion.
 */
class UserDropdownTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(UserRole $role): User
    {
        return User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'role' => $role,
            'name' => 'Persona Ejemplo',
        ]);
    }

    private function nav(User $user): string
    {
        return (string) $this->actingAs($user)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('signing-processes.index'))
            ->getContent();
    }

    /**
     * Contenido del boton del desplegable, sin el panel que cuelga de el.
     */
    private function dropdownButton(string $html): string
    {
        $start = strpos($html, '<button @click="open');
        $this->assertNotFalse($start, 'No se encontro el boton del desplegable.');

        $end = strpos($html, '</button>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /**
     * @return array<string, array{UserRole, string, string}>
     */
    public static function roles(): array
    {
        return [
            'superadmin' => [UserRole::SUPER_ADMIN, 'Super Administrator', 'bg-purple-100'],
            'admin' => [UserRole::ADMIN, 'Administrator', 'bg-red-100'],
            'operador' => [UserRole::OPERATOR, 'Operator', 'bg-blue-100'],
            'lector' => [UserRole::VIEWER, 'Viewer', 'bg-gray-100'],
        ];
    }

    #[DataProvider('roles')]
    public function test_el_rol_aparece_con_su_etiqueta_y_su_color(UserRole $role, string $label, string $clase): void
    {
        // Antes solo se mostraba para superadmin y admin; en el desplegable
        // hay sitio para los cuatro.
        $html = $this->nav($this->userWith($role));

        $this->assertStringContainsString($label, $html);
        $this->assertStringContainsString($clase, $html);
    }

    public function test_el_rol_no_esta_en_el_boton_de_la_barra(): void
    {
        $boton = $this->dropdownButton($this->nav($this->userWith(UserRole::SUPER_ADMIN)));

        $this->assertStringNotContainsString('Super Administrator', $boton);
        $this->assertStringNotContainsString('Superadmin', $boton);
    }

    public function test_el_boton_sigue_mostrando_el_nombre(): void
    {
        $boton = $this->dropdownButton($this->nav($this->userWith(UserRole::ADMIN)));

        $this->assertStringContainsString('Persona Ejemplo', $boton);
    }

    public function test_las_clases_del_badge_van_literales(): void
    {
        // Tailwind solo ve nombres de clase completos: construirlos por
        // interpolacion deja el badge sin color en cuanto nadie mas use
        // esa clase.
        $layout = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

        $this->assertStringNotContainsString('bg-{{', (string) $layout);
        $this->assertStringContainsString('bg-purple-100', (string) $layout);
    }
}
