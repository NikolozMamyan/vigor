<?php

namespace App\Controller;

use App\Service\Dashboard\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VigorAppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/app/{view}', name: 'vigor_app', requirements: ['view' => 'home|workout|library|profile'], defaults: ['view' => 'home'])]
    public function __invoke(DashboardService $dashboardService, string $view = 'home'): Response
    {
        $dashboard = $dashboardService->build();

        return $this->render('vigor_app/index.html.twig', [
            'activeView' => $view,
            'dashboard' => $dashboard,
            'navItems' => [
                ['id' => 'home', 'label' => 'Accueil', 'icon' => 'layout-grid'],
                ['id' => 'workout', 'label' => 'Seance', 'icon' => 'play'],
                ['id' => 'library', 'label' => 'Bibliotheque', 'icon' => 'search'],
                ['id' => 'profile', 'label' => 'Profil', 'icon' => 'user'],
            ],
            'sets' => [
                ['number' => 1, 'previous' => '80kg x 10', 'weight' => 80, 'reps' => 10],
                ['number' => 2, 'previous' => '80kg x 8', 'weight' => null, 'reps' => null],
                ['number' => 3, 'previous' => '77.5kg x 9', 'weight' => null, 'reps' => null],
            ],
            'exercises' => [
                [
                    'name' => 'Squat',
                    'category' => 'Jambes',
                    'tag' => 'Barre',
                    'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop',
                ],
                [
                    'name' => 'Curl Biceps',
                    'category' => 'Isolation',
                    'tag' => 'Halteres',
                    'image' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=1470&auto=format&fit=crop',
                ],
                [
                    'name' => 'Developpe couche',
                    'category' => 'Pectoraux',
                    'tag' => 'Push',
                    'image' => 'https://images.unsplash.com/photo-1534367610401-9f5ed68180aa?q=80&w=1470&auto=format&fit=crop',
                ],
                [
                    'name' => 'Tractions',
                    'category' => 'Dos',
                    'tag' => 'Poids libre',
                    'image' => 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?q=80&w=1470&auto=format&fit=crop',
                ],
            ],
            'profileStats' => [
                ['label' => 'Seances', 'value' => '147', 'icon' => 'calendar-check'],
                ['label' => 'Volume total', 'value' => '428', 'suffix' => 'T', 'icon' => 'dumbbell'],
                ['label' => 'Records battus', 'value' => '23', 'icon' => 'trophy'],
                ['label' => 'Plus longue serie', 'value' => '28', 'suffix' => 'jours', 'icon' => 'flame'],
            ],
            'badges' => [
                ['label' => 'Marathon', 'icon' => 'flame', 'locked' => false],
                ['label' => 'Eclair', 'icon' => 'zap', 'locked' => false],
                ['label' => 'Champion', 'icon' => 'trophy', 'locked' => false],
                ['label' => 'Discipline', 'icon' => 'medal', 'locked' => false],
                ['label' => 'Etoile', 'icon' => 'star', 'locked' => false],
                ['label' => '???', 'icon' => 'lock', 'locked' => true],
                ['label' => '???', 'icon' => 'lock', 'locked' => true],
                ['label' => '???', 'icon' => 'lock', 'locked' => true],
            ],
            'profileSettings' => [
                ['label' => 'Preferences', 'description' => 'Unites, theme, langue', 'icon' => 'settings'],
                ['label' => 'Notifications', 'description' => 'Rappels, rapports hebdo', 'icon' => 'bell'],
                ['label' => 'Confidentialite', 'description' => 'Donnees, partage', 'icon' => 'shield-check'],
                ['label' => 'Aide & support', 'description' => 'FAQ, nous contacter', 'icon' => 'help-circle'],
            ],
        ]);
    }
}
