<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('email', 'admin@hartonomotor.com')->doesntExist()) {
            User::create([
                'name' => 'System Administrator',
                'email' => 'admin@hartonomotor.com',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]);
        }
    }
}
