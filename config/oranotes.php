<?php

return [
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    'ora_editor_version' => '0.1.3',

    'update' => [
        'repository' => env('ORANOTES_UPDATE_REPO', 'tallyhome/OraNotes'),
        'api' => env('ORANOTES_UPDATE_API', 'https://api.github.com'),
        'allow_prerelease' => env('ORANOTES_UPDATE_PRERELEASE', false),
        'min_php' => '8.3.0',
        'timeout' => 20,
    ],

    'install' => [
        'lock' => 'installed.lock',
    ],
];
