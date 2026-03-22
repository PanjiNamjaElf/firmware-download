<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the customer-facing firmware download page.
 *
 * This controller has one responsibility: render the Twig template.
 * The firmware lookup itself is handled client-side by a fetch() call
 * to the API endpoint at POST /api/carplay/software/version.
 *
 * There is no server-side form processing here — the page is a static
 * shell that drives the API exactly as the legacy Vue component did.
 */
class SoftwareDownloadController extends AbstractController
{
    /**
     * Renders the firmware software download page.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/carplay/software-download', name: 'customer_software_download', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('customer/software_download.html.twig');
    }
}
