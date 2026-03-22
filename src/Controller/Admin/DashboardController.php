<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SoftwareVersion;
use App\Repository\SoftwareVersionRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * EasyAdmin dashboard — navigation menu and firmware record stats overview.
 */
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly SoftwareVersionRepository $softwareVersionRepository,
    ) {
        //
    }

    /**
     * Admin home page with a quick count of firmware records by type.
     */
    #[Route('/admin', name: 'easyadmin')]
    public function index(): Response
    {
        $stats = $this->softwareVersionRepository->getStats();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Dashboard title and favicon.
     */
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/favicon.ico" style="height:20px"> Firmware Manager')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    /**
     * Sidebar navigation items.
     */
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Firmware Versions');

        yield MenuItem::linkToCrud('All Versions', 'fa fa-list', SoftwareVersion::class);

        yield MenuItem::section('Account');

        yield MenuItem::linkToLogout('Log Out', 'fa fa-sign-out');
    }

    /**
     * Disables user-menu dropdown items to work around an EasyAdmin 4.29 bug where
     * MenuItem::getHtmlAttributes() returns a PHP array that layout.html.twig passes
     * through {{ }} (string coercion), triggering "Array to string conversion".
     * Logout is available via the sidebar instead.
     */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return UserMenu::new()
            ->setName($user->getUserIdentifier())
            ->displayUserName()
            ->setMenuItems([]);
    }

    /**
     * Loads Font Awesome from CDN for sidebar and button icons.
     */
    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css');
    }
}
