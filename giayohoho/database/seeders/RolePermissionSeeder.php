<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->roles() as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                [
                    'description' => $role['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ($this->permissions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                    'created_at' => $now,
                ]
            );
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'code');

        $this->syncRolePermissions((int) $roleIds['ADMIN'], $permissionIds->values()->all());

        $staffCodes = [
            'PRODUCT_VIEW',
            'ORDER_VIEW',
            'ORDER_UPDATE_STATUS',
            'ACCOUNT_VIEW',
            'CATEGORY_VIEW',
            'DASHBOARD_VIEW',
            'PAYMENT_VIEW',
            'COUPON_VIEW',
            'SHIPPING_VIEW',
            'SHIPPING_CREATE',
            'SHIPPING_SYNC',
            'SHIPPING_PRINT',
        ];

        $this->syncRolePermissions(
            (int) $roleIds['STAFF'],
            collect($staffCodes)->map(fn (string $code) => $permissionIds[$code] ?? null)->filter()->values()->all()
        );
    }

    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => (int) $permissionId,
            ]);
        }
    }

    private function roles(): array
    {
        return [
            ['name' => 'ADMIN', 'description' => 'System administrator'],
            ['name' => 'CUSTOMER', 'description' => 'Customer'],
            ['name' => 'STAFF', 'description' => 'Operations staff'],
        ];
    }

    private function permissions(): array
    {
        return [
            ['code' => 'PRODUCT_VIEW', 'description' => 'View products in admin', 'module' => 'PRODUCT'],
            ['code' => 'PRODUCT_CREATE', 'description' => 'Create products', 'module' => 'PRODUCT'],
            ['code' => 'PRODUCT_UPDATE', 'description' => 'Update products', 'module' => 'PRODUCT'],
            ['code' => 'PRODUCT_DELETE', 'description' => 'Soft delete products', 'module' => 'PRODUCT'],
            ['code' => 'PRODUCT_RESTORE', 'description' => 'Restore deleted products', 'module' => 'PRODUCT'],
            ['code' => 'ORDER_VIEW', 'description' => 'View orders', 'module' => 'ORDER'],
            ['code' => 'ORDER_UPDATE_STATUS', 'description' => 'Update order status', 'module' => 'ORDER'],
            ['code' => 'ACCOUNT_VIEW', 'description' => 'View accounts', 'module' => 'ACCOUNT'],
            ['code' => 'ACCOUNT_LOCK', 'description' => 'Lock or unlock accounts', 'module' => 'ACCOUNT'],
            ['code' => 'CATEGORY_VIEW', 'description' => 'View categories in admin', 'module' => 'CATEGORY'],
            ['code' => 'CATEGORY_CREATE', 'description' => 'Create categories', 'module' => 'CATEGORY'],
            ['code' => 'CATEGORY_UPDATE', 'description' => 'Update categories', 'module' => 'CATEGORY'],
            ['code' => 'CATEGORY_DELETE', 'description' => 'Delete categories', 'module' => 'CATEGORY'],
            ['code' => 'DASHBOARD_VIEW', 'description' => 'View admin dashboard', 'module' => 'DASHBOARD'],
            ['code' => 'PAYMENT_VIEW', 'description' => 'View payments', 'module' => 'PAYMENT'],
            ['code' => 'COUPON_VIEW', 'description' => 'View coupons', 'module' => 'COUPON'],
            ['code' => 'COUPON_CREATE', 'description' => 'Create coupons', 'module' => 'COUPON'],
            ['code' => 'COUPON_UPDATE', 'description' => 'Update coupons', 'module' => 'COUPON'],
            ['code' => 'COUPON_DELETE', 'description' => 'Delete coupons', 'module' => 'COUPON'],
            ['code' => 'SHIPPING_VIEW', 'description' => 'View shipments', 'module' => 'SHIPPING'],
            ['code' => 'SHIPPING_CREATE', 'description' => 'Create shipments', 'module' => 'SHIPPING'],
            ['code' => 'SHIPPING_CANCEL', 'description' => 'Cancel shipments', 'module' => 'SHIPPING'],
            ['code' => 'SHIPPING_SYNC', 'description' => 'Sync shipments', 'module' => 'SHIPPING'],
            ['code' => 'SHIPPING_PRINT', 'description' => 'Print shipment labels', 'module' => 'SHIPPING'],
        ];
    }
}
