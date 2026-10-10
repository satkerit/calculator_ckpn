<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions', 'users')->get();
        return view('role.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::all();
        return view('role.form', compact('permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_role' => 'required|string|unique:roles,kode_role|max:50',
            'nama_role' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:500',
            'active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

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

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'kode_role' => 'required|string|unique:roles,kode_role,' . $role->id . '|max:50',
            'nama_role' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:500',
            'active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

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
        $role->permissions()->detach();
        $role->delete();

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

        $count = Role::whereIn('id', $validated['ids'])->delete();

        return redirect()->route('role.index')->with('success', "{$count} role berhasil dihapus.");
    }
}
