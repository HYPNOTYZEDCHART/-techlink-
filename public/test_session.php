<?php
require_once dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

$request = Request::createFromGlobals();
Request::setTrustedProxies(['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PREFIX);

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("DATABASE_URL is missing.");
}

try {
    $handler = new PdoSessionHandler($dbUrl, [
        'db_table' => 'sessions',
        'db_id_col' => 'sess_id',
        'db_data_col' => 'sess_data',
        'db_time_col' => 'sess_time',
        'db_lifetime_col' => 'sess_lifetime',
    ]);
    
    $storage = new NativeSessionStorage([], $handler);
    $session = new Session($storage);
    $session->start();
    
    $count = $session->get('count', 0);
    $session->set('count', $count + 1);
    
    echo "<h1>Session Test</h1>";
    echo "<p>Session ID: " . $session->getId() . "</p>";
    echo "<p>Count: " . $session->get('count') . "</p>";
    echo "<p>Is Secure: " . ($request->isSecure() ? 'Yes' : 'No') . "</p>";
    echo "<p>Scheme: " . $request->getScheme() . "</p>";
    echo "<p>Host: " . $request->getHost() . "</p>";
    
    // Read directly from DB to verify it was written
    $pdoDsn = preg_replace('#^postgresql://#', 'pgsql://', $dbUrl);
    $parsed = parse_url($dbUrl);
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%d;dbname=%s', $parsed['host'], $parsed['port'] ?? 5432, ltrim($parsed['path'], '/')),
        $parsed['user'],
        $parsed['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE sess_id = :id");
    $stmt->execute(['id' => $session->getId()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "<p style='color:green'>Session FOUND in database!</p>";
        echo "<pre>" . print_r($row, true) . "</pre>";
    } else {
        echo "<p style='color:red'>Session NOT FOUND in database! Write failed or delayed.</p>";
    }
} catch (Exception $e) {
    echo "<h2>Exception:</h2>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
