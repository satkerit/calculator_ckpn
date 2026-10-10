<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('role', 'office')->paginate(15);
        return view('user.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::where('active', true)->get();
        $offices = Office::where('active', true)->get();
        return view('user.create', compact('roles', 'offices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'office_id' => 'nullable|exists:offices,id',
            'active' => 'boolean',
        ]);

        $validated['active'] = $request->boolean('active', true);

        User::create($validated);

        return redirect()->route('user.index')->with('success', 'User created successfully');
    }

    public function show(User $user): View
    {
        $user->load('role', 'office');
        return view('user.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $user->load('role', 'office');
        $roles = Role::where('active', true)->get();
        $offices = Office::where('active', true)->get();
        return view('user.edit', compact('user', 'roles', 'offices'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'office_id' => 'nullable|exists:offices,id',
            'active' => 'boolean',
        ]);

        $validated['active'] = $request->boolean('active', $user->active);

        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }
        unset($validated['password']);

        $user->update($validated);

        return redirect()->route('user.index')->with('success', 'User updated successfully');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('user.index')->with('error', 'Cannot delete own account');
        }

        $user->delete();
        return redirect()->route('user.index')->with('success', 'User deleted successfully');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:users,id'
        ]);

        // Prevent deleting current authenticated user
        $ids = array_diff($validated['ids'], [auth()->id()]);

        if (empty($ids)) {
            return redirect()->route('user.index')->with('error', 'Cannot delete your own account');
        }

        $count = User::whereIn('id', $ids)->delete();

        return redirect()->route('user.index')->with('success', "{$count} user berhasil dihapus.");
    }
}
