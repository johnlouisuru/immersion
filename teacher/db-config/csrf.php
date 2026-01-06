<?php
// csrf.php

/**
 * Generate a CSRF token and store it in session with an expiry.
 *
 * @param string $session_key Session key to store token (default 'csrf')
 * @param int    $ttl Seconds until expiration (default 180 = 3 minutes)
 * @return string The token to embed in the form
 */
function generate_csrf_token(string $session_key = 'csrf', int $ttl = 180): string {
    // Create cryptographically secure random token (hex)
    $token = bin2hex(random_bytes(32)); // 64 hex chars, strong entropy

    // Store token and expiry in session
    $_SESSION[$session_key] = [
        'token'   => $token,
        'expires' => time() + $ttl,
    ];

    return $token;
}

/**
 * Validate a submitted CSRF token.
 *
 * @param string|null $submitted_token The token received from the form (e.g. $_POST['csrf_token'])
 * @param string      $session_key Session key where token is stored
 * @param bool        $single_use If true, will remove token on successful validation (default true)
 * @return bool True if token is valid and not expired
 */
function validate_csrf_token(?string $submitted_token, string $session_key = 'csrf', bool $single_use = true): bool {
    if (empty($submitted_token)) {
        return false;
    }

    if (empty($_SESSION[$session_key]) || !is_array($_SESSION[$session_key])) {
        return false;
    }

    $data = $_SESSION[$session_key];

    // Check structure
    if (empty($data['token']) || empty($data['expires'])) {
        return false;
    }

    // Check expiry (use <= to disallow expired token)
    if (time() > (int)$data['expires']) {
        // expired: remove stored token
        unset($_SESSION[$session_key]);
        return false;
    }

    // Timing-safe comparison
    $isValid = hash_equals($data['token'], $submitted_token);

    // Optionally make token single-use
    if ($isValid && $single_use) {
        unset($_SESSION[$session_key]);
    }

    return $isValid;
}
