# CertificateHub Installation Guide

## Quick Start

### Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Node.js 16+
- Composer 2.x
- Git

### Step 1: Clone Repository
```bash
git clone https://github.com/your-org/certificatehub.git
cd certificatehub
```

### Step 2: Install Dependencies
```bash
composer install
npm install
```

### Step 3: Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=certificatehub
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### Step 5: Build Assets
```bash
npm run build
```

### Step 6: Storage Setup
```bash
php artisan storage:link
```

### Step 7: Start Development Server
```bash
php artisan serve
```

Visit `http://localhost:8000` to access the application.

## Production Deployment

### Server Requirements
- Nginx or Apache
- PHP-FPM
- MySQL/MariaDB
- Redis (recommended)
- Supervisor
- SSL Certificate

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/certificatehub/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Supervisor Configuration
```ini
[program:certificatehub-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/certificatehub/artisan queue:work
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/certificatehub/storage/logs/worker.log
```

### Cron Configuration
Add to crontab:
```bash
* * * * * cd /path/to/certificatehub && php artisan schedule:run >> /dev/null 2>&1
```

### SSL Configuration
1. Install Certbot:
```bash
sudo apt install certbot python3-certbot-nginx
```

2. Obtain certificate:
```bash
sudo certbot --nginx -d your-domain.com
```

### Redis Configuration
1. Install Redis:
```bash
sudo apt install redis-server
```

2. Update `.env`:
```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### File Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Troubleshooting

### Common Issues

1. **Storage Permission Issues**
```bash
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

2. **Cache Issues**
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

3. **Composer Issues**
```bash
composer dump-autoload
```

4. **Node/NPM Issues**
```bash
rm -rf node_modules
npm cache clean --force
npm install
```

### Verification Steps

1. Check PHP requirements:
```bash
php artisan about
```

2. Verify database connection:
```bash
php artisan migrate:status
```

3. Test queue connection:
```bash
php artisan queue:listen --timeout=0
```

4. Check storage symlink:
```bash
php artisan storage:link
```

### Getting Help
- Documentation: https://docs.certificatehub.com
- Issues: https://github.com/your-org/certificatehub/issues
- Support: support@certificatehub.com
