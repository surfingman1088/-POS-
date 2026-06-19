#!/bin/bash
# ================================================================
# YO 團購 POS 系統 - 新增分店腳本
# 使用方式：sudo bash add-branch.sh
# ================================================================

set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[資訊]${NC} $1"; }
success() { echo -e "${GREEN}[完成]${NC} $1"; }
error()   { echo -e "${RED}[錯誤]${NC} $1"; exit 1; }

PHP_VER="8.3"
APP_DIR="/var/www"
DEPLOY_INFO="/root/yo-pos-deploy-info.txt"
BRANCHES_CONF="/opt/yo-pos/branches/branches.conf"

# 讀取資料庫密碼
DB_USER="pos_user"
DB_PASS=$(grep "POS 資料庫密碼:" "$DEPLOY_INFO" | awk '{print $NF}')
DB_ROOT_PASS=$(grep "MySQL Root 密碼:" "$DEPLOY_INFO" | awk '{print $NF}')
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║        YO 團購 POS - 新增分店                        ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# 取得目前最大埠號
LAST_PORT=$(grep -v '^#' "$BRANCHES_CONF" | awk -F'|' '{print $5}' | sort -n | tail -1)
NEXT_PORT=$((LAST_PORT + 1))

# 互動式輸入
read -p "請輸入分店 ID（英文，例如：zhongli）: " BRANCH_ID
read -p "請輸入分店名稱（例如：中壢店）: " BRANCH_NAME
read -p "請輸入分店副名稱（例如：YO 團購中壢店）: " BRANCH_NAME_ALT
read -p "請輸入分店地址（例如：桃園市中壢區）: " BRANCH_ADDRESS

# 自動分配埠號和資料庫名
BRANCH_PORT=$NEXT_PORT
DB_NAME="pos_${BRANCH_ID}"
BRANCH_DIR="${APP_DIR}/pos-${BRANCH_ID}"

echo ""
info "新分店設定："
echo "  分店 ID：$BRANCH_ID"
echo "  分店名稱：$BRANCH_NAME"
echo "  副名稱：$BRANCH_NAME_ALT"
echo "  地址：$BRANCH_ADDRESS"
echo "  埠號：$BRANCH_PORT"
echo "  資料庫：$DB_NAME"
echo ""
read -p "確認新增？(y/N): " CONFIRM
[[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]] && echo "已取消" && exit 0

# 複製代碼
info "複製代碼..."
cp -r /tmp/pos-source "$BRANCH_DIR" 2>/dev/null || cp -r "${APP_DIR}/pos-bade" "$BRANCH_DIR"
cd "$BRANCH_DIR"

# 建立資料庫
info "建立資料庫..."
mysql -u root -p"$DB_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p"$DB_ROOT_PASS" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -u root -p"$DB_ROOT_PASS" -e "FLUSH PRIVILEGES;"

# 建立 .env
APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
cat > "${BRANCH_DIR}/.env" << ENVEOF
APP_NAME="YO 團購 POS 系統"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://${SERVER_IP}:${BRANCH_PORT}
APP_LOCALE=zh
APP_FALLBACK_LOCALE=zh
APP_FAKER_LOCALE=zh_TW
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
VITE_APP_NAME="YO 團購 POS"
STORE_NAME="${BRANCH_NAME}"
STORE_NAME_ALT="${BRANCH_NAME_ALT}"
STORE_ADDRESS="${BRANCH_ADDRESS}"
STORE_DEFAULT_ORDER_TYPE="deliver"
STORE_DEFAULT_PAYMENT_TYPE="cash"
ORDER_EDIT_LOCK_STATUS="preparing,in_transit,delivered"
STORE_OPEN_HOUR=7
STORE_CLOSE_HOUR=22
STORE_OPEN_DAYS="1,2,3,4,5,6,7"
OTHER_PAYMENT_TYPES="轉帳,Line Pay,街口支付"
SESSION_EXPIRE_DAYS=30
ENVEOF

# 安裝依賴
info "安裝依賴..."
composer install --no-interaction --optimize-autoloader --no-dev -q
npm install --silent && npm run build

# 執行 Migration
php artisan migrate --force -q
php artisan db:seed --force -q
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 更新管理員語言
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE users SET lang='zh' WHERE username='admin';"

# 設定權限
chown -R www-data:www-data "$BRANCH_DIR"
chmod -R 755 "$BRANCH_DIR"
chmod -R 775 "${BRANCH_DIR}/storage" "${BRANCH_DIR}/bootstrap/cache"

# 建立 PHP-FPM 池
cat > "/etc/php/${PHP_VER}/fpm/pool.d/pos-${BRANCH_ID}.conf" << FPMEOF
[pos-${BRANCH_ID}]
user = www-data
group = www-data
listen = /run/php/php${PHP_VER}-fpm-pos-${BRANCH_ID}.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
FPMEOF

# 建立 Nginx 設定
cat > "/etc/nginx/sites-available/pos-${BRANCH_ID}" << NGINXEOF
server {
    listen ${BRANCH_PORT};
    server_name _;
    root ${BRANCH_DIR}/public;
    index index.php;
    charset utf-8;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm-pos-${BRANCH_ID}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    location ~ /\.(?!well-known).* { deny all; }
    access_log /var/log/nginx/pos-${BRANCH_ID}-access.log;
    error_log  /var/log/nginx/pos-${BRANCH_ID}-error.log;
}
NGINXEOF

ln -sf "/etc/nginx/sites-available/pos-${BRANCH_ID}" "/etc/nginx/sites-enabled/pos-${BRANCH_ID}"

# 開放防火牆
ufw allow "$BRANCH_PORT"/tcp 2>/dev/null || iptables -I INPUT -p tcp --dport "$BRANCH_PORT" -j ACCEPT

# 重啟服務
systemctl restart "php${PHP_VER}-fpm"
systemctl reload nginx

# 更新設定檔
echo "${BRANCH_ID}|${BRANCH_NAME}|${BRANCH_NAME_ALT}|${BRANCH_ADDRESS}|${BRANCH_PORT}|${DB_NAME}" >> "$BRANCHES_CONF"

# 更新部署資訊
echo "  ${BRANCH_NAME}: http://${SERVER_IP}:${BRANCH_PORT}" >> "$DEPLOY_INFO"
echo "    帳號: admin | 密碼: 123（請登入後立即修改）" >> "$DEPLOY_INFO"

echo ""
success "✅ ${BRANCH_NAME} 新增完成！"
echo ""
echo -e "  網址：${GREEN}http://${SERVER_IP}:${BRANCH_PORT}${NC}"
echo -e "  帳號：admin | 密碼：123"
echo ""
