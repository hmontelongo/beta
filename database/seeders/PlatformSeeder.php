<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'inmuebles24',
                'slug' => 'inmuebles24',
                'base_url' => 'https://www.inmuebles24.com',
            ],
            [
                'name' => 'vivanuncios',
                'slug' => 'vivanuncios',
                'base_url' => 'https://www.vivanuncios.com.mx',
            ],
            [
                'name' => 'mercadolibre',
                'slug' => 'mercadolibre',
                'base_url' => 'https://inmuebles.mercadolibre.com.mx',
            ],
            [
                'name' => 'easybroker',
                'slug' => 'easybroker',
                'base_url' => 'https://www.easybroker.com',
            ],
            [
                'name' => 'propiedades',
                'slug' => 'propiedades',
                'base_url' => 'https://propiedades.com',
            ],
            [
                'name' => 'lamudi',
                'slug' => 'lamudi',
                'base_url' => 'https://www.lamudi.com.mx',
            ],
        ];

        foreach ($platforms as $platform) {
            Platform::updateOrCreate(
                ['slug' => $platform['slug']],
                $platform
            );
        }
    }
}
