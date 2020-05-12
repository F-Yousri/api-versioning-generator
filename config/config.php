<?php

return [
     // Paths were the files will be generated
    'paths' => [
        'routes' => app_path('../routes/API'),
        'controllers' => app_path('Http/Controllers'),
        'requests' => app_path('Http/Requests'),
        'transformers' => app_path('Transformers'),
        'routeServiceProvider' => app_path('Providers')
    ],
    'module-paths' => [
        'routes' => '/Routes/API',
        'controllers' => '/Http/Controllers',
        'requests' => '/Http/Requests',
        'transformers' => '/Transformers',
        'routeServiceProvider' => '/Providers'
    ]
];