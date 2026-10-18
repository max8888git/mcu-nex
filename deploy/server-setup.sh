#!/usr/bin/env bash
# One-time setup of an Ubuntu 24.04 server for MCU-NEX (Azure for Students or Oracle Cloud).
# Installs Nginx, PHP 8.4 (FPM), MariaDB and Certbot, opens the web ports, creates the
# database and the app folder. Run it once, on the server, as the login user
# (azureuser on Azure, ubuntu on Oracle):
#
#   bash server-setup.sh mcu-nex.eastasia.cloudapp.azure.com
#
# Safe to run again: it skips what is already done.
# See docs/deploy-azure.md (or docs/deploy-oracle-cloud.md).
set -euo pipefail

DOMAIN="${1:-}"
if [ -z "$DOMAIN" ]; then
  echo "Usage: bash server-setup.sh your-domain (for example mcu-nex.eastasia.cloudapp.azure.com)" >&2
  exit 1
fi
APP_DIR=/var/www/mcu-nex
DB_NAME=mcu_nex
DB_USER=mcu_nex
LOGIN_USER="$(id -un)"   # the user that GitHub deploys as

echo "== Updating the system"
sudo apt-get update -y
sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -y

echo "== Swap (helps the small 1 GB server)"
if ! swapon --show | grep -q /swapfile; then
  sudo fallocate -l 2G /swapfile
  sudo chmod 600 /swapfile
  sudo mkswap /swapfile
  sudo swapon /swapfile
  echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab > /dev/null
fi

echo "== Nginx, MariaDB, Certbot, tools"
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y software-properties-common nginx mariadb-server \
  certbot python3-certbot-nginx rsync unzip netfilter-persistent iptables-persistent

echo "== PHP 8.4 (the same version as the development PCs)"
if ! command -v php8.4 > /dev/null; then
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt-get update -y
fi
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath
# photo uploads (My account) and the CSV/Excel imports
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 16M/; s/^post_max_size = .*/post_max_size = 20M/' /etc/php/8.4/fpm/php.ini
# the login user deploys the files; PHP runs as www-data. They share the www-data group.
sudo usermod -aG www-data "$LOGIN_USER"
sudo systemctl enable --now php8.4-fpm nginx mariadb

echo "== Opening ports 80 and 443 on the server's own firewall (Oracle's image blocks them; harmless elsewhere)"
for port in 80 443; do
  if ! sudo iptables -C INPUT -p tcp --dport "$port" -m state --state NEW -j ACCEPT 2> /dev/null; then
    sudo iptables -I INPUT 1 -p tcp --dport "$port" -m state --state NEW -j ACCEPT
  fi
done
sudo netfilter-persistent save

echo "== Database"
DB_PASS_FILE="$HOME/.mcu-nex-db-password"
if [ ! -f "$DB_PASS_FILE" ]; then
  openssl rand -base64 24 | tr -d '/+=' > "$DB_PASS_FILE"
  chmod 600 "$DB_PASS_FILE"
fi
DB_PASS="$(cat "$DB_PASS_FILE")"
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "== App folder"
sudo mkdir -p "$APP_DIR"
sudo chown "$LOGIN_USER":www-data "$APP_DIR"
sudo chmod 2775 "$APP_DIR"
mkdir -p "$APP_DIR"/storage/{app/public,app/private,framework/cache/data,framework/sessions,framework/views,framework/testing,logs} "$APP_DIR"/bootstrap/cache
sudo chown -R "$LOGIN_USER":www-data "$APP_DIR"/storage "$APP_DIR"/bootstrap/cache
sudo chmod -R 2775 "$APP_DIR"/storage "$APP_DIR"/bootstrap/cache

echo "== Nginx site"
sudo tee /etc/nginx/sites-available/mcu-nex > /dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # the API's /api/*.php addresses are Laravel routes (routes/api.php); no .php file
    # other than index.php is ever run directly, and no hidden file (.env, .git) is served
    location ~ \.php$ { rewrite ^ /index.php last; }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
sudo ln -sf /etc/nginx/sites-available/mcu-nex /etc/nginx/sites-enabled/mcu-nex
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx

echo
echo "Done. Log out and back in, then carry on with the guide (docs/deploy-azure.md, step 7)."
echo "Database:  name $DB_NAME, user $DB_USER, password saved in $DB_PASS_FILE"
echo "           (put it in $APP_DIR/.env as DB_PASSWORD; see deploy/env.production.example)"
