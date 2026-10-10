@extends('layouts.app')

@section('title', 'Edit User - CKPN Calculator')

@section('page-title', 'Edit User')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-2xl font-bold mb-6">Edit User</h2>

        <form action="{{ route('user.update', $user) }}" method="POST" class="space-y-6">
            @csrf @method('PUT')

            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password (Optional) -->
            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Role -->
            <div>
                <label for="role_id" class="block text-sm font-semibold text-gray-700 mb-2">Role *</label>
                <select id="role_id" name="role_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Role --</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->nama_role }}</option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Office -->
            <div>
                <label for="office_id" class="block text-sm font-semibold text-gray-700 mb-2">Office</label>
                <select id="office_id" name="office_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Office --</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->id }}" @selected(old('office_id', $user->office_id) == $office->id)>{{ $office->office_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Active -->
            <div class="flex items-center">
                <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $user->active)) class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                <label for="active" class="ml-2 text-sm font-medium text-gray-700">Active</label>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Update User
                </button>
                <a href="{{ route('user.index') }}" class="flex-1 bg-gray-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors text-center">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
