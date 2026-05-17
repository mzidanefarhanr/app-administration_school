<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReligionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Religion::create([
            'name' => 'Islam'
        ]);
        Religion::create([
            'name' => 'Kristen'
        ]);
        Religion::create([
            'name' => 'Katholik'
        ]);
        Religion::create([
            'name' => 'Hindu'
        ]);
        Religion::create([
            'name' => 'Budha'
        ]);
        Religion::create([
            'name' => 'Khonghucu'
        ]);
        Religion::create([
            'name' => 'Lainnya'
        ]);
    }
}
