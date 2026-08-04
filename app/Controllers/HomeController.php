<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AdminService;
use App\Services\AgendaService;
use App\Services\BlogService;
use App\Services\HomeInfoService;

class HomeController extends Controller
{
    /**
     * Exibe a home pública do sistema.
     */
    public function index(): void
    {
        $agendaService = new AgendaService();
        $adminService = new AdminService();
        $blogService = new BlogService();
        $homeInfoService = new HomeInfoService();
        $locations = $agendaService->listLocations();
        usort($locations, static function (array $left, array $right): int {
            $leftName = trim((string) ($left['apelido_local'] ?? $left['nome_local'] ?? ''));
            $rightName = trim((string) ($right['apelido_local'] ?? $right['nome_local'] ?? ''));
            return strcasecmp($leftName, $rightName);
        });
        $suggestedLocations = $locations;
        shuffle($suggestedLocations);
        $suggestedLocations = array_slice($suggestedLocations, 0, 3);

        $this->view('home/index', [
            'title' => 'Cursos Esportivos SBC',
            'pageClass' => 'pagina-home',
            'locations' => $locations,
            'suggestedLocations' => $suggestedLocations,
            'posts' => $blogService->listPublishedPosts([
                'limit' => 3,
            ]),
            'homeSpecialEvents' => $adminService->listPublishedSpecialSchedules('home', 3),
            'blogSpecialEvents' => $adminService->listPublishedSpecialSchedules('blog', 6),
            'homeInfoBox' => $homeInfoService->getHomeInfoBox(),
            'homeHighlightCards' => $homeInfoService->getHighlightCards(),
            'homeHeroContent' => $homeInfoService->getHeroContent(),
            'homeHeaderContent' => array_merge($homeInfoService->getLogoContent(), $homeInfoService->getContactContent()),
        ]);
    }
}
