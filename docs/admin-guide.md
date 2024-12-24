# CertificateHub Administrator Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [User Management](#user-management)
5. [System Maintenance](#system-maintenance)
6. [Monitoring](#monitoring)
7. [Backup & Recovery](#backup--recovery)
8. [Security](#security)

## System Requirements

### Server Requirements
- PHP 8.1 or higher
- MySQL 8.0 or higher
- Node.js 16.x or higher
- Composer 2.x
- Redis (optional, for caching)

### PHP Extensions
- BCMath PHP Extension
- Ctype PHP Extension
- JSON PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- GD Library or Imagick

## Installation

### Fresh Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/your-org/certificatehub.git
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Set up environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database in `.env`

5. Run migrations:
   ```bash
   php artisan migrate
   ```

6. Build assets:
   ```bash
   npm run build
   ```

### Updating
Use the deployment script:
```bash
./deploy.sh
```

## Configuration

### Environment Variables
Key configurations in `.env`:
```env
APP_NAME=CertificateHub
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=certificatehub
DB_USERNAME=user
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### Queue Configuration
1. Configure supervisor:
   ```conf
   [program:certificatehub-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/certificatehub/artisan queue:work
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=4
   redirect_stderr=true
   stdout_logfile=/path/to/certificatehub/storage/logs/worker.log
   ```

2. Start workers:
   ```bash
   supervisorctl reread
   supervisorctl update
   supervisorctl start all
   ```

## User Management

### Managing Users
- Create/edit users in Admin > Users
- Assign roles and permissions
- Monitor user activity
- Impersonate users for troubleshooting

### Roles and Permissions
Default roles:
- Admin: Full system access
- User: Limited to own resources

## System Maintenance

### Regular Tasks
1. Clean old certificates:
   ```bash
   php artisan certificates:cleanup
   ```

2. Optimize database:
   ```bash
   php artisan db:maintain
   ```

3. Clear cache:
   ```bash
   php artisan optimize:clear
   ```

### Monitoring Tasks
1. Check system logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. Monitor queue status:
   ```bash
   php artisan queue:monitor
   ```

## Monitoring

### Key Metrics
- Server resources (CPU, memory, disk)
- Queue length and processing time
- Database performance
- API response times

### Log Management
- Application logs: `storage/logs/laravel.log`
- Queue worker logs: `storage/logs/worker.log`
- Email logs: `storage/logs/mail.log`

## Backup & Recovery

### Backup Procedure
1. Run backup script:
   ```bash
   ./backup.sh
   ```

2. Verify backup integrity:
   ```bash
   ./verify-backup.sh latest
   ```

### Recovery Procedure
1. Stop application:
   ```bash
   php artisan down
   ```

2. Restore from backup:
   ```bash
   ./restore.sh backup_file.tar.gz
   ```

3. Verify restoration:
   ```bash
   php artisan migrate:status
   ```

4. Start application:
   ```bash
   php artisan up
   ```

## Security

### Best Practices
1. Regular updates:
   ```bash
   composer update
   npm update
   ```

2. Security scanning:
   ```bash
   composer security-check
   ```

3. File permissions:
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

### SSL Configuration
1. Install SSL certificate
2. Configure Nginx/Apache
3. Enable HTTPS-only access
4. Set up HSTS

### Firewall Rules
- Allow HTTP (80)
- Allow HTTPS (443)
- Allow SSH (22)
- Allow MySQL (3306) only from trusted IPs
- Allow Redis (6379) only from localhost
