#!/bin/bash
# ================================================================
# YO 團購 POS 系統 - Oracle Cloud VPS 一鍵部署腳本
# 支援：Ubuntu 22.04 / 24.04
# 功能：自動安裝環境、部署所有分店、設定 Nginx
# 使用方式：sudo bash deploy-vps.sh
# ================================================================

set -e

# ── 顏色輸出 ──────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[資訊]${NC} $1"; }
success() { echo -e "${GREEN}[完成]${NC} $1"; }
warn()    { echo -e "${YELLOW}[警告]${NC} $1"; }
error()   { echo -e "${RED}[錯誤]${NC} $1"; exit 1; }

# ── 設定變數 ──────────────────────────────────────────────────
GITHUB_REPO="https://github.com/surfingman1088/-POS-.git"
APP_DIR="/var/www"
DB_ROOT_PASS="YoPOS_Root_$(openssl rand -hex 8)"
DB_USER="pos_user"
DB_PASS="YoPOS_$(openssl rand -hex 12)"
PHP_VER="8.3"
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

# ── 分店列表 ──────────────────────────────────────────────────
declare -A BRANCHES
# 格式: [id]="名稱|副名稱|地址|埠號|資料庫名"
BRANCHES[bade]="八德店|YO 團購八德店|桃園市八德區|8001|pos_bade"
BRANCHES[sanxia]="三峽店|YO 團購三峽店|新北市三峽區|8002|pos_sanxia"
BRANCHES[dazhu]="大竹店|YO 團購大竹店|桃園市蘆竹區大竹|8003|pos_dazhu"
BRANCHES[linkou]="林口店|YO 團購林口店|新北市林口區|8004|pos_linkou"
BRANCHES[yiwen]="藝文店|YO 團購藝文店|桃園市中壢區藝文|8005|pos_yiwen"
BRANCHES[guolin]="菓林店|YO 團購菓林店|桃園市龜山區|8006|pos_guolin"

BRANCH_ORDER=(bade sanxia dazhu linkou yiwen guolin)

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║        YO 團購 POS 系統 - 自動部署程式               ║"
echo "║        版本 1.0 | 支援 6 間分店 + 可擴充             ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# ── 步驟 1：更新系統 ──────────────────────────────────────────
info "步驟 1/8：更新系統套件..."
apt-get update -qq && apt-get upgrade -y -qq
success "系統更新完成"

# ── 步驟 2：安裝 PHP 8.3 ──────────────────────────────────────
info "步驟 2/8：安裝 PHP ${PHP_VER} 及擴展..."
apt-get install -y -qq software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y -qq \
    php${PHP_VER} php${PHP_VER}-fpm php${PHP_VER}-mysql \
    php${PHP_VER}-xml php${PHP_VER}-mbstring php${PHP_VER}-curl \
    php${PHP_VER}-zip php${PHP_VER}-bcmath php${PHP_VER}-tokenizer \
    php${PHP_VER}-sqlite3 php${PHP_VER}-intl php${PHP_VER}-gd \
    php${PHP_VER}-redis
success "PHP ${PHP_VER} 安裝完成"

# ── 步驟 3：安裝 MySQL ────────────────────────────────────────
info "步驟 3/8：安裝 MySQL 8.0..."
apt-get install -y -qq mysql-server
systemctl enable mysql
systemctl start mysql

# 設定 MySQL root 密碼
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASS}';" 2>/dev/null || true
mysql -u root -p"${DB_ROOT_PASS}" -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
mysql -u root -p"${DB_ROOT_PASS}" -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
mysql -u root -p"${DB_ROOT_PASS}" -e "FLUSH PRIVILEGES;" 2>/dev/null || true

# 建立 POS 資料庫使用者
mysql -u root -p"${DB_ROOT_PASS}" -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null || true
success "MySQL 安裝完成"

# ── 步驟 4：安裝 Nginx ────────────────────────────────────────
info "步驟 4/8：安裝 Nginx..."
apt-get install -y -qq nginx
systemctl enable nginx
success "Nginx 安裝完成"

