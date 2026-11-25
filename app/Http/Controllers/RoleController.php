<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions')->withCount(['users', 'permissions'])->get();
        return view('role.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::all();
        return view('role.form', compact('permissions'));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'kode_role' => strtoupper($validated['kode_role']),
            'nama_role' => $validated['nama_role'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'active' => $request->has('active'),
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('role.index')->with('success', "Role '{$role->nama_role}' berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        $permissions = Permission::all();
        return view('role.form', compact('role', 'permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        $role->update([
            'kode_role' => strtoupper($validated['kode_role']),
            'nama_role' => $validated['nama_role'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'active' => $request->has('active'),
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('role.index')->with('success', "Role '{$role->nama_role}' berhasil diperbarui.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->count() > 0) {
            return redirect()->route('role.index')->with('error', "Role '{$role->nama_role}' masih digunakan oleh " . $role->users()->count() . " user(s).");
        }

        $roleName = $role->nama_role;

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });

        return redirect()->route('role.index')->with('success', "Role '{$roleName}' berhasil dihapus.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:roles,id'
        ]);

        // Check if any role still has users
        $rolesWithUsers = Role::whereIn('id', $validated['ids'])->has('users')->get();

        if ($rolesWithUsers->isNotEmpty()) {
            $names = $rolesWithUsers->pluck('nama_role')->implode(', ');
            return redirect()->route('role.index')->with('error', "Role berikut masih digunakan: {$names}");
        }

        DB::transaction(function () use ($validated) {
            $roles = Role::whereIn('id', $validated['ids'])->get();
            foreach ($roles as $role) {
                $role->permissions()->detach();
            }
            Role::whereIn('id', $validated['ids'])->delete();
        });

        $count = count($validated['ids']);

        return redirect()->route('role.index')->with('success', "{$count} role berhasil dihapus.");
    }
}
