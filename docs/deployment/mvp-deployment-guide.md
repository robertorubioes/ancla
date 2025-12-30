# Firmalum MVP Deployment Guide

## Overview

This guide provides a complete deployment strategy for Firmalum, optimized for **MVP speed** with the ability to scale and iterate quickly.

---

## Table of Contents

1. [Deployment Strategy Recommendation](#deployment-strategy-recommendation)
2. [Infrastructure Options Comparison](#infrastructure-options-comparison)
3. [Recommended Stack: Laravel Forge + DigitalOcean](#recommended-stack-laravel-forge--digitalocean)
4. [MVP Feature Set](#mvp-feature-set)
5. [Step-by-Step Deployment](#step-by-step-deployment)
6. [CI/CD Pipeline](#cicd-pipeline)
7. [Monitoring & Alerting](#monitoring--alerting)
8. [Scaling Path](#scaling-path)
9. [Cost Estimation](#cost-estimation)

---

## Deployment Strategy Recommendation

### TL;DR - Recommended MVP Stack

| Component | Provider | Rationale |
|-----------|----------|-----------|
| **Orchestration** | Laravel Forge | Automated server management, zero DevOps |
| **Server** | DigitalOcean Droplet | Good price/performance, EU datacenter |
| **Database** | DigitalOcean Managed MySQL | Automated backups, no maintenance |
| **Cache/Queue** | Redis (Forge managed) | Fast, reliable |
| **Storage** | DigitalOcean Spaces | S3-compatible, cheap |
| **CDN** | DigitalOcean CDN (Spaces) | Global distribution |
| **Email** | Postmark | Best deliverability, fair pricing |
| **SSL** | Let's Encrypt (Forge) | Free, automated |
| **Monitoring** | Laravel Forge + Sentry | Errors + uptime |

**Estimated Monthly Cost: ~€70-100/month**

---

## Infrastructure Options Comparison

### Option 1: VPS Simple (Hetzner/DigitalOcean)

**Pros:**
- ✅ Cheapest option (~€20-30/month)
- ✅ Full control over server
- ✅ No vendor lock-in

**Cons:**
- ❌ Manual server management
- ❌ Own security hardening
- ❌ Manual deployments (unless scripted)
- ❌ Higher maintenance burden

**Best for:** Teams with DevOps experience, budget-constrained projects

### Option 2: PaaS (Laravel Forge + DO) ⭐ RECOMMENDED

**Pros:**
- ✅ Automated server provisioning
- ✅ Zero-downtime deployments
- ✅ SSL certificates automated
- ✅ Queue worker management
- ✅ Database backups automated
- ✅ Still affordable (~€70-100/month total)
- ✅ Quick to set up (1-2 hours)

**Cons:**
- ❌ Monthly Forge fee ($12/month)
- ❌ Less flexibility than raw VPS

**Best for:** MVP, small teams without dedicated DevOps

### Option 3: Laravel Vapor (Serverless)

**Pros:**
- ✅ Infinite scalability
- ✅ Pay-per-use
- ✅ Zero server management
- ✅ Global edge network

**Cons:**
- ❌ Higher complexity for debugging
- ❌ Cold starts can affect UX
- ❌ AWS vendor lock-in
- ❌ More expensive at low traffic
- ❌ PDF generation requires Lambda layers
- ❌ Longer initial setup

**Best for:** High-scale production, variable traffic

---

## Recommended Stack: Laravel Forge + DigitalOcean

### Why This Choice for MVP

1. **Speed**: Full deployment in 1-2 hours
2. **Simplicity**: No DevOps knowledge required
3. **Cost-effective**: ~€70-100/month for everything
4. **Laravel-optimized**: Built specifically for Laravel apps
5. **Scalable path**: Easy to scale up or migrate to Vapor later

### Architecture Diagram

```
                    ┌─────────────────────────────────────┐
                    │           USERS                      │
                    │    *.firmalum.com / custom domains      │
                    └─────────────────┬───────────────────┘
                                      │
                                      ▼
                    ┌─────────────────────────────────────┐
                    │         CLOUDFLARE (optional)        │
                    │     DNS + DDoS Protection + CDN      │
                    └─────────────────┬───────────────────┘
                                      │
                    ┌─────────────────▼───────────────────┐
                    │      DIGITALOCEAN LOAD BALANCER      │
                    │        (when scaling needed)         │
                    └─────────────────┬───────────────────┘
                                      │
        ┌─────────────────────────────┼─────────────────────────────┐
        │                             │                             │
        ▼                             ▼                             ▼
┌───────────────┐           ┌───────────────┐           ┌───────────────┐
│   WEB SERVER  │           │  WEB SERVER   │           │ QUEUE WORKER  │
│   (Nginx)     │           │   (Nginx)     │           │  (Supervisor) │
│               │           │               │           │               │
│ Laravel App   │           │ Laravel App   │           │ Laravel App   │
│ PHP 8.2-FPM   │           │ PHP 8.2-FPM   │           │ php artisan   │
│               │           │               │           │ queue:work    │
└───────┬───────┘           └───────┬───────┘           └───────┬───────┘
        │                           │                           │
        └───────────────────────────┼───────────────────────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        ▼                           ▼                           ▼
┌───────────────┐           ┌───────────────┐           ┌───────────────┐
│    MySQL      │           │     Redis     │           │  DO Spaces    │
│   Managed     │           │    (Cache)    │           │  (S3-compat)  │
│               │           │   (Sessions)  │           │               │
│  Primary DB   │           │   (Queues)    │           │  Documents    │
│  + Backups    │           │               │           │  + Evidence   │
└───────────────┘           └───────────────┘           └───────────────┘
        │
        ▼
┌───────────────────────────────────────────────────────────────────────┐
│                         EXTERNAL SERVICES                              │
├───────────────┬───────────────┬───────────────┬───────────────────────┤
│    Postmark   │  FreeTSA.org  │   ipapi.co    │   Sentry.io           │
│    (Email)    │    (TSA)      │ (Geolocation) │   (Error tracking)    │
└───────────────┴───────────────┴───────────────┴───────────────────────┘
```

---

## MVP Feature Set

### MUST HAVE (Launch Blockers)

| Feature | Status | Notes |
|---------|--------|-------|
| Multi-tenant auth with 2FA | ✅ Done | ADR-003, ADR-004 |
| Document upload & hashing | 🔄 Ready | ADR-007 |
| Audit trail with chain hashing | ✅ Done | ADR-005 |
| TSA timestamp integration | ✅ Done | Using FreeTSA for MVP |
| Basic signature workflow | 📋 Planned | E2-002 |
| Evidence package generation | ✅ Done | ADR-005 |
| Device fingerprinting | ✅ Done | ADR-006 |
| Consent capture | ✅ Done | ADR-006 |
| Email notifications | 📋 Planned | E2-003 |
| Basic admin dashboard | 📋 Planned | E3-001 |

### NICE TO HAVE (Post-MVP)

| Feature | Priority | Sprint |
|---------|----------|--------|
| Public verification API | High | Sprint 3 |
| Long-term archive (5yr) | High | Sprint 3 |
| Biometric signature | Medium | Sprint 4 |
| Advanced branding | Low | Sprint 5 |
| API integrations | Medium | Sprint 4 |
| Mobile responsive | Medium | Sprint 4 |

### DEFER TO LATER

- Advanced analytics
- Batch operations
- Webhook integrations
- Multi-language support (beyond ES/EN)
- SSO/SAML integration
- Custom domain SSL automation

---

## Step-by-Step Deployment

### Prerequisites

1. **Accounts needed:**
   - DigitalOcean account
   - Laravel Forge account ($12/month)
   - Postmark account (free tier to start)
   - Sentry.io account (free tier)
   - GitHub repository access

### Step 1: Provision Server via Forge

1. Connect Forge to DigitalOcean
2. Create new server:
   - **Region**: Frankfurt (FRA1) for EU compliance
   - **Size**: 2 GB RAM / 2 vCPUs ($18/month)
   - **PHP Version**: 8.2
   - **Database**: None (we'll use managed)

### Step 2: Create Managed MySQL Database

1. In DigitalOcean, create MySQL cluster:
   - **Region**: Same as server (FRA1)
   - **Size**: Basic 1GB ($15/month)
   - **Engine**: MySQL 8

2. Configure connection in Forge:
   - Add firewall rule to allow Forge server IP

### Step 3: Configure Redis

Option A: Managed Redis (recommended for production)
- DigitalOcean Managed Redis: $15/month

Option B: Redis on server (fine for MVP)
- Install via Forge → Server → Install Redis

### Step 4: Set Up DigitalOcean Spaces

1. Create Space:
   - **Region**: Frankfurt
   - **Name**: firmalum-storage

2. Create access keys and configure in `.env`

### Step 5: Configure Site in Forge

1. Add new site:
   - **Domain**: firmalum.com
   - **Project Type**: General PHP / Laravel
   - **Web Directory**: /public

2. Connect GitHub repository

3. Configure deployment script:

```bash
cd /home/forge/firmalum.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan queue:restart
fi
```

### Step 6: Configure Environment Variables

See [environment-variables.md](./environment-variables.md) for complete list.

### Step 7: Set Up Queue Worker

In Forge → Server → Daemons:

```
Command: php /home/forge/firmalum.com/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
User: forge
Directory: /home/forge/firmalum.com
```

### Step 8: Configure SSL

1. In Forge → Site → SSL
2. Select "Let's Encrypt"
3. Add all domains (including wildcard for subdomains)

### Step 9: Set Up Scheduler

In Forge → Server → Scheduler:

```
Command: php /home/forge/firmalum.com/artisan schedule:run
Frequency: Every Minute
User: forge
```

---

## CI/CD Pipeline

### GitHub Actions Workflow

The CI/CD pipeline is defined in `.github/workflows/ci.yml` and handles:

1. **Testing**: Run PHPUnit tests
2. **Linting**: Check code style with Pint
3. **Static Analysis**: Run PHPStan
4. **Deploy**: Trigger Forge deployment on main branch

```yaml
# See .github/workflows/ci.yml for full configuration
```

### Deployment Flow

```
Developer Push → GitHub Actions → Tests Pass → Forge Webhook → Server Deploy
```

### Rollback Procedure

**Quick Rollback (< 5 min):**
1. SSH into server: `ssh forge@your-server`
2. `cd /home/forge/firmalum.com`
3. `git log --oneline -5` (find last good commit)
4. `git checkout <commit-hash>`
5. `php artisan migrate:rollback --step=1` (if needed)
6. `sudo service php8.2-fpm restart`

**Using Forge:**
1. Go to Forge → Site → Deployments
2. Click "Rollback" on last successful deployment

---

## Monitoring & Alerting

### Recommended Stack

| Tool | Purpose | Cost |
|------|---------|------|
| **Laravel Forge** | Server health, uptime | Included |
| **Sentry** | Error tracking, performance | Free tier |
| **DigitalOcean Monitoring** | Server metrics | Free |
| **UptimeRobot** | External uptime monitoring | Free |
| **Laravel Telescope** | Debug (staging only) | Free |

### Essential Alerts

1. **Server Down**: Forge + UptimeRobot
2. **High Error Rate**: Sentry
3. **Database Connection Failed**: Sentry + Forge
4. **Queue Backed Up**: Custom Laravel alert
5. **Disk Space Low**: DigitalOcean alerts
6. **SSL Expiring**: Forge (auto-renewal)

### Health Check Endpoint

Add to `routes/web.php`:

```php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Cache::has('health-check');
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 503);
    }
})->name('health');
```

---

## Scaling Path

### Phase 1: MVP (Current)

- Single server (2GB RAM)
- Managed MySQL (1GB)
- Redis on server
- DO Spaces for storage

**Handles**: ~100 concurrent users, ~1000 documents/day

### Phase 2: Growth (When Needed)

- Upgrade to 4GB RAM server
- Managed Redis
- Add CDN for static assets
- Separate queue worker server

**Handles**: ~500 concurrent users, ~10,000 documents/day

### Phase 3: Scale (Future)

- Load balancer + multiple servers
- Larger managed database
- Redis cluster
- Consider migration to Laravel Vapor

**Handles**: ~5000+ concurrent users

### Scaling Triggers

| Metric | Threshold | Action |
|--------|-----------|--------|
| CPU | >80% sustained | Upgrade server |
| Memory | >85% | Upgrade server |
| DB connections | >80% of max | Upgrade DB |
| Queue latency | >30 seconds | Add worker |
| Response time | >2 seconds | Investigate/scale |

---

## Cost Estimation

### MVP Phase (Month 1-6)

| Service | Monthly Cost |
|---------|--------------|
| Laravel Forge | $12 |
| DO Droplet (2GB) | $18 |
| DO Managed MySQL | $15 |
| DO Spaces (100GB) | $5 |
| Postmark (10K emails) | $10 |
| Sentry (free tier) | $0 |
| Domain (firmalum.com) | ~$2 |
| **TOTAL** | **~€60-70/month** |

### Growth Phase (Month 6-12)

| Service | Monthly Cost |
|---------|--------------|
| Laravel Forge | $12 |
| DO Droplet (4GB) | $24 |
| DO Managed MySQL (2GB) | $30 |
| DO Managed Redis | $15 |
| DO Spaces (500GB) | $10 |
| Postmark (50K emails) | $40 |
| Sentry (Team plan) | $26 |
| Cloudflare Pro | $20 |
| **TOTAL** | **~€180/month** |

### Cost Optimization Tips

1. **DO Spaces**: Use lifecycle rules to move old files to cheaper storage
2. **Reserved pricing**: Commit to annual for 20% savings on DO
3. **Email**: Use SES for transactional if volume is high
4. **CDN**: Cloudflare free tier is sufficient for MVP

---

## Security Checklist

### Before Launch

- [ ] All secrets in environment variables (not in code)
- [ ] HTTPS enforced (redirect HTTP)
- [ ] CORS configured correctly
- [ ] Rate limiting enabled
- [ ] Debug mode OFF in production
- [ ] APP_KEY generated uniquely
- [ ] Database credentials secure
- [ ] S3 bucket private by default
- [ ] Firewall configured (only 80, 443, 22)
- [ ] SSH key-only authentication
- [ ] Fail2ban installed and configured
- [ ] Regular backups verified
- [ ] Laravel security headers middleware

### Post-Launch

- [ ] Regular dependency updates (`composer update`)
- [ ] Security audit logs reviewed weekly
- [ ] Backup restoration tested monthly
- [ ] SSL certificate auto-renewal working
- [ ] Error monitoring active and reviewed

---

## Quick Reference Commands

### Deployment

```bash
# Manual deploy
forge deploy firmalum.com

# SSH into server
forge ssh firmalum.com

# View logs
tail -f /home/forge/firmalum.com/storage/logs/laravel.log
```

### Maintenance

```bash
# Clear all caches
php artisan optimize:clear

# Queue management
php artisan queue:restart
php artisan queue:monitor redis:default --max=100

# Database
php artisan migrate --force
php artisan migrate:rollback --step=1
```

### Debugging

```bash
# Check queue
php artisan queue:failed
php artisan queue:retry all

# Check scheduler
php artisan schedule:list

# Check config
php artisan config:show
```

---

## Next Steps

1. ✅ Review this guide
2. 📋 Set up Forge account
3. 📋 Provision infrastructure
4. 📋 Configure CI/CD
5. 📋 Deploy to staging
6. 📋 Run security checklist
7. 📋 Deploy to production
8. 📋 Set up monitoring
9. 🚀 Launch MVP!

---

**Document Version**: 1.0
**Last Updated**: 2025-12-28
**Author**: Firmalum Architecture Team
