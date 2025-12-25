<?php

declare(strict_types=1);

/**
 * Main entry point - routes requests
 * 
 * Routes:
 * - /join/{code} → join.php
 * - /css/* → static files
 * - /js/* → static files
 * - / → index.html (chat app)
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Route /join/{code} to join.php
if (preg_match('#^/join/([A-Za-z0-9]+)$#', $path, $matches)) {
    $_GET['code'] = $matches[1];
    require __DIR__ . '/join.php';
    exit;
}

// Route /join to join.php (with query param)
if ($path === '/join' || $path === '/join.php') {
    require __DIR__ . '/join.php';
    exit;
}

// Route /login to login.php
if ($path === '/login' || $path === '/login.php') {
    require __DIR__ . '/login.php';
    exit;
}

// Serve static files
if (preg_match('#^/(css|js)/.+$#', $path)) {
    return false; // Let PHP built-in server handle static files
}

// Main app - serve index.html
if ($path === '/' || $path === '/index.php' || $path === '/index.html') {
    readfile(__DIR__ . '/index.html');
    exit;
}

// 404 for everything else
http_response_code(404);
echo '404 Not Found';
