<?php

return [
    'module_path' => 'app/Modules',
    'shared_path' => 'app/Shared',
    'stub_path' => null, // null => package stubs; non-null => project override dir
    'profiles' => ['basic', 'modular', 'saas', 'tenant-saas'],
    'default_profile' => 'modular',
    'support' => [
        'php' => '8.3',   // minimum
        'laravel' => '12', // minimum major
    ],
];
