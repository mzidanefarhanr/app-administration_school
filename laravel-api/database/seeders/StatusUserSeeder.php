<?php

namespace Database\Seeders;

use App\Models\StatusUser;
use Illuminate\Database\Seeder;

class StatusUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        StatusUser::firstOrCreate([
            'name' => 'Aktif',
        ]);
        StatusUser::firstOrCreate([
            'name' => 'Tidak Aktif',
        ]);
    }
}
