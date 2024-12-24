#!/bin/bash

# Exit on error
set -e

echo "Starting deployment process..."

# Pull latest changes
echo "Pulling latest changes..."
git pull origin main

# Install/update Composer dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install/update npm dependencies
echo "Installing npm dependencies..."
npm install
npm run build

# Clear caches
echo "Clearing application cache..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Optimize the application
echo "Optimizing the application..."
php artisan optimize
php artisan view:cache
php artisan config:cache
php artisan route:cache

# Update storage link
echo "Updating storage links..."
php artisan storage:link

# Set permissions
echo "Setting file permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart queue workers
echo "Restarting queue workers..."
php artisan queue:restart

# Clear and rebuild cache
echo "Rebuilding cache..."
php artisan cache:clear
php artisan config:cache

echo "Deployment completed successfully!"
