<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AdminService;
use App\Services\AgendaService;
use App\Services\BlogService;
use App\Services\HomeInfoService;
use App\Services\OfficialCommunicationService;

class HomeController extends Controller
{
    /**
     * Exibe a home publica do sistema.
     */
    public function index(): void
    {
        $agendaService = new AgendaService();
        $adminService = new AdminService();
        $blogService = new BlogService();
        $homeInfoService = new HomeInfoService();
        $officialCommunicationService = new OfficialCommunicationService();

        $this->view('home/index', [
            'title' => 'Cursos Esportivos SBC',
            'pageClass' => 'pagina-home',
            'locations' => $agendaService->listLocations(),
            'posts' => $blogService->listPublishedPosts([
                'limit' => 3,
            ]),
            'homeSpecialEvents' => $adminService->listPublishedSpecialAgendaEvents('home', 3),
            'blogSpecialEvents' => $adminService->listPublishedSpecialAgendaEvents('blog', 6),
            'homeInfoBox' => $homeInfoService->getHomeInfoBox(),
            'officialCommunication' => $officialCommunicationService->getHomeBlock(),
        ]);
    }
}
