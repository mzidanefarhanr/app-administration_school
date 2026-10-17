<?php

namespace Database\Seeders;

use App\Models\TypeUser;
use Illuminate\Database\Seeder;

class TypeUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        TypeUser::firstOrCreate([
            'name' => 'Admin',
        ]);
        TypeUser::firstOrCreate([
            'name' => 'User',
        ]);
    }
}
