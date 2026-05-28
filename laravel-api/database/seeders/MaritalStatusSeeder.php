<?php

namespace Database\Seeders;

use App\Models\MaritalStatus;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaritalStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        MaritalStatus::create([
            'name' => 'Menikah'
        ]);
        MaritalStatus::create([
            'name' => 'Belum Menikah'
        ]);
        MaritalStatus::create([
            'name' => 'Janda'
        ]);
        MaritalStatus::create([
            'name' => 'Duda'
        ]);
        MaritalStatus::create([
            'name' => 'Cerai Meninggal'
        ]);
    }
}
