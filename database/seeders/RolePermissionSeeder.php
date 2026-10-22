<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Financing
            ['name' => 'financing.view', 'kode_permission' => 'FIN_VIEW', 'nama_permission' => 'View Financing', 'display_name' => 'View Financing', 'module' => 'financing'],
            ['name' => 'financing.create', 'kode_permission' => 'FIN_CREATE', 'nama_permission' => 'Create Financing', 'display_name' => 'Create Financing', 'module' => 'financing'],
            ['name' => 'financing.edit', 'kode_permission' => 'FIN_EDIT', 'nama_permission' => 'Edit Financing', 'display_name' => 'Edit Financing', 'module' => 'financing'],
            ['name' => 'financing.delete', 'kode_permission' => 'FIN_DELETE', 'nama_permission' => 'Delete Financing', 'display_name' => 'Delete Financing', 'module' => 'financing'],
            ['name' => 'financing.bulk-delete', 'kode_permission' => 'FIN_BULK_DELETE', 'nama_permission' => 'Bulk Delete Financing', 'display_name' => 'Bulk Delete Financing', 'module' => 'financing'],
            ['name' => 'financing.import', 'kode_permission' => 'FIN_IMPORT', 'nama_permission' => 'Import Financing', 'display_name' => 'Import Financing', 'module' => 'financing'],

            // Collateral
            ['name' => 'collateral.view', 'kode_permission' => 'COL_VIEW', 'nama_permission' => 'View Collateral', 'display_name' => 'View Collateral', 'module' => 'collateral'],
            ['name' => 'collateral.create', 'kode_permission' => 'COL_CREATE', 'nama_permission' => 'Create Collateral', 'display_name' => 'Create Collateral', 'module' => 'collateral'],
            ['name' => 'collateral.edit', 'kode_permission' => 'COL_EDIT', 'nama_permission' => 'Edit Collateral', 'display_name' => 'Edit Collateral', 'module' => 'collateral'],
            ['name' => 'collateral.delete', 'kode_permission' => 'COL_DELETE', 'nama_permission' => 'Delete Collateral', 'display_name' => 'Delete Collateral', 'module' => 'collateral'],
            ['name' => 'collateral.bulk-delete', 'kode_permission' => 'COL_BULK_DELETE', 'nama_permission' => 'Bulk Delete Collateral', 'display_name' => 'Bulk Delete Collateral', 'module' => 'collateral'],

            // Office
            ['name' => 'office.view', 'kode_permission' => 'OFF_VIEW', 'nama_permission' => 'View Office', 'display_name' => 'View Office', 'module' => 'office'],
            ['name' => 'office.create', 'kode_permission' => 'OFF_CREATE', 'nama_permission' => 'Create Office', 'display_name' => 'Create Office', 'module' => 'office'],
            ['name' => 'office.edit', 'kode_permission' => 'OFF_EDIT', 'nama_permission' => 'Edit Office', 'display_name' => 'Edit Office', 'module' => 'office'],
            ['name' => 'office.delete', 'kode_permission' => 'OFF_DELETE', 'nama_permission' => 'Delete Office', 'display_name' => 'Delete Office', 'module' => 'office'],
            ['name' => 'office.bulk-delete', 'kode_permission' => 'OFF_BULK_DELETE', 'nama_permission' => 'Bulk Delete Office', 'display_name' => 'Bulk Delete Office', 'module' => 'office'],

            // Calculation
            ['name' => 'calculation.view', 'kode_permission' => 'CALC_VIEW', 'nama_permission' => 'View Calculation', 'display_name' => 'View Calculation', 'module' => 'calculation'],
            ['name' => 'calculation.run', 'kode_permission' => 'CALC_RUN', 'nama_permission' => 'Run Calculation', 'display_name' => 'Run Calculation', 'module' => 'calculation'],

            // Reporting
            ['name' => 'reporting.view', 'kode_permission' => 'REP_VIEW', 'nama_permission' => 'View Reports', 'display_name' => 'View Reports', 'module' => 'reporting'],
            ['name' => 'reporting.export', 'kode_permission' => 'REP_EXPORT', 'nama_permission' => 'Export Reports', 'display_name' => 'Export Reports', 'module' => 'reporting'],

            // User Management
            ['name' => 'user.view', 'kode_permission' => 'USR_VIEW', 'nama_permission' => 'View Users', 'display_name' => 'View Users', 'module' => 'user'],
            ['name' => 'user.create', 'kode_permission' => 'USR_CREATE', 'nama_permission' => 'Create User', 'display_name' => 'Create User', 'module' => 'user'],
            ['name' => 'user.edit', 'kode_permission' => 'USR_EDIT', 'nama_permission' => 'Edit User', 'display_name' => 'Edit User', 'module' => 'user'],
            ['name' => 'user.delete', 'kode_permission' => 'USR_DELETE', 'nama_permission' => 'Delete User', 'display_name' => 'Delete User', 'module' => 'user'],

            // Role Management
            ['name' => 'role.view', 'kode_permission' => 'ROLE_VIEW', 'nama_permission' => 'View Roles', 'display_name' => 'View Roles', 'module' => 'role'],
            ['name' => 'role.create', 'kode_permission' => 'ROLE_CREATE', 'nama_permission' => 'Create Role', 'display_name' => 'Create Role', 'module' => 'role'],
            ['name' => 'role.edit', 'kode_permission' => 'ROLE_EDIT', 'nama_permission' => 'Edit Role', 'display_name' => 'Edit Role', 'module' => 'role'],
            ['name' => 'role.delete', 'kode_permission' => 'ROLE_DELETE', 'nama_permission' => 'Delete Role', 'display_name' => 'Delete Role', 'module' => 'role'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['name' => $perm['name']], $perm);
        }

        // Admin role
        $adminRole = Role::updateOrCreate(
            ['kode_role' => 'ADMIN'],
            ['nama_role' => 'Administrator', 'deskripsi' => 'Full system access', 'active' => true]
        );
        $adminRole->permissions()->sync(Permission::pluck('id'));

        // Manager role
        $managerPerms = Permission::whereIn('name', [
            'financing.view', 'financing.create', 'financing.edit', 'financing.delete', 'financing.bulk-delete', 'financing.import',
            'collateral.view', 'collateral.create', 'collateral.edit', 'collateral.delete', 'collateral.bulk-delete',
            'office.view',
            'calculation.view', 'calculation.run',
            'reporting.view', 'reporting.export',
            'user.view',
        ])->pluck('id');

        $managerRole = Role::updateOrCreate(
            ['kode_role' => 'MANAGER'],
            ['nama_role' => 'Manager', 'deskripsi' => 'Manage data and calculations', 'active' => true]
        );
        $managerRole->permissions()->sync($managerPerms);

        // Analyst role
        $analystPerms = Permission::whereIn('name', [
            'financing.view',
            'collateral.view',
            'office.view',
            'calculation.view', 'calculation.run',
            'reporting.view', 'reporting.export',
        ])->pluck('id');

        $analystRole = Role::updateOrCreate(
            ['kode_role' => 'ANALYST'],
            ['nama_role' => 'Analyst', 'deskripsi' => 'View and analyze data', 'active' => true]
        );
        $analystRole->permissions()->sync($analystPerms);

        // Operational roles: MAKER, CHECKER, APPROVER, AUDITOR
        // Semua harus dapat melihat hasil perhitungan & meng-export untuk backtesting/audit.
        $operationalPerms = Permission::whereIn('name', [
            'financing.view',
            'collateral.view',
            'office.view',
            'calculation.view',
            'reporting.view', 'reporting.export',
        ])->pluck('id');

        $operationalRoles = [
            ['kode_role' => 'MAKER', 'nama_role' => 'Maker', 'deskripsi' => 'Input and prepare data'],
            ['kode_role' => 'CHECKER', 'nama_role' => 'Checker', 'deskripsi' => 'Verify data and calculations'],
            ['kode_role' => 'APPROVER', 'nama_role' => 'Approver', 'deskripsi' => 'Approve final CKPN'],
            ['kode_role' => 'AUDITOR', 'nama_role' => 'Auditor', 'deskripsi' => 'Read-only audit and backtesting'],
        ];

        foreach ($operationalRoles as $roleDef) {
            $role = Role::updateOrCreate(
                ['kode_role' => $roleDef['kode_role']],
                ['nama_role' => $roleDef['nama_role'], 'deskripsi' => $roleDef['deskripsi'], 'active' => true]
            );
            $role->permissions()->syncWithoutDetaching($operationalPerms);
        }

        // Viewer role
        $viewerPerms = Permission::whereIn('name', [
            'financing.view', 'collateral.view', 'office.view',
            'reporting.view',
        ])->pluck('id');

        $viewerRole = Role::updateOrCreate(
            ['kode_role' => 'VIEWER'],
            ['nama_role' => 'Viewer', 'deskripsi' => 'Read-only access', 'active' => true]
        );
        $viewerRole->permissions()->sync($viewerPerms);
    }
}
