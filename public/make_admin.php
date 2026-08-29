<?php
/**
 * Script de promotion Admin - Usage unique et sécurisé
 * URL: /make_admin.php?email=votre@email.com&secret=techlink2026
 */

define('ADMIN_SECRET', 'techlink2026');

$secret = $_GET['secret'] ?? '';
if ($secret !== ADMIN_SECRET) {
    http_response_code(403);
    die('Accès refusé. Clé secrète invalide.');
}

$email = $_GET['email'] ?? null;
if (!$email) {
    die("Usage: /make_admin.php?email=votre@email.com&secret=techlink2026");
}

// Connexion directe à la base de données via PDO
$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    die('DATABASE_URL non défini.');
}

$dsn = preg_replace('#^postgresql://#', 'pgsql://', $databaseUrl);
// Convertir l'URL en paramètres PDO
$parsed = parse_url($databaseUrl);
$pdoDsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
    $parsed['host'],
    $parsed['port'] ?? 5432,
    ltrim($parsed['path'], '/')
);

try {
    $pdo = new PDO($pdoDsn, $parsed['user'], $parsed['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Aucun compte trouvé avec l'email : " . htmlspecialchars($email) . "<br>Créez d'abord un compte sur le site !");
    }

    // Donner le rôle ADMIN
    $roles = json_encode(['ROLE_ADMIN']);
    $update = $pdo->prepare('UPDATE users SET roles = :roles WHERE email = :email');
    $update->execute([':roles' => $roles, ':email' => $email]);

    echo "<h2 style='color:green;font-family:sans-serif'>✅ SUCCÈS !</h2>";
    echo "<p style='font-family:sans-serif'>L'utilisateur <strong>" . htmlspecialchars($email) . "</strong> est maintenant ADMIN.</p>";
    echo "<p style='font-family:sans-serif'><a href='/admin'>Cliquez ici pour aller sur /admin</a></p>";
    echo "<p style='color:red;font-family:sans-serif'><strong>⚠️ IMPORTANT : Supprimez ce fichier make_admin.php après utilisation !</strong></p>";

} catch (Exception $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
