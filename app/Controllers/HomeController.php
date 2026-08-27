<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AdminService;
use App\Services\AgendaService;
use App\Services\BlogService;
use App\Services\HomeInfoService;
use App\Services\ProfileService;

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
        $profile = null;
        $registrationBlock = null;
        $needsProfileCompletion = false;
        $agendaActionUrl = url('/perfil/completar?return_to=%2F%23home-training-locations');
        $agendaActionLabel = 'Completar cadastro';
        $agendaReminderTitle = 'Complete seu cadastro para agendar';
        $schedulablePeople = [];
        $specialSchedulePeople = [];

        if (Auth::check()) {
            $profileService = new ProfileService();
            $profile = $profileService->getAuthenticatedPerson();
            $registrationBlock = $profileService->getSchedulingBlockForAuthenticatedAccount();
            $needsProfileCompletion = $registrationBlock !== null || !$profile || (int) ($profile['cadastro_completo'] ?? 0) !== 1;
            $specialSchedulePeople = $agendaService->listSpecialSchedulePeople();

            if (!$needsProfileCompletion) {
                $schedulablePeople = $agendaService->listSchedulablePeople();
            } elseif (($registrationBlock['tipo'] ?? '') === 'dependente_cadastro_incompleto') {
                $agendaActionUrl = url('/dashboard');
                $agendaActionLabel = 'Abrir meu painel';
                $agendaReminderTitle = 'Regularize os cadastros para agendar';
            }
        }
        $scheduleFilterOptions = $agendaService->activeWeeklyScheduleFilterOptions();
        $locations = $agendaService->listLocations();
        usort($locations, static function (array $left, array $right): int {
            $leftName = trim((string) ($left['apelido_local'] ?? $left['nome_local'] ?? ''));
            $rightName = trim((string) ($right['apelido_local'] ?? $right['nome_local'] ?? ''));
            return strcasecmp($leftName, $rightName);
        });
        $suggestedLocations = $locations;
        shuffle($suggestedLocations);
        $suggestedLocations = array_slice($suggestedLocations, 0, 3);
        $trainingLocations = $scheduleFilterOptions['locations'] ?? [];
        $suggestedTrainingLocations = $trainingLocations;
        shuffle($suggestedTrainingLocations);
        $suggestedTrainingLocations = array_slice($suggestedTrainingLocations, 0, 3);

        $this->view('home/index', [
            'title' => 'Cursos Esportivos SBC',
            'pageClass' => 'pagina-home',
            'locations' => $locations,
            'suggestedLocations' => $suggestedLocations,
            'trainingLocations' => $trainingLocations,
            'suggestedTrainingLocations' => $suggestedTrainingLocations,
            'trainingModalities' => $scheduleFilterOptions['modalities'] ?? [],
            'schedulablePeople' => $schedulablePeople,
            'specialSchedulePeople' => $specialSchedulePeople,
            'profile' => $profile,
            'registrationBlock' => $registrationBlock,
            'needsProfileCompletion' => $needsProfileCompletion,
            'agendaActionUrl' => $agendaActionUrl,
            'agendaActionLabel' => $agendaActionLabel,
            'agendaReminderTitle' => $agendaReminderTitle,
            'courseModalities' => $agendaService->listModalities(),
            'weeklyTrainingModalityNames' => $agendaService->activeWeeklyScheduleModalityNames(),
            'homeCoursesLocationsContent' => $homeInfoService->getCoursesLocationsContent(),
            'homeTrainingLocationsContent' => $homeInfoService->getTrainingLocationsContent(),
            'homeCourseModalitiesContent' => $homeInfoService->getCourseModalitiesContent(),
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
