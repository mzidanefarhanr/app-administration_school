<?php

namespace Database\Seeders;

use App\Models\FamilyStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FamilyStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        FamilyStatus::create([
            'name' => 'Lengkap'
        ]);
        FamilyStatus::create([
            'name' => 'Yatim'
        ]);
        FamilyStatus::create([
            'name' => 'Piatu'
        ]);
        FamilyStatus::create([
            'name' => 'Yatim Piatu'
        ]);
    }
}
