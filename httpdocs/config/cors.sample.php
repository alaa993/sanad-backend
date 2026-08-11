<?php
return [
  'paths' => ['api/*', 'sanctum/csrf-cookie'],
  'allowed_methods' => ['*'],
  'allowed_origins' => ['https://your-domain.com','http://localhost'],
  'allowed_headers' => ['*'],
  'supports_credentials' => false,
];
