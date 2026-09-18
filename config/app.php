<?php
declare(strict_types=1);

return [
    'mode' => getenv('APP_MODE') ?: 'demo',
    'env' => getenv('APP_ENV') ?: 'production',
    'url' => rtrim(getenv('APP_URL') ?: '', '/'),
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'idle_timeout_seconds' => (int)(getenv('SESSION_IDLE_TIMEOUT') ?: 3600),
        'absolute_timeout_seconds' => (int)(getenv('SESSION_ABSOLUTE_TIMEOUT') ?: 43200),
    ],
    'mapping' => [
        'users_table' => getenv('DB_USERS_TABLE') ?: 'users',
        'delegations_table' => getenv('DB_DELEGATIONS_TABLE') ?: 'delegations',
        'vehicles_table' => getenv('DB_VEHICLES_TABLE') ?: 'vehicles',
        'user_id' => getenv('DB_USER_ID_COLUMN') ?: 'id',
        'user_name' => getenv('DB_USER_NAME_COLUMN') ?: 'name',
        'delegation_id' => getenv('DB_DELEGATION_ID_COLUMN') ?: 'id',
        'delegation_user_id' => getenv('DB_DELEGATION_USER_ID_COLUMN') ?: 'user_id',
        'delegation_destination' => getenv('DB_DELEGATION_DESTINATION_COLUMN') ?: 'destination',
        'delegation_purpose' => getenv('DB_DELEGATION_PURPOSE_COLUMN') ?: 'purpose',
        'delegation_date_from' => getenv('DB_DELEGATION_DATE_FROM_COLUMN') ?: 'date_from',
        'delegation_date_to' => getenv('DB_DELEGATION_DATE_TO_COLUMN') ?: 'date_to',
        'delegation_status' => getenv('DB_DELEGATION_STATUS_COLUMN') ?: 'status',
    ],
];
