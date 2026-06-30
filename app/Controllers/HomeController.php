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
     * Exibe a home publica do sistema.
     */
    public function index(): void
    {
        $agendaService = new AgendaService();
        $adminService = new AdminService();
        $blogService = new BlogService();
        $homeInfoService = new HomeInfoService();

        $this->view('home/index', [
            'title' => 'Cursos Esportivos SBC',
            'pageClass' => 'pagina-home',
            'locations' => $agendaService->listLocations(),
            'posts' => $blogService->listPublishedPosts([
                'limit' => 3,
            ]),
            'homeSpecialEvents' => $adminService->listPublishedSpecialSchedules('home', 3),
            'blogSpecialEvents' => $adminService->listPublishedSpecialSchedules('blog', 6),
            'homeInfoBox' => $homeInfoService->getHomeInfoBox(),
        ]);
    }
}
