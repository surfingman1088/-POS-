<?php
return [
    'default_order_type'  => env('STORE_DEFAULT_ORDER_TYPE'),
    'default_payment_type' => env('STORE_DEFAULT_PAYMENT_TYPE'),
    'order_edit_lock_status' => explode(',', env('ORDER_EDIT_LOCK_STATUS')),
    'store_open_hour'     => env('STORE_OPEN_HOUR', 7),
    'store_close_hour'    => env('STORE_CLOSE_HOUR', 20),
    'store_open_days'     => explode(',', env('STORE_OPEN_DAYS', '1,2,3,4,5,6')),
    'other_payment_types' => explode(',', env('OTHER_PAYMENT_TYPES', 'gcash')),
    'session_expire_days' => env('SESSION_EXPIRE_DAYS', 30),

    // ── LINE 異常通知設定 ──────────────────────────────
    'line_channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN', ''),
    'line_owner_user_id'        => env('LINE_OWNER_USER_ID', ''),
    'store_name'                => env('STORE_NAME', env('APP_NAME', 'YoPOS')),
];
