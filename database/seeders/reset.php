<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class reset extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hapus semua data di tabel websites
        DB::table('websites')->delete();

        // Reset auto-increment ID (khusus SQLite)
        DB::statement('DELETE FROM sqlite_sequence WHERE name="websites"');

        // Masukkan data baru
        DB::table('websites')->insert([
            [
                'name' => 'Scanner',
                'url' => 'http://localhost:8000/',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin Panel',
                'url' => 'https://www.csirt.purwakartakab.go.id/',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
