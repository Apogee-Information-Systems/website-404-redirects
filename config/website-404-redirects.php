<?php

return [

    'enabled' => env('WEBSITE_404_REDIRECTS_ENABLED', true),

    'table' => 'website_404_redirects',

    'normalize_lowercase' => true,

    'max_path_length' => 512,

    'default_redirect_status' => 301,

    'redirect_methods' => ['GET', 'HEAD'],

    'log_methods' => ['GET', 'HEAD'],

    /*
    |--------------------------------------------------------------------------
    | Log 404s when a route matched but the controller returned 404 (Str::is)
    |--------------------------------------------------------------------------
    |
    | Route-not-found URLs are always logged. Additionally log controller abort(404)
    | when the path matches (e.g. missing blog post at /blog/{slug}).
    |
    */
    'log_matched_route_patterns' => [
        'blog',
        'blog/*',
        'features',
        'features/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded paths (Str::is patterns, no leading slash)
    |--------------------------------------------------------------------------
    |
    | Homepage `/` is checked as an empty string after ltrim — use '' to exclude it.
    */
    'exclude_patterns' => [
        '',
        'admin',
        'admin/*',
        'api/*',
        'up',
        '*.css',
        '*.js',
        '*.map',
        '*.ico',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.gif',
        '*.webp',
        '*.svg',
        '*.woff',
        '*.woff2',
        '*.ttf',
        '*.eot',
        '_debugbar/*',
        'livewire/*',
    ],

    'allow_external_redirects' => false,

    'allowed_external_hosts' => [],

    'cache' => [
        'store' => env('WEBSITE_404_REDIRECTS_CACHE_STORE'),
        'key' => 'website_404_redirects:active',
        'ttl' => 3600,
    ],

];
