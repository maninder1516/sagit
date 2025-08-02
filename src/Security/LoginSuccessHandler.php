<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

// This class is redundant if you're already using the LoginFormAuthenticator
// You can safely delete this file if your LoginFormAuthenticator already handles redirection
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // Make sure this route name matches the one in your LoginFormAuthenticator if you keep this file
        return new RedirectResponse($this->urlGenerator->generate('sagit_mission')); // Note: changed from 'app_mission' for consistency
    }
}