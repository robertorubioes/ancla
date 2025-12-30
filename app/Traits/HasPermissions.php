<?php

namespace App\Traits;

use App\Enums\Permission;
use App\Enums\UserRole;

/**
 * Trait for handling role-based permissions on User model.
 *
 * Provides methods to check user permissions based on their role.
 */
trait HasPermissions
{
    /**
     * Get the user's role as enum.
     */
    public function getRoleEnum(): UserRole
    {
        // Handle both casted enum and string values
        if ($this->role instanceof UserRole) {
            return $this->role;
        }

        return UserRole::from($this->role);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionEnum = $permission instanceof Permission
            ? $permission
            : Permission::from($permission);

        return $this->getRoleEnum()->hasPermission($permissionEnum);
    }

    /**
     * Alias for hasPermission - Laravel Gate compatible.
     */
    public function can($ability, $arguments = []): bool
    {
        // If it's a Permission enum value, check permission
        if (is_string($ability) && $this->isPermissionString($ability)) {
            return $this->hasPermission($ability);
        }

        // Fallback to parent can method if it exists
        if (method_exists(parent::class, 'can')) {
            return parent::can($ability, $arguments);
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param  array<Permission|string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can do any of the given abilities.
     * Compatible with Laravel's Authorizable trait.
     *
     * @param  iterable|string  $abilities
     * @param  array|mixed  $arguments
     */
    public function canAny($abilities, $arguments = []): bool
    {
        // If abilities is a string, convert to array
        if (is_string($abilities)) {
            $abilities = [$abilities];
        }

        // Check if these are permission strings
        $permissionStrings = [];
        foreach ($abilities as $ability) {
            if (is_string($ability) && $this->isPermissionString($ability)) {
                $permissionStrings[] = $ability;
            }
        }

        // If we found permission strings, use our permission check
        if (! empty($permissionStrings)) {
            return $this->hasAnyPermission($permissionStrings);
        }

        // Fallback to parent canAny if available
        if (method_exists(parent::class, 'canAny')) {
            return parent::canAny($abilities, $arguments);
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     *
     * @param  array<Permission|string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for hasAllPermissions.
     *
     * @param  array<Permission|string>  $permissions
     */
    public function canAll(array $permissions): bool
    {
        return $this->hasAllPermissions($permissions);
    }

    /**
     * Get all permissions for the user.
     *
     * @return array<Permission>
     */
    public function getPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::cases();
        }

        return $this->getRoleEnum()->permissions();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(UserRole|string $role): bool
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return $this->role === $roleValue;
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param  array<UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is super admin (no tenant, global access).
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->role === UserRole::SUPER_ADMIN->value;
    }

    /**
     * Check if user is admin of their tenant.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN->value || $this->isSuperAdmin();
    }

    /**
     * Check if user is operator.
     */
    public function isOperator(): bool
    {
        return $this->role === UserRole::OPERATOR->value;
    }

    /**
     * Check if user is viewer.
     */
    public function isViewer(): bool
    {
        return $this->role === UserRole::VIEWER->value;
    }

    /**
     * Check if user can assign a role to another user.
     */
    public function canAssignRole(UserRole|string $role): bool
    {
        $roleEnum = $role instanceof UserRole ? $role : UserRole::from($role);

        return $this->getRoleEnum()->canAssignRole($roleEnum);
    }

    /**
     * Get roles this user can assign to others.
     *
     * @return array<UserRole>
     */
    public function getAssignableRoles(): array
    {
        return $this->getRoleEnum()->assignableRoles();
    }

    /**
     * Check if string is a valid permission string.
     */
    protected function isPermissionString(string $ability): bool
    {
        try {
            Permission::from($ability);

            return true;
        } catch (\ValueError) {
            return false;
        }
    }
}
