<?php

namespace Database\Seeders;

use App\Enums\EstadoCurso;
use App\Enums\EstadoInscripcion;
use App\Enums\Lenguaje;
use App\Enums\Rol;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Cuentas de prueba para una instalación limpia: 2 instructores y 20 estudiantes inscritos en un curso. No corre con
 * `db:seed`; se pide aparte: `php artisan db:seed --class=CuentasPruebaSeeder`. Se puede repetir sin duplicar nada ni
 * cambiar contraseñas. Los correos usan el dominio reservado .test, así que ningún aviso le llega a nadie. Los
 * instructores activan la verificación en dos pasos al entrar a la web, como cualquier otro: la consola la exige.
 * No usa fábricas: en producción no está instalado Faker.
 */
class CuentasPruebaSeeder extends Seeder
{
    private const DOMINIO = 'clt4bp.test';

    private const INSTRUCTORES = ['Instructor Prueba 1', 'Instructor Prueba 2'];

    private const ESTUDIANTES = [
        'Ana López', 'Bruno Castillo', 'Carla Méndez', 'Diego Ramírez', 'Elena Torres',
        'Fernando Ruiz', 'Gabriela Soto', 'Héctor Navarro', 'Isabel Cruz', 'Jorge Herrera',
        'Karla Jiménez', 'Luis Morales', 'María Ortega', 'Nicolás Vega', 'Olivia Rojas',
        'Pablo Guzmán', 'Quetzalli Flores', 'Ricardo Silva', 'Sofía Medina', 'Tomás Aguilar',
    ];

    public function run(): void
    {
        $password = $this->password();

        $instructores = [];
        foreach (self::INSTRUCTORES as $i => $nombre) {
            $instructores[] = $this->cuenta(sprintf('instructor%02d', $i + 1), $nombre, $password, Rol::Instructor);
        }

        $curso = Course::firstOrCreate(
            ['owner_id' => $instructores[0]->id, 'titulo' => 'Curso de prueba'],
            ['lenguaje' => Lenguaje::C, 'nivel_educativo' => 'universidad', 'estado' => EstadoCurso::Activo],
        );
        $curso->instructores()->syncWithoutDetaching([$instructores[0]->id => ['rol' => 'responsable']]);

        foreach (self::ESTUDIANTES as $i => $nombre) {
            $estudiante = $this->cuenta(sprintf('prueba%02d', $i + 1), $nombre, $password, Rol::Estudiante);
            Enrollment::firstOrCreate(
                ['course_id' => $curso->id, 'user_id' => $estudiante->id],
                ['estado' => EstadoInscripcion::Cursando, 'inscrito_at' => now()],
            );
        }
    }

    private function cuenta(string $usuario, string $nombre, string $password, Rol $rol): User
    {
        $cuenta = User::firstOrCreate(
            ['email' => $usuario.'@'.self::DOMINIO],
            ['name' => $nombre, 'password' => $password],
        );
        $cuenta->forceFill(['email_verified_at' => now(), 'invitacion_aceptada_at' => now()])->save();
        $cuenta->syncRoles([$rol->value]);

        return $cuenta;
    }

    /** En local basta la clave de siempre; en cualquier otro entorno hay que definir una larga. */
    private function password(): string
    {
        $password = (string) config('clt4bp.prueba_password');
        if ($password === '' && app()->isLocal()) {
            return 'password';
        }
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('Define CLT4BP_PRUEBA_PASSWORD (mínimo 12 caracteres) antes de crear las cuentas de prueba.');
        }

        return $password;
    }
}
