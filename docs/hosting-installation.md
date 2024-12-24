# CertificateHub Hosting Installation Guide

## Table of Contents
1. [cPanel Installation](#cpanel-installation)
2. [DirectAdmin Installation](#directadmin-installation)
3. [Administrator Setup](#administrator-setup)
4. [File Permissions](#file-permissions)
5. [Troubleshooting](#troubleshooting)

## cPanel Installation

### Step 1: Domain Setup
1. Log in to cPanel
2. Go to `Domains` section
3. Add a domain or subdomain for CertificateHub
4. Note the document root path

### Step 2: Database Creation
1. Go to `MySQL Databases`
2. Create a new database:
   - Database name: `your_username_certificatehub`
   - Note the full database name
3. Create database user:
   - Username: `your_username_certuser`
   - Generate a strong password
4. Add user to database with 'ALL PRIVILEGES'

### Step 3: File Upload
1. Download CertificateHub files
2. Go to `File Manager`
3. Navigate to your domain's document root
4. Upload CertificateHub files
5. Extract files if uploaded as zip

### Step 4: Environment Setup
1. Rename `.env.example` to `.env`
2. Edit `.env` file:
```env
APP_NAME=CertificateHub
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_username_certificatehub
DB_USERNAME=your_username_certuser
DB_PASSWORD=your_database_password

MAIL_MAILER=smtp
MAIL_HOST=your-mail-server
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Step 5: Application Setup
1. Open Terminal (SSH) or use cPanel's Terminal:
```bash
cd public_html/certificatehub
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate
php artisan storage:link
```

2. Set up cron job in cPanel:
   - Go to `Cron Jobs`
   - Add new cron job:
   ```
   * * * * * cd /home/username/public_html/certificatehub && php artisan schedule:run >> /dev/null 2>&1
   ```

## DirectAdmin Installation

### Step 1: Domain Setup
1. Log in to DirectAdmin
2. Go to `Domain Setup`
3. Add domain or subdomain
4. Note the document root path

### Step 2: Database Creation
1. Go to `MySQL Management`
2. Create new database:
   - Database name: `certificatehub`
   - Create new user
   - Assign privileges

### Step 3: File Upload
1. Go to `File Manager`
2. Navigate to domain directory
3. Upload CertificateHub files
4. Extract if needed

### Step 4: Environment Setup
Same as cPanel setup, adjust paths accordingly.

### Step 5: Application Setup
Same commands as cPanel setup.

## Administrator Setup

### Create Initial Admin Account
1. Run the admin creation command:
```bash
php artisan make:admin
```

2. Or use the seeder:
```bash
php artisan db:seed --class=AdminSeeder
```

Default admin credentials:
- Email: admin@certificatehub.com
- Password: password123

**IMPORTANT**: Change these credentials immediately after first login!

### Security Checklist
1. Change admin password
2. Configure email settings
3. Set up backup schedule
4. Configure SSL certificate
5. Set up firewall rules

## File Permissions

### Check Required Permissions
```bash
#!/bin/bash

echo "Checking CertificateHub file permissions..."

# Define paths
APP_ROOT="/path/to/certificatehub"
STORAGE_PATH="$APP_ROOT/storage"
BOOTSTRAP_CACHE="$APP_ROOT/bootstrap/cache"
PUBLIC_PATH="$APP_ROOT/public"

# Function to check directory permissions
check_directory() {
    local dir=$1
    local required_perms=$2
    
    echo "Checking $dir..."
    
    if [ ! -d "$dir" ]; then
        echo "❌ Directory not found: $dir"
        return 1
    }
    
    current_perms=$(stat -f "%A" "$dir")
    if [ "$current_perms" != "$required_perms" ]; then
        echo "❌ Incorrect permissions on $dir"
        echo "Current: $current_perms, Required: $required_perms"
        return 1
    }
    
    echo "✅ Permissions correct for $dir"
    return 0
}

# Check critical directories
check_directory "$STORAGE_PATH" "775" || ERRORS=1
check_directory "$BOOTSTRAP_CACHE" "775" || ERRORS=1
check_directory "$PUBLIC_PATH" "755" || ERRORS=1

# Check storage subdirectories
for dir in app framework logs; do
    check_directory "$STORAGE_PATH/$dir" "775" || ERRORS=1
done

# Fix permissions if needed
if [ "$ERRORS" == "1" ]; then
    echo "Fixing permissions..."
    chmod -R 775 "$STORAGE_PATH"
    chmod -R 775 "$BOOTSTRAP_CACHE"
    chmod -R 755 "$PUBLIC_PATH"
    echo "Permissions fixed!"
fi
```

Save this as `check-permissions.sh` and run:
```bash
chmod +x check-permissions.sh
./check-permissions.sh
```

### Required Permissions
- `storage/`: 775
- `bootstrap/cache/`: 775
- `public/`: 755
- Other directories: 755
- PHP files: 644
- Configuration files: 644

## Troubleshooting

### Common Issues

1. **500 Server Error**
   - Check storage permissions
   - Verify .env file exists
   - Check log files in storage/logs

2. **Database Connection Error**
   - Verify database credentials
   - Check database user privileges
   - Confirm database exists

3. **File Upload Issues**
   - Check storage/app permissions
   - Verify symbolic links
   - Check PHP upload limits

4. **Email Not Working**
   - Verify SMTP settings
   - Check mail logs
   - Test mail configuration

### File Checklist
```bash
#!/bin/bash

echo "Checking required files..."

required_files=(
    ".env"
    "artisan"
    "composer.json"
    "public/index.php"
    "public/.htaccess"
    "storage/app/.gitignore"
    "storage/framework/views/.gitignore"
    "storage/logs/.gitignore"
    "bootstrap/cache/.gitignore"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ Found: $file"
    else
        echo "❌ Missing: $file"
        MISSING_FILES=1
    fi
done

if [ "$MISSING_FILES" == "1" ]; then
    echo "Some required files are missing. Please check your installation."
    exit 1
fi

echo "All required files present!"
```

Save as `check-files.sh` and run:
```bash
chmod +x check-files.sh
./check-files.sh
```

### Support
If you encounter any issues:
1. Check the logs in `storage/logs/laravel.log`
2. Contact support: support@certificatehub.com
3. Visit our community forum: forum.certificatehub.com
