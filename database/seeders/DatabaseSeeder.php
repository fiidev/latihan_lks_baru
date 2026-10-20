<?php

namespace Database\Seeders;

use App\Models\ShippingService;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 4 User Utama
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Staff Operasional',
            'email' => 'staff@example.com',
            'password' => 'password',
            'role' => 'staff',
        ]);

        User::create([
            'name' => 'Kurir Lapangan',
            'email' => 'courier@example.com',
            'password' => 'password',
            'role' => 'courier',
        ]);

        User::create([
            'name' => 'Customer Alya',
            'email' => 'customer@example.com',
            'password' => 'password',
            'role' => 'customer',
        ]);

        // Layanan Ekspedisi Default
        ShippingService::create([
            'service_name' => 'Regular',
            'description' => 'Standard delivery service (2-3 business days)',
            'is_active' => true,
        ]);

        ShippingService::create([
            'service_name' => 'Express',
            'description' => 'Next-day fast delivery service (1 business day)',
            'is_active' => true,
        ]);
    }
}
