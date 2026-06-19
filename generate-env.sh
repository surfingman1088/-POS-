#!/bin/bash
# ============================================================
# YO 團購 POS - 分店 .env 設定檔生成腳本
# 用法: ./generate-env.sh <branch_id>
# ============================================================

BRANCH_ID=$1
CONF_FILE="$(dirname "$0")/../branches/branches.conf"

if [ -z "$BRANCH_ID" ]; then
    echo "用法: $0 <branch_id>"
    echo "可用分店:"
    grep -v '^#' "$CONF_FILE" | awk -F'|' '{print "  " $1 " - " $2}'
    exit 1
fi

# 讀取分店設定
BRANCH_LINE=$(grep -v '^#' "$CONF_FILE" | grep "^${BRANCH_ID}|")
if [ -z "$BRANCH_LINE" ]; then
    echo "錯誤：找不到分店 '$BRANCH_ID'"
    exit 1
fi

BRANCH_NAME=$(echo "$BRANCH_LINE" | awk -F'|' '{print $2}')
BRANCH_NAME_ALT=$(echo "$BRANCH_LINE" | awk -F'|' '{print $3}')
BRANCH_ADDRESS=$(echo "$BRANCH_LINE" | awk -F'|' '{print $4}')
BRANCH_PORT=$(echo "$BRANCH_LINE" | awk -F'|' '{print $5}')
DB_NAME=$(echo "$BRANCH_LINE" | awk -F'|' '{print $6}')
APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")

cat > "/var/www/pos-${BRANCH_ID}/.env" << EOF
APP_NAME="YO 團購 POS 系統"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://localhost:${BRANCH_PORT}
APP_LOCALE=zh
APP_FALLBACK_LOCALE=zh
APP_FAKER_LOCALE=zh_TW
APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=pos_user
DB_PASSWORD=\${DB_PASSWORD}
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
VITE_APP_NAME="YO 團購 POS"
# 分店設定
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
EOF

echo "✅ ${BRANCH_NAME} (.env) 設定完成"