# ── 步驟 5：安裝 Composer 和 Node.js ─────────────────────────
info "步驟 5/8：安裝 Composer 和 Node.js..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y -qq nodejs
success "Composer 和 Node.js 安裝完成"

# ── 步驟 6：安裝 Git 並克隆代碼 ──────────────────────────────
info "步驟 6/8：克隆 POS 系統代碼..."
apt-get install -y -qq git opencc

# 克隆主代碼庫（只克隆一次，其他分店用複製）
if [ ! -d "/tmp/pos-source" ]; then
    git clone "$GITHUB_REPO" /tmp/pos-source
    # 轉換繁體中文
    opencc -i /tmp/pos-source/lang/zh.json -o /tmp/pos-source/lang/zh.json.tw -c s2twp.json
    mv /tmp/pos-source/lang/zh.json.tw /tmp/pos-source/lang/zh.json
    # 更新預設語言
    sed -i "s/->default('en')/->default('zh')/" /tmp/pos-source/database/migrations/0001_01_01_000000_create_users_table.php
fi
success "代碼克隆完成"

# ── 步驟 7：部署各分店 ────────────────────────────────────────
info "步驟 7/8：部署 ${#BRANCHES[@]} 間分店..."

for BRANCH_ID in "${BRANCH_ORDER[@]}"; do
    IFS='|' read -r BNAME BNAME_ALT BADDRESS BPORT BDBNAME <<< "${BRANCHES[$BRANCH_ID]}"
    BRANCH_DIR="${APP_DIR}/pos-${BRANCH_ID}"

    info "  → 部署 ${BNAME}（埠 ${BPORT}）..."

    # 複製代碼
    cp -r /tmp/pos-source "$BRANCH_DIR"
    cd "$BRANCH_DIR"

    # 建立資料庫
    mysql -u root -p"${DB_ROOT_PASS}" -e "CREATE DATABASE IF NOT EXISTS ${BDBNAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root -p"${DB_ROOT_PASS}" -e "GRANT ALL PRIVILEGES ON ${BDBNAME}.* TO '${DB_USER}'@'localhost';"
    mysql -u root -p"${DB_ROOT_PASS}" -e "FLUSH PRIVILEGES;"

    # 生成 APP_KEY
    APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")

    # 建立 .env
    cat > "${BRANCH_DIR}/.env" << ENVEOF
APP_NAME="YO 團購 POS 系統"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://${SERVER_IP}:${BPORT}
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
DB_DATABASE=${BDBNAME}
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
STORE_NAME="${BNAME}"
STORE_NAME_ALT="${BNAME_ALT}"
STORE_ADDRESS="${BADDRESS}"
STORE_DEFAULT_ORDER_TYPE="deliver"
STORE_DEFAULT_PAYMENT_TYPE="cash"
ORDER_EDIT_LOCK_STATUS="preparing,in_transit,delivered"
STORE_OPEN_HOUR=7
STORE_CLOSE_HOUR=22
STORE_OPEN_DAYS="1,2,3,4,5,6,7"
OTHER_PAYMENT_TYPES="轉帳,Line Pay,街口支付"
SESSION_EXPIRE_DAYS=30
ENVEOF

    # 安裝 PHP 依賴
    composer install --no-interaction --optimize-autoloader --no-dev -q

    # 安裝並編譯前端
    npm install --silent
    npm run build

    # 執行 Migration 和 Seeder
    php artisan migrate --force -q
    php artisan db:seed --force -q
    php artisan storage:link
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # 更新管理員預設語言為繁體中文
    mysql -u "$DB_USER" -p"$DB_PASS" "$BDBNAME" -e "UPDATE users SET lang='zh' WHERE username='admin';"

    # 設定目錄權限
    chown -R www-data:www-data "$BRANCH_DIR"
    chmod -R 755 "$BRANCH_DIR"
    chmod -R 775 "${BRANCH_DIR}/storage" "${BRANCH_DIR}/bootstrap/cache"

    success "  ✅ ${BNAME} 部署完成"
done

