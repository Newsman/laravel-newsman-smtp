<?php

return [
    'api_key'      => env('NEWSMAN_API_KEY', ''),
    'account_id'   => env('NEWSMAN_ACCOUNT_ID', ''),
    'endpoint'     => env('NEWSMAN_ENDPOINT', 'https://cluster.newsmanapp.com/api/1.0/message.send_raw'),

    // adding here default
    'from_address' => env('NEWSMAN_FROM_ADDRESS', 'no-reply@example.com'),
    'from_name'    => env('NEWSMAN_FROM_NAME', 'Laravel App'),

    'http' => [
        'timeout' => 15,
        'retry'   => [ 'times' => 2, 'sleep' => 200 ],
    ],
    'log' => [
        'enabled'   => true,
        'channel'   => 'newsman', // write to storage/logs/newsman.log
        'level'     => 'info',
        'redact'    => ['api_key', 'mime_message'], // hidden fields in log
        'requests'  => true,
        'responses' => true,
    ],
];
