<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, AdminSeeder::class, CatalogoEfectosSeeder::class]);
        Artisan::call('banco:cargar');
    }
}
