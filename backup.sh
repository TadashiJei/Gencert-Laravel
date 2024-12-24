#!/bin/bash

# Exit on error
set -e

# Configuration
BACKUP_DIR="/path/to/backups"
APP_DIR="/path/to/app"
DB_NAME="certificatehub"
DB_USER="root"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_PATH="${BACKUP_DIR}/backup_${TIMESTAMP}"

# Create backup directory
echo "Creating backup directory..."
mkdir -p "${BACKUP_PATH}"

# Backup database
echo "Backing up database..."
mysqldump -u "${DB_USER}" "${DB_NAME}" > "${BACKUP_PATH}/database.sql"

# Backup application files
echo "Backing up application files..."
tar -czf "${BACKUP_PATH}/app.tar.gz" \
    --exclude="vendor" \
    --exclude="node_modules" \
    --exclude=".git" \
    "${APP_DIR}"

# Backup storage files
echo "Backing up storage files..."
tar -czf "${BACKUP_PATH}/storage.tar.gz" "${APP_DIR}/storage/app"

# Create backup info file
echo "Creating backup info file..."
cat > "${BACKUP_PATH}/backup_info.txt" << EOF
Backup Date: $(date)
Application Version: $(git describe --tags --abbrev=0)
Git Commit: $(git rev-parse HEAD)
EOF

# Compress entire backup
echo "Compressing backup..."
cd "${BACKUP_DIR}"
tar -czf "backup_${TIMESTAMP}.tar.gz" "backup_${TIMESTAMP}"
rm -rf "backup_${TIMESTAMP}"

# Cleanup old backups (keep last 7 days)
echo "Cleaning up old backups..."
find "${BACKUP_DIR}" -name "backup_*.tar.gz" -mtime +7 -delete

echo "Backup completed successfully!"
echo "Backup location: ${BACKUP_DIR}/backup_${TIMESTAMP}.tar.gz"
