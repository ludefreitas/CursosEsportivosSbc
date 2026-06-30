<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AdminService;
use App\Services\BlogService;
use App\Services\OfficialCommunicationService;

class BlogController extends Controller
{
    /**
     * Exibe a capa do blog.
     */
    public function index(): void
    {
        $blogService = new BlogService();
        $adminService = new AdminService();
        $officialCommunicationService = new OfficialCommunicationService();
        $search = trim((string) ($_GET['busca'] ?? ''));
        $category = trim((string) ($_GET['categoria'] ?? ''));

        $this->view('blog/index', [
            'title' => 'Blog',
            'pageClass' => 'pagina-blog',
            'search' => $search,
            'selectedCategory' => $category,
            'posts' => $blogService->listPublishedPosts([
                'search' => $search,
                'category' => $category,
                'limit' => 12,
            ]),
            'featuredPosts' => $blogService->listPublishedPosts([
                'featured_only' => 1,
                'limit' => 3,
            ]),
            'categories' => $blogService->listPublicCategories(),
            'archiveMonths' => $blogService->listArchiveMonths(),
            'blogSpecialEvents' => $adminService->listPublishedSpecialAgendaEvents('blog', 4),
            'officialCommunication' => $officialCommunicationService->getBlogBlock(),
        ]);
    }

    /**
     * Exibe uma postagem especifica.
     */
    public function post(): void
    {
        $blogService = new BlogService();
        $adminService = new AdminService();
        $slug = trim((string) ($_GET['slug'] ?? ''));
        $post = $blogService->findPublishedPostBySlug($slug);

        if ($post === null) {
            http_response_code(404);
            $this->view('blog/not_found', [
                'title' => 'Postagem nao encontrada',
                'pageClass' => 'pagina-blog',
                'categories' => $blogService->listPublicCategories(),
                'archiveMonths' => $blogService->listArchiveMonths(),
            ]);
            return;
        }

        $this->view('blog/post', [
            'title' => (string) ($post['titulo'] ?? 'Blog'),
            'pageClass' => 'pagina-blog',
            'post' => $post,
            'relatedPosts' => $blogService->listRelatedPosts($post, 3),
            'blogSpecialEvents' => $adminService->listPublishedSpecialAgendaEvents('blog', 3),
        ]);
    }
}
