<?php

namespace App\Enums;

/**
 * User role enumeration for the ANCLA platform.
 *
 * Defines all roles available and their associated permissions.
 */
enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case OPERATOR = 'operator';
    case VIEWER = 'viewer';

    /**
     * Get role label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::ADMIN => 'Administrator',
            self::OPERATOR => 'Operator',
            self::VIEWER => 'Viewer',
        };
    }

    /**
     * Get role description.
     */
    public function description(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Full platform access, manages all tenants',
            self::ADMIN => 'Full tenant access, manages users and settings',
            self::OPERATOR => 'Can create and manage documents and signatures',
            self::VIEWER => 'Read-only access, can sign assigned documents',
        };
    }

    /**
     * Get permissions associated with this role.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => Permission::cases(), // All permissions

            self::ADMIN => [
                Permission::VIEW_USERS,
                Permission::CREATE_USERS,
                Permission::EDIT_USERS,
                Permission::DELETE_USERS,
                Permission::MANAGE_USERS,
                Permission::VIEW_DOCUMENTS,
                Permission::CREATE_DOCUMENTS,
                Permission::EDIT_DOCUMENTS,
                Permission::DELETE_DOCUMENTS,
                Permission::VIEW_SIGNATURES,
                Permission::CREATE_SIGNATURES,
                Permission::SIGN_DOCUMENTS,
                Permission::MANAGE_SETTINGS,
                Permission::MANAGE_BRANDING,
                Permission::VIEW_AUDIT,
                Permission::EXPORT_AUDIT,
            ],

            self::OPERATOR => [
                Permission::VIEW_USERS,
                Permission::VIEW_DOCUMENTS,
                Permission::CREATE_DOCUMENTS,
                Permission::EDIT_DOCUMENTS,
                Permission::VIEW_SIGNATURES,
                Permission::CREATE_SIGNATURES,
                Permission::SIGN_DOCUMENTS,
                Permission::VIEW_AUDIT,
            ],

            self::VIEWER => [
                Permission::VIEW_DOCUMENTS,
                Permission::VIEW_SIGNATURES,
                Permission::SIGN_DOCUMENTS,
            ],
        };
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(Permission $permission): bool
    {
        if ($this === self::SUPER_ADMIN) {
            return true;
        }

        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Get roles that can be assigned by this role.
     *
     * @return array<UserRole>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => [self::SUPER_ADMIN, self::ADMIN, self::OPERATOR, self::VIEWER],
            self::ADMIN => [self::ADMIN, self::OPERATOR, self::VIEWER],
            self::OPERATOR => [self::VIEWER],
            self::VIEWER => [],
        };
    }

    /**
     * Check if this role can assign another role.
     */
    public function canAssignRole(UserRole $role): bool
    {
        return in_array($role, $this->assignableRoles(), true);
    }

    /**
     * Get all roles as array for select options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    /**
     * Get roles available for tenant users (excludes super_admin).
     *
     * @return array<UserRole>
     */
    public static function tenantRoles(): array
    {
        return [
            self::ADMIN,
            self::OPERATOR,
            self::VIEWER,
        ];
    }
}
