<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['kode_role', 'nama_role', 'deskripsi', 'active'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permissionName): bool
    {
        return in_array($permissionName, $this->permissionNames(), true);
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        return (bool) array_intersect($permissionNames, $this->permissionNames());
    }

    /** Sekali query per role per request; dipakai middleware CheckPermission. */
    private function permissionNames(): array
    {
        static $cache = [];

        $key = $this->getKey();

        if (! isset($cache[$key])) {
            $cache[$key] = $this->permissions()->pluck('name')->all();
        }

        return $cache[$key];
    }
}
