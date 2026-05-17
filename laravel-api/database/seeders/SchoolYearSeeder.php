<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        SchoolYear::create([
            'name'     => '2019/2020'
        ]);
        SchoolYear::create([
            'name'     => '2020/2021'
        ]);
        SchoolYear::create([
            'name'     => '2021/2022'
        ]);
        SchoolYear::create([
            'name'     => '2022/2023'
        ]);
        SchoolYear::create([
            'name'     => '2023/2024'
        ]);
        SchoolYear::create([
            'name'     => '2024/2025'
        ]);
        SchoolYear::create([
            'name'     => '2025/2026'
        ]);
        SchoolYear::create([
            'name'     => '2026/2027'
        ]);
        SchoolYear::create([
            'name'     => '2027/2028'
        ]);
        SchoolYear::create([
            'name'     => '2028/2029'
        ]);
        SchoolYear::create([
            'name'     => '2029/2030'
        ]);
        SchoolYear::create([
            'name'     => '2030/2031'
        ]);
    }
}
