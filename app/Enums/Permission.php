<?php

namespace App\Enums;

/**
 * Permission enumeration for role-based access control.
 *
 * Defines all permissions available in the Firmalum platform.
 * Permissions are grouped by resource type.
 */
enum Permission: string
{
    // Tenant management (super_admin only)
    case MANAGE_TENANTS = 'tenants.manage';

    // User management
    case VIEW_USERS = 'users.view';
    case CREATE_USERS = 'users.create';
    case EDIT_USERS = 'users.edit';
    case DELETE_USERS = 'users.delete';
    case MANAGE_USERS = 'users.manage';

    // Document management
    case VIEW_DOCUMENTS = 'documents.view';
    case CREATE_DOCUMENTS = 'documents.create';
    case EDIT_DOCUMENTS = 'documents.edit';
    case DELETE_DOCUMENTS = 'documents.delete';

    // Signature processes
    case VIEW_SIGNATURES = 'signatures.view';
    case CREATE_SIGNATURES = 'signatures.create';
    case SIGN_DOCUMENTS = 'signatures.sign';

    // Settings
    case MANAGE_SETTINGS = 'settings.manage';
    case MANAGE_BRANDING = 'settings.branding';

    // Audit
    case VIEW_AUDIT = 'audit.view';
    case EXPORT_AUDIT = 'audit.export';

    /**
     * Get permission label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::MANAGE_TENANTS => 'Manage Tenants',
            self::VIEW_USERS => 'View Users',
            self::CREATE_USERS => 'Create Users',
            self::EDIT_USERS => 'Edit Users',
            self::DELETE_USERS => 'Delete Users',
            self::MANAGE_USERS => 'Manage Users',
            self::VIEW_DOCUMENTS => 'View Documents',
            self::CREATE_DOCUMENTS => 'Create Documents',
            self::EDIT_DOCUMENTS => 'Edit Documents',
            self::DELETE_DOCUMENTS => 'Delete Documents',
            self::VIEW_SIGNATURES => 'View Signatures',
            self::CREATE_SIGNATURES => 'Create Signatures',
            self::SIGN_DOCUMENTS => 'Sign Documents',
            self::MANAGE_SETTINGS => 'Manage Settings',
            self::MANAGE_BRANDING => 'Manage Branding',
            self::VIEW_AUDIT => 'View Audit Trail',
            self::EXPORT_AUDIT => 'Export Audit Trail',
        };
    }

    /**
     * Get permission description.
     */
    public function description(): string
    {
        return match ($this) {
            self::MANAGE_TENANTS => 'Full access to tenant management (platform admin only)',
            self::VIEW_USERS => 'View user list and profiles',
            self::CREATE_USERS => 'Create new users in the tenant',
            self::EDIT_USERS => 'Edit user information and roles',
            self::DELETE_USERS => 'Delete users from the tenant',
            self::MANAGE_USERS => 'Full user management access',
            self::VIEW_DOCUMENTS => 'View documents and their status',
            self::CREATE_DOCUMENTS => 'Upload new documents',
            self::EDIT_DOCUMENTS => 'Edit document metadata',
            self::DELETE_DOCUMENTS => 'Delete documents',
            self::VIEW_SIGNATURES => 'View signature processes',
            self::CREATE_SIGNATURES => 'Create new signature processes',
            self::SIGN_DOCUMENTS => 'Sign documents assigned to user',
            self::MANAGE_SETTINGS => 'Manage tenant settings',
            self::MANAGE_BRANDING => 'Manage tenant branding (logo, colors)',
            self::VIEW_AUDIT => 'View audit trail logs',
            self::EXPORT_AUDIT => 'Export audit trail data',
        };
    }

    /**
     * Get all permissions as array.
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
