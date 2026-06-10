#!/bin/bash
# FastPOS Modern - Automated PostgreSQL Disaster Recovery Script
# CRON Expression for 3:00 AM daily: 0 3 * * * /path/to/fastpos/scripts/backup_db.sh >> /var/log/fastpos_backup.log 2>&1

set -e

# Directory where backups are stored locally
BACKUP_DIR="/var/backups/fastpos_db"
# Source the exact .env variables used in production
ENV_FILE="/var/www/fastpos/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env file not found at $ENV_FILE"
    exit 1
fi

source "$ENV_FILE"

# Container details mapped directly from docker-compose.prod.yml
CONTAINER_NAME="fastpos_postgres_prod"
DB_NAME="${DB_DATABASE:-fastpos}"
DB_USER="${DB_USERNAME:-postgres}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FILENAME="fastpos_backup_${TIMESTAMP}.sql.gz"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Starting secure backup for database: $DB_NAME"

# Execute pg_dump inside the running container and compress the stream on the host
# Using pg_dump via docker exec ensures we don't need postgres client on the host machine
docker exec "$CONTAINER_NAME" pg_dump -U "$DB_USER" -d "$DB_NAME" -F c | gzip > "$FILEPATH"

echo "[$(date)] Backup completed successfully: $FILEPATH"

# Offsite upload (AWS S3)
# Ensure AWS CLI is installed and configured on the host machine
if command -v aws >/dev/null 2>&1; then
    S3_BUCKET="s3://your-fastpos-backups-bucket"
    echo "[$(date)] Uploading to AWS S3: $S3_BUCKET"
    aws s3 cp "$FILEPATH" "$S3_BUCKET/" --storage-class STANDARD_IA
    echo "[$(date)] S3 Upload completed."
else
    echo "[$(date)] WARNING: AWS CLI not found. Skipping offsite backup."
fi

# Retention Policy: Delete local backups older than 7 days to prevent disk exhaustion
echo "[$(date)] Enforcing 7-day local retention policy..."
find "$BACKUP_DIR" -type f -name "fastpos_backup_*.sql.gz" -mtime +7 -exec rm {} \;

echo "[$(date)] Backup workflow finished."
