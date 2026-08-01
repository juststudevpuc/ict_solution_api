<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear old permissions if you run this multiple times
        Permission::truncate();

        $modules = [
            // Original Modules
            ['module_name' => 'Dashboard', 'key' => 'dashboard', 'staff' => true, 'user' => true],

            ['module_name' => 'Product Management', 'key' => 'productPage', 'staff' => true, 'user' => false],
            ['module_name' => 'Inventory Management', 'key' => 'inventory', 'staff' => true, 'user' => false],
            ['module_name' => 'Order Page', 'key' => 'orderPage', 'staff' => true, 'user' => true],
            ['module_name' => 'Request Order', 'key' => 'request_order', 'staff' => false, 'user' => true],
            ['module_name' => 'Staff Management', 'key' => 'staff_management', 'staff' => false, 'user' => false],

            // 🔥 NEW MODULES (Keys perfectly matched to App.jsx)
            ['module_name' => 'Transaction History', 'key' => 'transaction_history', 'staff' => false, 'user' => false],
            ['module_name' => 'Reports', 'key' => 'report', 'staff' => false, 'user' => false],
            ['module_name' => 'Settings', 'key' => 'super_admin_only_setting', 'staff' => false, 'user' => false],
            ['module_name' => 'career', 'key' => 'career', 'staff' => false, 'user' => false],
        ];

        foreach ($modules as $module) {
            Permission::create($module);
        }
    }
}
