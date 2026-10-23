<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials File Path
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase Service Account JSON credentials file.
    | You can use an absolute path or a relative path from the project root.
    | Example: 'storage/app/firebase/firebase_credentials.json'
    |
    */
    'credentials_path' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/firebase_credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID (Optional)
    |--------------------------------------------------------------------------
    |
    | If not specified, it will be automatically extracted from the credentials JSON.
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Auto Push on Database Notification
    |--------------------------------------------------------------------------
    |
    | When enabled (true), whenever NotificationService::send() creates an
    | in-app database notification, it will also safely dispatch an FCM
    | push notification to the recipient user's active devices.
    |
    */
    'auto_push_notifications' => env('FIREBASE_AUTO_PUSH', true),

    /*
    |--------------------------------------------------------------------------
    | Default Push Notification Defaults
    |--------------------------------------------------------------------------
    |
    | Android channel ID, sound, and notification icon defaults.
    |
    */
    'defaults' => [
        'sound' => env('FIREBASE_DEFAULT_SOUND', 'default'),
        'channel_id' => env('FIREBASE_CHANNEL_ID', 'default_channel'),
        'icon' => env('FIREBASE_DEFAULT_ICON', '/assets/images/brand/logo-icon.png'),
    ],

];
