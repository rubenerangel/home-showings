<?php

use NotificationChannels\WebPush\PushSubscription;

return [

    // Generated on the first start (docker/entrypoint.sh). The subject is generic on purpose.
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:push@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => null,
    ],

    'model' => PushSubscription::class,

    'table_name' => 'push_subscriptions',

    'database_connection' => env('DB_CONNECTION', 'pgsql'),

    // The endpoints are checked when they are saved. A redirect would lead somewhere else.
    'client_options' => ['allow_redirects' => false],

    'automatic_padding' => true,

];
