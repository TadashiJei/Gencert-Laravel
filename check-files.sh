#!/bin/bash

# CertificateHub File Checker
# This script checks for required files and their integrity

echo "CertificateHub File Checker"
echo "=========================="

# Get the application root directory
APP_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Define color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Function to check file existence
check_file() {
    local file=$1
    local required=$2
    
    echo -n "Checking ${file}... "
    
    if [ ! -f "$APP_ROOT/$file" ]; then
        if [ "$required" = true ]; then
            echo -e "${RED}❌ Missing (Required)${NC}"
            return 1
        else
            echo -e "${YELLOW}⚠️  Missing (Optional)${NC}"
            return 0
        fi
    fi
    
    echo -e "${GREEN}✅ Found${NC}"
    return 0
}

# Function to check directory existence
check_directory() {
    local dir=$1
    local required=$2
    
    echo -n "Checking ${dir}... "
    
    if [ ! -d "$APP_ROOT/$dir" ]; then
        if [ "$required" = true ]; then
            echo -e "${RED}❌ Missing (Required)${NC}"
            return 1
        else
            echo -e "${YELLOW}⚠️  Missing (Optional)${NC}"
            return 0
        fi
    fi
    
    echo -e "${GREEN}✅ Found${NC}"
    return 0
}

# Required files
echo -e "\n${YELLOW}Checking Required Files:${NC}"
ERRORS=0

required_files=(
    ".env:true"
    "artisan:true"
    "composer.json:true"
    "composer.lock:true"
    "package.json:true"
    "package-lock.json:false"
    "public/index.php:true"
    "public/.htaccess:true"
    "public/robots.txt:false"
    "public/favicon.ico:false"
    "README.md:false"
    "phpunit.xml:false"
    "webpack.mix.js:false"
    "server.php:true"
)

for item in "${required_files[@]}"; do
    IFS=':' read -r file required <<< "$item"
    check_file "$file" "$required" || ERRORS=$((ERRORS + 1))
done

# Required directories
echo -e "\n${YELLOW}Checking Required Directories:${NC}"

required_directories=(
    "app:true"
    "bootstrap:true"
    "config:true"
    "database:true"
    "public:true"
    "resources:true"
    "routes:true"
    "storage:true"
    "storage/app:true"
    "storage/framework:true"
    "storage/framework/cache:true"
    "storage/framework/sessions:true"
    "storage/framework/views:true"
    "storage/logs:true"
    "tests:false"
    "vendor:true"
    "node_modules:false"
)

for item in "${required_directories[@]}"; do
    IFS=':' read -r dir required <<< "$item"
    check_directory "$dir" "$required" || ERRORS=$((ERRORS + 1))
done

# Check Laravel specific files
echo -e "\n${YELLOW}Checking Laravel Configuration Files:${NC}"

config_files=(
    "config/app.php:true"
    "config/auth.php:true"
    "config/broadcasting.php:true"
    "config/cache.php:true"
    "config/database.php:true"
    "config/filesystems.php:true"
    "config/mail.php:true"
    "config/queue.php:true"
    "config/services.php:true"
    "config/session.php:true"
    "config/view.php:true"
)

for item in "${config_files[@]}"; do
    IFS=':' read -r file required <<< "$item"
    check_file "$file" "$required" || ERRORS=$((ERRORS + 1))
done

# Check CertificateHub specific files
echo -e "\n${YELLOW}Checking CertificateHub Specific Files:${NC}"

certificatehub_files=(
    "app/Http/Controllers/CertificateController.php:true"
    "app/Http/Controllers/TemplateController.php:true"
    "app/Models/Certificate.php:true"
    "app/Models/Template.php:true"
    "resources/views/certificates/index.blade.php:true"
    "resources/views/templates/index.blade.php:true"
    "database/migrations/2024_12_20_134555_create_settings_table.php:true"
    "database/migrations/2024_12_20_134556_create_audit_logs_table.php:true"
    "database/migrations/2024_12_20_135112_create_api_tokens_table.php:true"
)

for item in "${certificatehub_files[@]}"; do
    IFS=':' read -r file required <<< "$item"
    check_file "$file" "$required" || ERRORS=$((ERRORS + 1))
done

# Check storage symlink
echo -e "\n${YELLOW}Checking Storage Symlink:${NC}"
if [ -L "$APP_ROOT/public/storage" ]; then
    echo -e "${GREEN}✅ Storage symlink exists${NC}"
else
    echo -e "${RED}❌ Storage symlink missing${NC}"
    echo "Run 'php artisan storage:link' to create the symlink"
    ERRORS=$((ERRORS + 1))
fi

# Summary
echo -e "\n${YELLOW}File Check Summary:${NC}"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All required files are present!${NC}"
else
    echo -e "${RED}❌ Found $ERRORS missing required files${NC}"
    echo "Please ensure all required files are present before running the application"
fi

# Composer validation
echo -e "\n${YELLOW}Validating composer.json:${NC}"
if command -v composer &> /dev/null; then
    composer validate --no-check-publish || ERRORS=$((ERRORS + 1))
else
    echo -e "${YELLOW}⚠️  Composer not found, skipping validation${NC}"
fi

# Node.js dependencies check
echo -e "\n${YELLOW}Checking Node.js dependencies:${NC}"
if [ -f "$APP_ROOT/package.json" ]; then
    if command -v npm &> /dev/null; then
        echo "Running npm check..."
        npm ls --depth=0 || echo -e "${YELLOW}⚠️  Some dependencies might be missing${NC}"
    else
        echo -e "${YELLOW}⚠️  npm not found, skipping dependency check${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  package.json not found, skipping dependency check${NC}"
fi

exit $ERRORS
