#!/bin/bash
# ============================================================
#  YO 團購 POS 系統 - VPS 一鍵全新安裝腳本
#  適用系統：Ubuntu 22.04 / 24.04 / 26.04 LTS
#  使用方式：bash install.sh <SERVER_IP>
# ============================================================
set -e

SERVER_IP="${1:-$(curl -s ifconfig.me)}"
REPO_URL="https://github.com/surfingman1088/-POS-.git"
MAIN_REPO="/opt/yo-pos-source"
APP_BASE_DIR="/var/www"
MYSQL_ROOT_PASS="YoPOS2026Secure!"
INFO_FILE="/root/yo-pos-info.txt"

# 分店列表：ID|名稱|埠號
BRANCHES=(
    "bade|八德店|8001"
    "sanxia|三峽店|8002"
    "dazhu|大竹店|8003"
    "linkou|林口店|8004"
    "yiwen|藝文店|8005"
    "guolin|菓林店|8006"
)

echo "=========================================="
echo "  YO 團購 POS 系統 - 全自動安裝腳本"
echo "  伺服器 IP: ${SERVER_IP}"
echo "=========================================="

# ---- 偵測 Ubuntu 版本並安裝 PHP ----
UBUNTU_CODENAME=$(lsb_release -cs 2>/dev/null || echo "unknown")
echo "[1/6] 安裝系統依賴（Ubuntu: ${UBUNTU_CODENAME}）..."

apt-get update -qq

# 移除可能殘留的失敗 PPA
rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list 2>/dev/null || true
apt-get update -qq 2>/dev/null || true

# 偵測可用的 PHP 版本
if apt-cache show php8.5-fpm &>/dev/null; then
    PHP_VER="8.5"
elif apt-cache show php8.3-fpm &>/dev/null; then
    PHP_VER="8.3"
elif apt-cache show php8.2-fpm &>/dev/null; then
    PHP_VER="8.2"
else
    # 嘗試加入 Ondrej PPA（適用於 Ubuntu 22.04/24.04）
    add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
    apt-get update -qq
    PHP_VER="8.3"
fi

echo "  使用 PHP ${PHP_VER}"

apt-get install -y \
    php${PHP_VER} php${PHP_VER}-fpm php${PHP_VER}-mysql php${PHP_VER}-mbstring \
    php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-zip php${PHP_VER}-bcmath \
    php${PHP_VER}-gd php${PHP_VER}-intl php${PHP_VER}-dom \
    2>&1 | tail -5

# 設定 php 指令指向正確版本
update-alternatives --set php /usr/bin/php${PHP_VER} 2>/dev/null || true
echo "  ✓ PHP ${PHP_VER} 安裝完成"

# ---- 安裝 MySQL ----
echo "[2/6] 安裝 MySQL..."
DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server 2>&1 | tail -3
systemctl start mysql && systemctl enable mysql
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASS}'; FLUSH PRIVILEGES;" 2>/dev/null || true
echo "  ✓ MySQL 安裝完成"

# ---- 安裝 Nginx ----
echo "[3/6] 安裝 Nginx..."
apt-get install -y nginx 2>&1 | tail -3
systemctl start nginx && systemctl enable nginx
echo "  ✓ Nginx 安裝完成"

# ---- 安裝 Composer ----
echo "[4/6] 安裝 Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>&1 | tail -2
echo "  ✓ Composer 安裝完成"

# ---- 安裝 Node.js ----
echo "[5/6] 安裝 Node.js..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - 2>/dev/null | tail -3
apt-get install -y nodejs 2>&1 | tail -3
echo "  ✓ Node.js 安裝完成"

# ---- 克隆代碼並安裝依賴 ----
echo "[6/6] 下載 POS 系統代碼..."
if [ -d "$MAIN_REPO" ]; then
    cd "$MAIN_REPO" && git pull 2>&1 | tail -3
else
    git clone "$REPO_URL" "$MAIN_REPO" 2>&1 | tail -3
fi

cd "$MAIN_REPO"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
npm install 2>&1 | tail -3
npm run build 2>&1 | tail -5
echo "  ✓ 代碼與依賴安裝完成"

# ---- 部署各分店 ----
echo ""
echo "開始部署各分店..."

echo "========================================" > $INFO_FILE
echo "  YO 團購 POS 系統 - 分店存取資訊" >> $INFO_FILE
echo "  部署時間: $(date '+%Y-%m-%d %H:%M:%S')" >> $INFO_FILE
echo "========================================" >> $INFO_FILE
echo "" >> $INFO_FILE

for BRANCH_INFO in "${BRANCHES[@]}"; do
    IFS='|' read -r BRANCH_ID BRANCH_NAME PORT <<< "$BRANCH_INFO"
    DB_NAME="pos_${BRANCH_ID}"
    DB_USER="pos_${BRANCH_ID}"
    DB_PASS="YoPOS_${BRANCH_ID}_2026"
    APP_DIR="${APP_BASE_DIR}/pos-${BRANCH_ID}"

    echo "  → 部署 ${BRANCH_NAME}（Port ${PORT}）..."

    # 建立資料庫
    mysql -u root -p"${MYSQL_ROOT_PASS}" -e "
        CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
        FLUSH PRIVILEGES;
    " 2>/dev/null

    # 複製代碼
    rm -rf "$APP_DIR"
    cp -r "$MAIN_REPO" "$APP_DIR"

    # 建立 .env
    cat > "${APP_DIR}/.env" << ENVEOF
APP_NAME="YO 團購 POS - ${BRANCH_NAME}"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://${SERVER_IP}:${PORT}
APP_LOCALE=zh
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=zh_TW
LOG_CHANNEL=stack
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
ENVEOF

    # 設定權限
    chown -R www-data:www-data "$APP_DIR"
    chmod -R 755 "$APP_DIR"
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

    # Laravel 初始化
    cd "$APP_DIR"
    php artisan key:generate --force 2>&1 | tail -1
    php artisan migrate --force 2>&1 | tail -3
    php artisan db:seed --force 2>&1 | tail -2
    php artisan storage:link --force 2>&1 | tail -1
    php artisan config:cache 2>&1 | tail -1
    php artisan route:cache 2>&1 | tail -1
    php artisan view:cache 2>&1 | tail -1

    # Nginx 設定
    PHP_FPM_SOCK="/var/run/php/php${PHP_VER}-fpm.sock"
    cat > "/etc/nginx/sites-available/pos-${BRANCH_ID}" << NGINXEOF
server {
    listen ${PORT};
    server_name _;
    root ${APP_DIR}/public;
    index index.php index.html;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    charset utf-8;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;
    location ~ \.php$ {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINXEOF

    ln -sf "/etc/nginx/sites-available/pos-${BRANCH_ID}" "/etc/nginx/sites-enabled/pos-${BRANCH_ID}"

    echo "【${BRANCH_NAME}】" >> $INFO_FILE
    echo "  網址: http://${SERVER_IP}:${PORT}" >> $INFO_FILE
    echo "  帳號: admin  密碼: 123" >> $INFO_FILE
    echo "" >> $INFO_FILE

    echo "  ✓ ${BRANCH_NAME} 完成"
done

# 開放防火牆
for PORT in 8001 8002 8003 8004 8005 8006; do
    ufw allow ${PORT}/tcp 2>/dev/null || true
done

# 移除預設 Nginx 設定並重啟
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
systemctl restart php${PHP_VER}-fpm

echo ""
echo "=========================================="
echo "  🎉 安裝完成！"
echo "=========================================="
cat $INFO_FILE
