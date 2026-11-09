<?php

namespace Tests\Feature\Etapa7;

use App\Enums\EstadoInscripcion;
use App\Enums\Rol;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\CuentasPruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class CuentasPruebaSeederTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'una-clave-larga-de-prueba';

    public function test_crea_instructores_y_estudiantes_inscritos_en_un_curso(): void
    {
        config(['clt4bp.prueba_password' => self::CLAVE]);

        $this->seed(CuentasPruebaSeeder::class);

        $this->assertSame(2, User::role(Rol::Instructor->value)->count());
        $this->assertSame(20, User::role(Rol::Estudiante->value)->count());

        $curso = Course::where('titulo', 'Curso de prueba')->sole();
        $responsable = User::where('email', 'instructor01@clt4bp.test')->sole();
        $this->assertSame($responsable->id, $curso->owner_id);
        $this->assertTrue($curso->instructores()->whereKey($responsable->id)->exists());
        $this->assertSame(20, Enrollment::where('course_id', $curso->id)->where('estado', EstadoInscripcion::Cursando)->count());

        $estudiante = User::where('email', 'prueba01@clt4bp.test')->sole();
        $this->assertSame('Ana López', $estudiante->name);
        $this->assertTrue(Hash::check(self::CLAVE, $estudiante->password));
        $this->assertNotNull($estudiante->email_verified_at);
    }

    public function test_los_instructores_no_quedan_con_la_verificacion_en_dos_pasos_resuelta(): void
    {
        config(['clt4bp.prueba_password' => self::CLAVE]);

        $this->seed(CuentasPruebaSeeder::class);

        $this->assertNull(User::where('email', 'instructor01@clt4bp.test')->sole()->two_factor_confirmed_at);
    }

    public function test_repetirlo_no_duplica_ni_cambia_contrasenas(): void
    {
        config(['clt4bp.prueba_password' => self::CLAVE]);
        $this->seed(CuentasPruebaSeeder::class);

        config(['clt4bp.prueba_password' => 'otra-clave-larga-de-prueba']);
        $this->seed(CuentasPruebaSeeder::class);

        $this->assertSame(22, User::count());
        $this->assertSame(1, Course::count());
        $this->assertSame(20, Enrollment::count());
        $this->assertTrue(Hash::check(self::CLAVE, User::where('email', 'prueba01@clt4bp.test')->sole()->password));
    }

    public function test_fuera_de_local_exige_una_clave_larga(): void
    {
        foreach ([null, 'password'] as $clave) {
            config(['clt4bp.prueba_password' => $clave]);

            try {
                $this->seed(CuentasPruebaSeeder::class);
                $this->fail('Debió negarse a crear las cuentas.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('CLT4BP_PRUEBA_PASSWORD', $e->getMessage());
            }
        }

        $this->assertSame(0, User::count());
    }

    public function test_en_local_sin_clave_usa_la_de_siempre(): void
    {
        config(['clt4bp.prueba_password' => null]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->seed(CuentasPruebaSeeder::class);

        $this->assertTrue(Hash::check('password', User::where('email', 'prueba20@clt4bp.test')->sole()->password));
    }
}
