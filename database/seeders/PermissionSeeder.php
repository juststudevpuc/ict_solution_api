<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // Original Modules
            ['module_name' => 'Dashboard', 'key' => 'dashboard', 'admin' => true, 'staff' => true, 'user' => true],
            ['module_name' => 'Product Management', 'key' => 'productPage', 'admin' => true, 'staff' => true, 'user' => false],
            ['module_name' => 'Inventory Management', 'key' => 'inventory', 'admin' => true, 'staff' => true, 'user' => false],
            ['module_name' => 'Order Page', 'key' => 'orderPage', 'admin' => true, 'staff' => true, 'user' => true],
            ['module_name' => 'Request Order', 'key' => 'request_order', 'admin' => true, 'staff' => false, 'user' => true],
            ['module_name' => 'Staff Management', 'key' => 'staff_management', 'admin' => true, 'staff' => false, 'user' => false],

            // Extended Modules (Keys matched to App.jsx)
            ['module_name' => 'Transaction History', 'key' => 'transaction_history', 'admin' => true, 'staff' => false, 'user' => false],
            ['module_name' => 'Reports', 'key' => 'report', 'admin' => true, 'staff' => false, 'user' => false],
            ['module_name' => 'Settings', 'key' => 'super_admin_only_setting', 'admin' => true, 'staff' => false, 'user' => false],
            ['module_name' => 'Career', 'key' => 'career', 'admin' => true, 'staff' => false, 'user' => false],
            ['module_name' => 'Job Applications', 'key' => 'job-applications', 'admin' => true, 'staff' => false, 'user' => false],
            ['module_name' => 'Portfolio', 'key' => 'portfolio', 'admin' => true, 'staff' => false, 'user' => false],
        ];

        foreach ($modules as $module) {
            Permission::firstOrCreate(
                ['key' => $module['key']],
                $module
            );
        }
    }
}
