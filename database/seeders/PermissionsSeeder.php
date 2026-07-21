<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            /* Access Management */
            ['name' => 'Permission Index', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission List', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Delete', 'group_name' => 'Access Management Permissions'],

            ['name' => 'Role Index', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role List', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Delete', 'group_name' => 'Access Management Permissions'],

            /* User Management */
            ['name' => 'User Index', 'group_name' => 'User Management Permissions'],
            ['name' => 'User List', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Create', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Update', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Delete', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Toggle Status', 'group_name' => 'User Management Permissions'],

            /* Country Management */
            ['name' => 'Country Index', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country List', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Create', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Update', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Delete', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Toggle Status', 'group_name' => 'Country Management Permissions'],

            /* Province Management */
            ['name' => 'Province Index', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province List', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Create', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Update', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Delete', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Toggle Status', 'group_name' => 'Province Management Permissions'],

            /* District Management */
            ['name' => 'District Index', 'group_name' => 'District Management Permissions'],
            ['name' => 'District List', 'group_name' => 'District Management Permissions'],
            ['name' => 'District Create', 'group_name' => 'District Management Permissions'],
            ['name' => 'District Update', 'group_name' => 'District Management Permissions'],
            ['name' => 'District Delete', 'group_name' => 'District Management Permissions'],
            ['name' => 'District Toggle Status', 'group_name' => 'District Management Permissions'],

            /* Branch Management */
            ['name' => 'Branch Index', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch List', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Create', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Update', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Delete', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Toggle Status', 'group_name' => 'Branch Management Permissions'],

            /* Department Management */
            ['name' => 'Department Index', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department List', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Create', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Update', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Delete', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Toggle Status', 'group_name' => 'Department Management Permissions'],

            /* Designation Management */
            ['name' => 'Designation Index', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation List', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Create', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Update', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Delete', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Toggle Status', 'group_name' => 'Designation Management Permissions'],

            /* Group Management */
            ['name' => 'Group Index', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group List', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Create', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Update', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Delete', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Toggle Status', 'group_name' => 'Group Management Permissions'],

            /* Item Type Management */
            ['name' => 'ItemType Index', 'group_name' => 'Item Type Management Permissions'],
            ['name' => 'ItemType List', 'group_name' => 'Item Type Management Permissions'],
            ['name' => 'ItemType Create', 'group_name' => 'Item Type Management Permissions'],
            ['name' => 'ItemType Update', 'group_name' => 'Item Type Management Permissions'],
            ['name' => 'ItemType Delete', 'group_name' => 'Item Type Management Permissions'],
            ['name' => 'ItemType Toggle Status', 'group_name' => 'Item Type Management Permissions'],

            /* Bank Management */
            ['name' => 'Bank Index', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank List', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Create', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Update', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Delete', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Toggle Status', 'group_name' => 'Bank Management Permissions'],

            /* Vehicle Management */
            ['name' => 'Vehicle Index', 'group_name' => 'Vehicle Management Permissions'],
            ['name' => 'Vehicle List', 'group_name' => 'Vehicle Management Permissions'],
            ['name' => 'Vehicle Create', 'group_name' => 'Vehicle Management Permissions'],
            ['name' => 'Vehicle Update', 'group_name' => 'Vehicle Management Permissions'],
            ['name' => 'Vehicle Delete', 'group_name' => 'Vehicle Management Permissions'],
            ['name' => 'Vehicle Toggle Status', 'group_name' => 'Vehicle Management Permissions'],

            /* Supplier Management */
            ['name' => 'Supplier Index', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier List', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Create', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Update', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Delete', 'group_name' => 'Supplier Management Permissions'],
            ['name' => 'Supplier Toggle Status', 'group_name' => 'Supplier Management Permissions'],

            /* Item Variety Management */
            ['name' => 'ItemVariety Index', 'group_name' => 'Item Variety Management Permissions'],
            ['name' => 'ItemVariety List', 'group_name' => 'Item Variety Management Permissions'],
            ['name' => 'ItemVariety Create', 'group_name' => 'Item Variety Management Permissions'],
            ['name' => 'ItemVariety Update', 'group_name' => 'Item Variety Management Permissions'],
            ['name' => 'ItemVariety Delete', 'group_name' => 'Item Variety Management Permissions'],
            ['name' => 'ItemVariety Toggle Status', 'group_name' => 'Item Variety Management Permissions'],

            /* Vehicle Log Management */
            ['name' => 'VehicleLog Index', 'group_name' => 'Vehicle Log Management Permissions'],
            ['name' => 'VehicleLog Create', 'group_name' => 'Vehicle Log Management Permissions'],
            ['name' => 'VehicleLog View', 'group_name' => 'Vehicle Log Management Permissions'],
            ['name' => 'VehicleLog Update', 'group_name' => 'Vehicle Log Management Permissions'],
            ['name' => 'VehicleLog Delete', 'group_name' => 'Vehicle Log Management Permissions'],
            ['name' => 'VehicleLog Exit', 'group_name' => 'Vehicle Log Management Permissions'],

            /* Warehouse Management */
            ['name' => 'Warehouse Index', 'group_name' => 'Warehouse Management Permissions'],
            ['name' => 'Warehouse List', 'group_name' => 'Warehouse Management Permissions'],
            ['name' => 'Warehouse Create', 'group_name' => 'Warehouse Management Permissions'],
            ['name' => 'Warehouse Update', 'group_name' => 'Warehouse Management Permissions'],
            ['name' => 'Warehouse Delete', 'group_name' => 'Warehouse Management Permissions'],
            ['name' => 'Warehouse Toggle Status', 'group_name' => 'Warehouse Management Permissions'],

            /* Stock In Batch Management */
            ['name' => 'StockInBatch Index', 'group_name' => 'Stock In Batch Management Permissions'],
            ['name' => 'StockInBatch List', 'group_name' => 'Stock In Batch Management Permissions'],
            ['name' => 'StockInBatch Create', 'group_name' => 'Stock In Batch Management Permissions'],
            ['name' => 'StockInBatch Update', 'group_name' => 'Stock In Batch Management Permissions'],
            ['name' => 'StockInBatch Delete', 'group_name' => 'Stock In Batch Management Permissions'],
            ['name' => 'StockInBatch Update Status', 'group_name' => 'Stock In Batch Management Permissions'],

            /* Stock Bag Management */
            ['name' => 'StockBag Index', 'group_name' => 'Stock Bag Management Permissions'],
            ['name' => 'StockBag List', 'group_name' => 'Stock Bag Management Permissions'],
            ['name' => 'StockBag Create', 'group_name' => 'Stock Bag Management Permissions'],
            ['name' => 'StockBag Update', 'group_name' => 'Stock Bag Management Permissions'],
            ['name' => 'StockBag Delete', 'group_name' => 'Stock Bag Management Permissions'],
            ['name' => 'StockBag Update Status', 'group_name' => 'Stock Bag Management Permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name'],
                'group_name' => $permission['group_name'],
                'guard_name' => 'api',
            ]);
        }

        $roles = [
            'Super Admin',
            'System Admin',
            'Branch Admin',
            'Branch Staff',
            'Warehouse Admin',
            'Warehouse Staff',
            'Weighbridge Operator',
            'Gate Security',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['guard_name' => 'api', 'name' => $roleName]);
        }

        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $allPermissions = Permission::all();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions($allPermissions);
        }
    }
}
