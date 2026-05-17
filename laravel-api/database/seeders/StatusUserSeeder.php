<?php

namespace Database\Seeders;

use App\Models\StatusUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatusUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        StatusUser::create([
            'name'     => 'Aktif'
        ]);
        StatusUser::create([
            'name'     => 'Tidak Aktif'
        ]);
    }
}
