#!/bin/bash
# ============================================================
#  YO 團購 POS 系統 - 新增分店腳本
#  使用方式：bash add-branch.sh <分店ID> <分店名稱> <埠號>
#  範例：bash add-branch.sh zhongli 中壢店 8007
# ============================================================
set -e

BRANCH_ID="$1"
BRANCH_NAME="$2"
PORT="$3"

if [ -z "$BRANCH_ID" ] || [ -z "$BRANCH_NAME" ] || [ -z "$PORT" ]; then
    echo "使用方式：bash add-branch.sh <分店ID> <分店名稱> <埠號>"
    echo "範例：    bash add-branch.sh zhongli 中壢店 8007"
    echo ""
    echo "注意事項："
    echo "  - 分店ID 只能使用英文小寫字母，例如：zhongli、taoyuan"
    echo "  - 埠號 建議從 8007 開始往上加"
    exit 1
fi

MAIN_REPO="/opt/yo-pos-source"
APP_BASE_DIR="/var/www"
MYSQL_ROOT_PASS="YoPOS2026Secure!"
DB_NAME="pos_${BRANCH_ID}"
DB_USER="pos_${BRANCH_ID}"
DB_PASS="YoPOS_${BRANCH_ID}_2026"
APP_DIR="${APP_BASE_DIR}/pos-${BRANCH_ID}"
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo "=========================================="
echo "  新增分店：${BRANCH_NAME}"
echo "  埠號：${PORT}"
echo "  伺服器 IP：${SERVER_IP}"
echo "=========================================="

# 檢查埠號是否已被使用
if ss -tlnp | grep -q ":${PORT} "; then
    echo "錯誤：埠號 ${PORT} 已被使用，請選擇其他埠號"
    exit 1
fi

# 建立資料庫
echo "[1/5] 建立資料庫..."
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "
    CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
    GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
    FLUSH PRIVILEGES;
" 2>/dev/null
echo "  ✓ 資料庫 ${DB_NAME} 建立完成"

# 複製代碼
echo "[2/5] 複製系統代碼..."
rm -rf "$APP_DIR"
cp -r "$MAIN_REPO" "$APP_DIR"
echo "  ✓ 代碼複製完成"

# 建立 .env
echo "[3/5] 設定環境變數..."
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
echo "  ✓ 環境設定完成"

# Laravel 初始化
echo "[4/5] 初始化系統..."
cd "$APP_DIR"
php artisan key:generate --force 2>&1 | tail -1
php artisan migrate --force 2>&1 | tail -3
php artisan db:seed --force 2>&1 | tail -2
php artisan storage:link --force 2>&1 | tail -1
php artisan config:cache 2>&1 | tail -1
php artisan route:cache 2>&1 | tail -1
php artisan view:cache 2>&1 | tail -1
echo "  ✓ 系統初始化完成"

# Nginx 設定
echo "[5/5] 設定 Nginx..."
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
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
ufw allow ${PORT}/tcp 2>/dev/null || true
nginx -t && systemctl reload nginx
echo "  ✓ Nginx 設定完成"

echo ""
echo "=========================================="
echo "  🎉 ${BRANCH_NAME} 新增完成！"
echo "=========================================="
echo ""
echo "  網址：http://${SERVER_IP}:${PORT}"
echo "  帳號：admin"
echo "  密碼：123"
echo ""
echo "  請立即登入並修改預設密碼！"
