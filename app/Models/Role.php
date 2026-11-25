<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['kode_role', 'nama_role', 'deskripsi', 'active'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
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

    /** Cache nama permission per instance (bukan static) agar tidak bocor antar request. */
    private ?array $permissionNameCache = null;

    /** Sekali query per role instance; dipakai middleware CheckPermission. */
    private function permissionNames(): array
    {
        if ($this->permissionNameCache !== null) {
            return $this->permissionNameCache;
        }

        return $this->permissionNameCache = $this->relationLoaded('permissions')
            ? $this->permissions->pluck('name')->all()
            : $this->permissions()->pluck('name')->all();
    }
}
