<?php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('prod', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$userRepository = $em->getRepository(\App\Entity\User::class);

$email = $_GET['email'] ?? null;

if (!$email) {
    die("Veuillez ajouter ?email=votre@email.com a l'URL.");
}

$user = $userRepository->findOneBy(['email' => $email]);

if (!$user) {
    die("Utilisateur non trouve avec l'email : " . htmlspecialchars($email));
}

$user->setRoles(['ROLE_ADMIN']);
$em->flush();

echo "SUCCES ! L'utilisateur " . htmlspecialchars($email) . " est maintenant ADMIN. Vous pouvez aller sur /admin !";
