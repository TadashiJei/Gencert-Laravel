#!/bin/bash

# CertificateHub Permission Checker
# This script checks and fixes common permission issues

echo "CertificateHub Permission Checker"
echo "================================"

# Get the application root directory
APP_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Define color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Function to check directory permissions
check_directory_permissions() {
    local dir=$1
    local required_perms=$2
    local owner=$3
    
    echo -n "Checking ${dir}... "
    
    if [ ! -d "$dir" ]; then
        echo -e "${RED}❌ Directory not found${NC}"
        return 1
    }
    
    current_perms=$(stat -f "%A" "$dir")
    current_owner=$(stat -f "%Su" "$dir")
    
    if [ "$current_perms" != "$required_perms" ] || [ "$current_owner" != "$owner" ]; then
        echo -e "${RED}❌ Incorrect permissions or owner${NC}"
        echo "Current: $current_perms ($current_owner), Required: $required_perms ($owner)"
        return 1
    }
    
    echo -e "${GREEN}✅ OK${NC}"
    return 0
}

# Function to check file permissions
check_file_permissions() {
    local file=$1
    local required_perms=$2
    
    echo -n "Checking ${file}... "
    
    if [ ! -f "$file" ]; then
        echo -e "${RED}❌ File not found${NC}"
        return 1
    }
    
    current_perms=$(stat -f "%A" "$file")
    
    if [ "$current_perms" != "$required_perms" ]; then
        echo -e "${RED}❌ Incorrect permissions${NC}"
        echo "Current: $current_perms, Required: $required_perms"
        return 1
    }
    
    echo -e "${GREEN}✅ OK${NC}"
    return 0
}

# Check critical directories
echo -e "\n${YELLOW}Checking Directory Permissions:${NC}"
ERRORS=0

# Define directories to check with their required permissions
declare -A directories=(
    ["$APP_ROOT/storage"]="775"
    ["$APP_ROOT/storage/app"]="775"
    ["$APP_ROOT/storage/framework"]="775"
    ["$APP_ROOT/storage/framework/views"]="775"
    ["$APP_ROOT/storage/framework/cache"]="775"
    ["$APP_ROOT/storage/framework/sessions"]="775"
    ["$APP_ROOT/storage/logs"]="775"
    ["$APP_ROOT/bootstrap/cache"]="775"
    ["$APP_ROOT/public"]="755"
)

# Get web server user
if [ -f /etc/apache2/envvars ]; then
    WEB_USER=$(grep -i 'APACHE_RUN_USER' /etc/apache2/envvars | cut -d= -f2)
elif [ -f /etc/nginx/nginx.conf ]; then
    WEB_USER=$(grep 'user' /etc/nginx/nginx.conf | awk '{print $2}' | tr -d ';')
else
    WEB_USER="www-data"
fi

for dir in "${!directories[@]}"; do
    check_directory_permissions "$dir" "${directories[$dir]}" "$WEB_USER" || ERRORS=$((ERRORS + 1))
done

# Check critical files
echo -e "\n${YELLOW}Checking File Permissions:${NC}"

declare -A files=(
    ["$APP_ROOT/.env"]="644"
    ["$APP_ROOT/artisan"]="755"
    ["$APP_ROOT/public/index.php"]="644"
    ["$APP_ROOT/public/.htaccess"]="644"
)

for file in "${!files[@]}"; do
    check_file_permissions "$file" "${files[$file]}" || ERRORS=$((ERRORS + 1))
done

# Summary
echo -e "\n${YELLOW}Permission Check Summary:${NC}"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All permissions are correct!${NC}"
else
    echo -e "${RED}❌ Found $ERRORS permission issues${NC}"
    
    # Ask to fix permissions
    read -p "Would you like to fix these permissions? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Fixing permissions..."
        
        # Fix directory permissions
        for dir in "${!directories[@]}"; do
            chmod "${directories[$dir]}" "$dir"
            chown -R "$WEB_USER" "$dir"
        done
        
        # Fix file permissions
        for file in "${!files[@]}"; do
            chmod "${files[$file]}" "$file"
        done
        
        echo -e "${GREEN}✅ Permissions fixed!${NC}"
    fi
fi

# Check SELinux if present
if command -v getenforce >/dev/null 2>&1; then
    echo -e "\n${YELLOW}Checking SELinux:${NC}"
    selinux_status=$(getenforce)
    echo "SELinux status: $selinux_status"
    
    if [ "$selinux_status" = "Enforcing" ]; then
        echo "SELinux is enforcing. You may need to set proper contexts:"
        echo "semanage fcontext -a -t httpd_sys_content_t '/path/to/certificatehub(/.*)?'"
        echo "restorecon -R /path/to/certificatehub"
    fi
fi

exit $ERRORS
