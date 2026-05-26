<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VigorAppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/app/{view}', name: 'vigor_app', requirements: ['view' => 'home|workout|library|profile'], defaults: ['view' => 'home'])]
    public function __invoke(string $view = 'home'): Response
    {
        return $this->render('vigor_app/index.html.twig', [
            'activeView' => $view,
            'navItems' => [
                ['id' => 'home', 'label' => 'Accueil', 'icon' => 'layout-grid'],
                ['id' => 'workout', 'label' => 'Seance', 'icon' => 'play'],
                ['id' => 'library', 'label' => 'Bibliotheque', 'icon' => 'search'],
                ['id' => 'profile', 'label' => 'Profil', 'icon' => 'user'],
            ],
            'weeklyDays' => [
                ['day' => 'L', 'value' => '2.8', 'label' => 'Lundi', 'height' => 55, 'rest' => false],
                ['day' => 'M', 'value' => '3.6', 'label' => 'Mardi', 'height' => 78, 'rest' => false],
                ['day' => 'M', 'value' => '0', 'label' => 'Mercredi', 'height' => 0, 'rest' => true],
                ['day' => 'J', 'value' => '4.2', 'label' => 'Jeudi - meilleure seance', 'height' => 100, 'rest' => false, 'active' => true],
                ['day' => 'V', 'value' => '2.1', 'label' => 'Vendredi', 'height' => 40, 'rest' => false],
                ['day' => 'S', 'value' => '0', 'label' => 'Samedi', 'height' => 0, 'rest' => true],
                ['day' => 'D', 'value' => '1.5', 'label' => 'Dimanche', 'height' => 30, 'rest' => false],
            ],
            'recentRecords' => [
                ['exercise' => 'Squat', 'value' => '140', 'unit' => 'kg x 5', 'date' => 'Aujourd\'hui', 'gain' => '+5kg', 'previous' => 'vs 135kg', 'new' => true],
                ['exercise' => 'Developpe couche', 'value' => '95', 'unit' => 'kg x 6', 'date' => 'Il y a 3j', 'gain' => '+2.5kg', 'previous' => 'vs 92.5kg'],
                ['exercise' => 'Souleve de terre', 'value' => '170', 'unit' => 'kg x 3', 'date' => 'Il y a 1s', 'gain' => '+10kg', 'previous' => 'vs 160kg'],
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
