<?php
return [
    'secret' => env('jwt.secret', ''),
    'issuer' => env('jwt.issuer', 'dnsmgr'),
    'ttl' => env('jwt.ttl', 7200), // 2 hours
    'refresh_ttl' => env('jwt.refresh_ttl', 604800), // 7 days
    'algo' => 'HS256',
    'leeway' => 60,
    'header' => 'Authorization',
    'prefix' => 'Bearer',
];
