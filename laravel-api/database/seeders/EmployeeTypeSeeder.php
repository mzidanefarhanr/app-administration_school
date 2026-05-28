<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        EmployeeType::create([
            'name' => 'Tenaga Kependidikan'
        ]);
        EmployeeType::create([
            'name' => 'Pendidik'
        ]);
    }
}
