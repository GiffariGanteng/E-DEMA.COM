<?php
/**
 * Router untuk PHP built-in server.
 *
 *   php -S 0.0.0.0:8080 -t . router.php
 *
 * Folder internal (database, var, tools, lib) tidak boleh diunduh lewat browser,
 * karena berisi berkas SQLite, CSV kredensial, dan kode server.
 */

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$proto = $forwarded !== ''
    ? $forwarded
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$localHost = preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host)
    || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host);

// Paksa HTTPS untuk domain publik (tidak berlaku untuk localhost/LAN).
if (!$localHost && $proto === 'http') {
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

if ($proto === 'https') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Tolak berkas/folder internal dan berkas tersembunyi.
if (preg_match('#^/(var|database|tools|api/lib)(/|$)#i', $path)
    || preg_match('#^/api/config\.local\.php$#i', $path)
    || preg_match('#(^|/)\.#', $path)
    || preg_match('#\.(sqlite|sqlite3|db|sql|csv|log|ini|md|json|lock)$#i', $path)
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n";
    return true;
}

// Serahkan ke server: berkas statis disajikan, PHP dieksekusi.
return false;
