#!/bin/bash
set -e

mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld

if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "Initializing MariaDB data directory..."
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

chown -R mysql:mysql /var/lib/mysql
chmod 755 /var/lib/mysql

# Persist TP Planner uploads inside the mounted /var/lib/mysql volume
mkdir -p /var/lib/mysql/tp_uploads/tp_documents /var/lib/mysql/tp_uploads/tp_schemas
if [ -d "/var/www/tp-planner/uploads_seed" ]; then
    cp -rn /var/www/tp-planner/uploads_seed/* /var/lib/mysql/tp_uploads/ 2>/dev/null || true
fi
rm -rf /var/www/tp-planner/uploads
ln -s /var/lib/mysql/tp_uploads /var/www/tp-planner/uploads
chown -R www-data:www-data /var/lib/mysql/tp_uploads

echo "Starting MariaDB..."
mysqld_safe --datadir=/var/lib/mysql --bind-address=127.0.0.1 --port=3306 &

for i in $(seq 1 30); do
    if mysqladmin ping --silent >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

mysql -u root << 'SQL'
CREATE DATABASE IF NOT EXISTS `tp_planner` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'tp_planner'@'localhost' IDENTIFIED BY 'tp_planner';
CREATE USER IF NOT EXISTS 'tp_planner'@'127.0.0.1' IDENTIFIED BY 'tp_planner';
GRANT ALL PRIVILEGES ON `tp_planner`.* TO 'tp_planner'@'localhost';
GRANT ALL PRIVILEGES ON `tp_planner`.* TO 'tp_planner'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

TABLE_COUNT=$(mysql -u root -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='tp_planner' AND table_name='users';")
if [ "$TABLE_COUNT" -eq "0" ] && [ -f "/docker-entrypoint-initdb.d/mysql_backup.sql" ]; then
    echo "Importing initial tp_planner database from mysql_backup.sql..."
    mysql -u root tp_planner < /docker-entrypoint-initdb.d/mysql_backup.sql
    echo "Database import complete."
fi

echo "Starting Apache..."
exec apache2-foreground
