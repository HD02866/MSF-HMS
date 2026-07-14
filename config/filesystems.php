<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
        // Direct public disk — no symlink needed, works on Windows
        'patient_photos' => [
            'driver'     => 'local',
            'root'       => public_path('images/patients'),
            'url'        => env('APP_URL').'/images/patients',
            'visibility' => 'public',
            'throw'      => true,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
