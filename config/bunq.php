<?php
declare(strict_types=1);

return [
    'version'         => '1.0.0-alpha.1',
    'access_token'    => env('FIREFLY_III_ACCESS_TOKEN', ''),
    'uri'             => env('FIREFLY_III_URI', ''),
    'api_code'        => env('BUNQ_API_CODE', ''),
    'api_uri'         => env('BUNQ_API_URI', ''),
    'minimum_version' => '5.0.5',
];
