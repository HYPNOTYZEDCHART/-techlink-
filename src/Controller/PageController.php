<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;


final class PageController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('page/contact.html.twig');
    }

    #[Route('/conditions-generales', name: 'app_terms')]
    public function terms(): Response
    {
        return $this->render('page/terms.html.twig');
    }

    #[Route('/confidentialite', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('page/privacy.html.twig');
    }

    #[Route('/newsletter', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function newsletter(Request $request, EntityManagerInterface $em, NewsletterSubscriberRepository $repo): Response
    {
        $email = $request->request->get('email');

        if ($email && !$repo->findOneBy(['email' => $email])) {
            $subscriber = new NewsletterSubscriber();
            $subscriber->setEmail($email);
            $subscriber->setCreatedAt(new \DateTimeImmutable());
            $em->persist($subscriber);
            $em->flush();
        }

        $this->addFlash('success', 'Merci pour ton inscription !');

        return $this->redirect($request->headers->get('referer', '/'));
    }
}