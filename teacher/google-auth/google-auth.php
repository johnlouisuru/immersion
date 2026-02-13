<?php 

// Generate Google OAuth URL
$google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $_ENV['GOOGLE_CLIENT_ID'],
    'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'],
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
]);