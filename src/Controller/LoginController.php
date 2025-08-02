<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Psr\Log\LoggerInterface;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'sagit_login')]
    public function index(AuthenticationUtils $authenticationUtils, LoggerInterface $logger): Response
    {
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $logger->warning('Login failed', [
                'last_username' => $lastUsername,
                'error' => $error->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } else if ($lastUsername) {
            $logger->info('Login page visited', [
                'last_username' => $lastUsername,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        }

        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'sagit_logout')]
    public function logout(): void
    {
        // This method can be empty - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method should not be reached!');
    }
}
