<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Handles admin authentication (login / logout).
 *
 * The login form posts to this same route — Symfony Security intercepts the
 * POST, authenticates the user, and redirects to the admin panel on success.
 * This controller only handles GET (render the form) and POST failure
 * (re-render with an error message). Logout is handled entirely by Symfony.
 */
class SecurityController extends AbstractController
{
    /**
     * Renders the admin login form.
     *
     * On a failed login attempt, the error and last submitted username are
     * retrieved from the session via AuthenticationUtils and passed to Twig.
     *
     * @param  \Symfony\Component\Security\Http\Authentication\AuthenticationUtils  $authenticationUtils  Symfony security helper.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/admin/login', name: 'security_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect to admin panel if already logged in.
        if ($this->getUser()) {
            return $this->redirectToRoute('easyadmin');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Logout route — intercepted and handled by Symfony Security.
     *
     * This method body is never executed. Symfony's logout handler takes over
     * at the firewall level, clears the session, and redirects to `security_login`.
     *
     * @throws \LogicException Always — method body is intentionally unreachable.
     */
    #[Route('/admin/logout', name: 'security_logout')]
    public function logout(): never
    {
        throw new \LogicException('This route is intercepted by the Symfony Security firewall.');
    }
}
