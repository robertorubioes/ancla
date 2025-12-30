# Superadmin Guide - Tenant Management

> **Sprint 6 - E0-001**: Comprehensive guide for managing organizations (tenants) in Firmalum

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Accessing the Admin Panel](#accessing-the-admin-panel)
3. [Dashboard Overview](#dashboard-overview)
4. [Creating a New Tenant](#creating-a-new-tenant)
5. [Managing Existing Tenants](#managing-existing-tenants)
6. [Suspending & Unsuspending Tenants](#suspending--unsuspending-tenants)
7. [Plan Limits Reference](#plan-limits-reference)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

---

## Overview

The **Superadmin Panel** allows privileged users to manage multiple organizations (tenants) on the Firmalum platform. Each tenant operates in complete isolation with its own users, documents, and signing processes.

### Key Features

- ✅ Create and manage multiple organizations
- ✅ Configure plan limits (users, documents)
- ✅ Suspend/unsuspend tenants
- ✅ Auto-generate subdomains
- ✅ Create admin users automatically
- ✅ Track tenant statistics
- ✅ Full audit trail

---

## Accessing the Admin Panel

### Default Superadmin Credentials

```
URL: http://localhost:8000/admin/tenants
Email: superadmin@firmalum.com
Password: password
```

⚠️ **IMPORTANT**: Change the default password immediately after first login in production!

### Access Requirements

- User must have `role = 'superadmin'`
- Must be authenticated
- Only superadmins can access `/admin/*` routes

### Creating Additional Superadmins

Run the seeder to create the first superadmin:

```bash
php artisan db:seed SuperadminSeeder
```

To create additional superadmins, update a user's role manually:

```sql
UPDATE users SET role = 'superadmin' WHERE email = 'admin@firmalum.com';
```

---

## Dashboard Overview

### Statistics Cards

The dashboard displays 4 key metrics:

1. **Total Tenants**: All organizations in the system
2. **Active**: Tenants with active subscriptions
3. **Trial**: Tenants in trial period
4. **Suspended**: Tenants that have been suspended

**💡 Tip**: Click on Active, Trial, or Suspended cards to filter the table instantly.

### Filters & Search

- **Search**: Filter by organization name, slug, or subdomain
- **Status Filter**: All, Trial, Active, Suspended, Cancelled
- **Plan Filter**: All, Free, Basic, Pro, Enterprise

---

## Creating a New Tenant

### Step-by-Step Process

1. Click **"Create Tenant"** button (top right)
2. Fill in the organization details
3. The system automatically:
   - Generates a subdomain
   - Creates an admin user
   - Sets up default retention policy
   - Sends welcome email with credentials

### Form Fields

#### Organization Information

| Field | Required | Description | Example |
|-------|----------|-------------|---------|
| Organization Name | ✅ | Full company name | Acme Corporation |
| Slug | ✅ | URL-safe identifier (auto-generated) | acme-corporation |
| Subdomain | ✅ | Tenant subdomain (auto-generated) | acme |
| Plan | ✅ | Subscription plan | Basic, Pro, Enterprise |
| Status | ✅ | Initial status | Trial, Active |

#### Admin User (Only on Create)

| Field | Required | Description | Example |
|-------|----------|-------------|---------|
| Admin Name | ✅ | Full name of admin user | John Doe |
| Admin Email | ✅ | Email (must be unique) | john@acme.com |
| Password | Auto | Auto-generated securely | Sent via email |

#### Limits & Quotas

| Field | Required | Description | Default |
|-------|----------|-------------|---------|
| Max Users | Optional | Maximum users allowed | Based on plan |
| Max Docs/Month | Optional | Monthly document limit | Based on plan |
| Trial Ends At | Optional | Trial expiration date | +30 days |
| Admin Notes | Optional | Internal notes | - |

### Auto-Generation Features

**Slug**: Automatically generated from organization name
- "Acme Corporation" → `acme-corporation`
- Lowercase, alphanumeric + hyphens only

**Subdomain**: Defaults to slug value
- Can be customized before saving
- Must be unique across all tenants
- Results in: `acme.firmalum.com`

**Password**: 12-character secure random password
- Sent to admin user via email
- User should change it on first login

### What Happens After Creation?

1. **Tenant created** in database
2. **Admin user created** with role='admin'
3. **Default retention policy** created (5 years)
4. **Default settings** applied:
   - Branding colors
   - Timezone (Europe/Madrid)
   - Locale (en)
5. **Welcome email sent** to admin with:
   - Organization URL
   - Login credentials
   - Getting started instructions
6. **Audit trail logged**

---

## Managing Existing Tenants

### Editing a Tenant

1. Find the tenant in the table
2. Click the **Edit** icon (pencil)
3. Update any of the following:
   - Organization name
   - Plan (triggers auto-limit update)
   - Status
   - Quotas
   - Trial end date
   - Admin notes

⚠️ **Note**: Slug and subdomain cannot be changed after creation (data integrity)

### Tenant Table Columns

| Column | Description |
|--------|-------------|
| Organization | Name and slug |
| Subdomain | Full subdomain URL |
| Plan | Current subscription plan (badge) |
| Status | Current status (badge) |
| Users | Current / Maximum users |
| Created | Creation date |
| Actions | Edit, Suspend/Unsuspend buttons |

---

## Suspending & Unsuspending Tenants

### Suspending a Tenant

**Use Cases:**
- Payment overdue
- Terms of service violation
- Security concerns
- Tenant request

**Process:**
1. Click **Suspend** icon (red circle with slash)
2. Enter suspension reason (minimum 10 characters)
3. Click **"Suspend Tenant"**

**What Happens:**
- Status changed to 'suspended'
- `suspended_at` timestamp recorded
- `suspended_reason` saved
- **All tenant users blocked from login**
- **Admin users notified via email**
- Audit trail logged

**Email Notification:**
- Subject: "Firmalum Organization Suspended - Action Required"
- Contains: Organization name, suspension date, reason
- Sent to: All admin users of the tenant

### Unsuspending a Tenant

1. Find suspended tenant in table
2. Click **Unsuspend** icon (green checkmark)
3. Confirm action

**What Happens:**
- Status changed to 'active'
- `suspended_at` cleared
- `suspended_reason` cleared
- Users can log in again
- Audit trail logged

---

## Plan Limits Reference

### Plan Comparison

| Feature | Free | Basic | Pro | Enterprise |
|---------|------|-------|-----|------------|
| Max Users | 1 | 5 | 20 | ∞ Unlimited |
| Docs/Month | 10 | 50 | 500 | ∞ Unlimited |
| Price | Free | $29/mo | $99/mo | Custom |
| Support | Community | Email | Priority | Dedicated |

### Limits Behavior

- **Null value** = Unlimited
- **Numeric value** = Hard limit enforced
- Limits are checked in real-time:
  - User creation blocked if `users_count >= max_users`
  - Document upload blocked if monthly quota reached

### Changing Plans

When changing a tenant's plan:
1. Select new plan in edit modal
2. Click **"Update Tenant"**
3. Limits are automatically applied based on new plan
4. Existing users/documents are NOT deleted
5. Future additions respect new limits

**Example:**
- Downgrade Pro (20 users) → Basic (5 users)
- Existing 15 users remain active
- Cannot add new users until count < 5

---

## Best Practices

### Security

- ✅ Always use HTTPS in production
- ✅ Change default superadmin password immediately
- ✅ Limit number of superadmin accounts (2-3 max)
- ✅ Document suspension reasons clearly
- ✅ Review audit trail regularly

### Tenant Management

- ✅ Use descriptive organization names
- ✅ Keep slugs short and memorable
- ✅ Set realistic quotas based on plan
- ✅ Use trial period for new signups (30 days)
- ✅ Document suspension reasons for legal protection
- ✅ Add admin notes for important context

### Communication

- ✅ Notify tenants before suspension if possible
- ✅ Respond to suspension inquiries within 24h
- ✅ Document all major changes in admin notes
- ✅ Keep welcome email template professional

---

## Troubleshooting

### Common Issues

#### Issue: Cannot access /admin/tenants

**Cause**: User doesn't have superadmin role

**Solution**:
```sql
-- Check user role
SELECT id, email, role FROM users WHERE email = 'your@email.com';

-- Update to superadmin if needed
UPDATE users SET role = 'superadmin' WHERE email = 'your@email.com';
```

#### Issue: Slug/subdomain already exists

**Cause**: Another tenant is using that slug

**Solution**:
- Modify the slug to make it unique
- Try adding a suffix: `acme-corp`, `acme-inc`, `acme-2024`

#### Issue: Welcome email not received

**Cause**: Email queue not running or SMTP misconfigured

**Solution**:
```bash
# Check queue status
php artisan queue:work

# Test email configuration
php artisan tinker
>>> Mail::raw('Test', function($m) { $m->to('test@test.com')->subject('Test'); });
```

#### Issue: Tenant has 0 users after creation

**Cause**: Admin user creation failed

**Solution**:
1. Check logs: `storage/logs/laravel.log`
2. Manually create admin user:
```php
php artisan tinker
>>> $tenant = Tenant::find(ID);
>>> User::create([
    'tenant_id' => $tenant->id,
    'name' => 'Admin Name',
    'email' => 'admin@tenant.com',
    'password' => Hash::make('password'),
    'role' => 'admin',
]);
```

#### Issue: Limits not enforcing

**Cause**: Limits are null (unlimited)

**Solution**:
- Edit tenant and set specific numeric limits
- Or apply plan limits: `$tenant->applyPlanLimits();`

---

## Audit Trail

All tenant management actions are logged:

### Events Tracked

| Event | Description |
|-------|-------------|
| `tenant.created` | New tenant created |
| `tenant.updated` | Tenant details modified |
| `tenant.suspended` | Tenant suspended |
| `tenant.unsuspended` | Tenant reactivated |

### Viewing Audit Trail

```php
// Get audit trail for a tenant
$tenant = Tenant::find($id);
$auditEntries = AuditTrailEntry::where('signing_process_id', $tenant->id)
    ->orderBy('created_at', 'desc')
    ->get();
```

---

## API Reference

### Tenant Model Methods

```php
// Check status
$tenant->isActive();          // bool
$tenant->isSuspended();        // bool
$tenant->isOnTrial();          // bool
$tenant->hasTrialExpired();    // bool

// Check quotas
$tenant->canAddUser();                 // bool
$tenant->hasReachedDocumentQuota();    // bool
$tenant->getDocumentQuota();           // int|null

// Manage status
$tenant->suspend($reason);     // void
$tenant->unsuspend();          // void

// Apply limits
$tenant->applyPlanLimits();    // void

// Get plan limits
Tenant::getPlanLimits('basic');  // array
```

---

## Support

For issues or questions about tenant management:

- **Internal Documentation**: This guide
- **Code Review**: Tech Lead approval required
- **Security Concerns**: Contact Security Expert
- **Architecture Questions**: Contact Software Architect

---

## Changelog

### Sprint 6 (2025-12-30)
- ✅ Initial tenant management implementation
- ✅ Superadmin panel created
- ✅ CRUD operations complete
- ✅ Suspension system implemented
- ✅ Welcome emails configured
- ✅ 25 feature tests passing

---

*Last updated: 2025-12-30*  
*Version: 1.0.0*  
*Sprint: 6 - E0-001*
