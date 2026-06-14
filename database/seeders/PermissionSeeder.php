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
            ['module_name' => 'Product Management', 'key' => 'productPage', 'staff' => true, 'user' => false],
            ['module_name' => 'Inventory Management', 'key' => 'inventory', 'staff' => true, 'user' => false],
            ['module_name' => 'Order Page', 'key' => 'orderPage', 'staff' => true, 'user' => true],
            ['module_name' => 'Request Order', 'key' => 'request_order', 'staff' => false, 'user' => true],
            ['module_name' => 'Staff Management', 'key' => 'staff_management', 'staff' => false, 'user' => false],
        ];

        foreach ($modules as $module) {
            Permission::create($module);
        }
    }
}
