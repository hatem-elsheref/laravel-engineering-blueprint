<?php

return [
    'profile' => null, // set by php artisan engineering:install
    'module_path' => 'app/Modules',
    'shared_path' => 'app/Shared',
    'stub_path' => null, // null => package stubs; non-null => project override dir
    'default_profile' => 'modular',
    'support' => [
        'php' => '8.3',   // minimum
        'laravel' => '12', // minimum major
    ],
];