# ── 步驟 8：設定 Nginx ────────────────────────────────────────
info "步驟 8/8：設定 Nginx 反向代理..."

for BRANCH_ID in "${BRANCH_ORDER[@]}"; do
    IFS='|' read -r BNAME BNAME_ALT BADDRESS BPORT BDBNAME <<< "${BRANCHES[$BRANCH_ID]}"
    BRANCH_DIR="${APP_DIR}/pos-${BRANCH_ID}"

    # 建立 PHP-FPM 池設定
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
chdir = /
env[APP_DIR] = ${BRANCH_DIR}
FPMEOF

    # 建立 Nginx 設定
    cat > "/etc/nginx/sites-available/pos-${BRANCH_ID}" << NGINXEOF
server {
    listen ${BPORT};
    server_name _;
    root ${BRANCH_DIR}/public;
    index index.php;
    charset utf-8;

    # 安全標頭
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    # 靜態資源快取
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

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # 日誌
    access_log /var/log/nginx/pos-${BRANCH_ID}-access.log;
    error_log  /var/log/nginx/pos-${BRANCH_ID}-error.log;
}
NGINXEOF

    ln -sf "/etc/nginx/sites-available/pos-${BRANCH_ID}" "/etc/nginx/sites-enabled/pos-${BRANCH_ID}"
done

# 移除預設 Nginx 設定
rm -f /etc/nginx/sites-enabled/default

# 重啟服務
systemctl restart "php${PHP_VER}-fpm"
systemctl reload nginx

# ── 開放防火牆埠號 ────────────────────────────────────────────
info "開放防火牆埠號..."
for PORT in 8001 8002 8003 8004 8005 8006; do
    ufw allow "$PORT"/tcp 2>/dev/null || iptables -I INPUT -p tcp --dport "$PORT" -j ACCEPT
done
ufw allow 80/tcp 2>/dev/null || true
ufw allow 443/tcp 2>/dev/null || true

# ── 儲存部署資訊 ──────────────────────────────────────────────
DEPLOY_INFO="/root/yo-pos-deploy-info.txt"
cat > "$DEPLOY_INFO" << INFOEOF
================================================================
YO 團購 POS 系統 - 部署資訊
部署時間: $(date '+%Y-%m-%d %H:%M:%S')
伺服器 IP: ${SERVER_IP}
================================================================

【資料庫資訊】（請妥善保管）
MySQL Root 密碼: ${DB_ROOT_PASS}
POS 資料庫使用者: ${DB_USER}
POS 資料庫密碼: ${DB_PASS}

【各分店網址】
INFOEOF

for BRANCH_ID in "${BRANCH_ORDER[@]}"; do
    IFS='|' read -r BNAME BNAME_ALT BADDRESS BPORT BDBNAME <<< "${BRANCHES[$BRANCH_ID]}"
    echo "  ${BNAME}: http://${SERVER_IP}:${BPORT}" >> "$DEPLOY_INFO"
    echo "    帳號: admin | 密碼: 123（請登入後立即修改）" >> "$DEPLOY_INFO"
done

cat >> "$DEPLOY_INFO" << INFOEOF

【新增分店指令】
  sudo bash /opt/yo-pos/scripts/add-branch.sh

【重啟所有分店】
  sudo systemctl restart php${PHP_VER}-fpm nginx

================================================================
INFOEOF

# ── 完成輸出 ──────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║           🎉 部署完成！                              ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}各分店網址：${NC}"
for BRANCH_ID in "${BRANCH_ORDER[@]}"; do
    IFS='|' read -r BNAME BNAME_ALT BADDRESS BPORT BDBNAME <<< "${BRANCHES[$BRANCH_ID]}"
    echo -e "  ${YELLOW}${BNAME}${NC}: http://${SERVER_IP}:${BPORT}"
done
echo ""
echo -e "${YELLOW}預設帳號：admin | 密碼：123（請登入後立即修改密碼！）${NC}"
echo ""
echo -e "完整部署資訊已儲存至：${GREEN}${DEPLOY_INFO}${NC}"
echo ""
