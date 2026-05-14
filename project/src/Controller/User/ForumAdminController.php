<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\Category;
use App\Entity\User;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/forum')]
#[IsGranted('ROLE_ADMIN')]
final class ForumAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_forum', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $user = $this->requireAdminUser();

        return $this->render('admin/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
            ...$this->buildLayoutData($user, 'Forum Backoffice', 'Manage categories and insights'),
        ]);
    }

    #[Route('/categories/new', name: 'app_admin_category_new')]
    public function newCategory(Request $request, CategoryService $categoryService): Response
    {
        $user = $this->requireAdminUser();

        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categoryService->save($category);

            return $this->redirectToRoute('app_admin_forum');
        }

        return $this->render('admin/category_form.html.twig', [
            'form' => $form,
            'mode' => 'create',
            ...$this->buildLayoutData($user, 'Forum Backoffice', 'Add a new category'),
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'app_admin_category_edit')]
    public function editCategory(Category $category, Request $request, CategoryService $categoryService): Response
    {
        $user = $this->requireAdminUser();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categoryService->save($category);

            return $this->redirectToRoute('app_admin_forum');
        }

        return $this->render('admin/category_form.html.twig', [
            'form' => $form,
            'mode' => 'edit',
            ...$this->buildLayoutData($user, 'Forum Backoffice', 'Update category details'),
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'])]
    public function deleteCategory(Category $category, Request $request, CategoryService $categoryService): Response
    {
        $this->requireAdminUser();

        if (!$this->isCsrfTokenValid('delete' . $category->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('app_admin_forum');
        }

        $categoryService->delete($category);

        return $this->redirectToRoute('app_admin_forum');
    }

    #[Route('/statistics', name: 'app_admin_statistics')]
    public function statistics(StatisticsService $statisticsService): Response
    {
        $user = $this->requireAdminUser();

        return $this->render('admin/statistics.html.twig', [
            'stats' => $statisticsService->getForumStatistics(),
            ...$this->buildLayoutData($user, 'Forum Backoffice', 'Forum statistics'),
        ]);
    }

    private function requireAdminUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($user->getRole() !== 'ADMIN') {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLayoutData(User $user, string $title, string $subtitle): array
    {
        return [
            'nav' => $this->buildAdminNav('app_admin_forum'),
            'userName' => $user->getEmail(),
            'topbarTitle' => $title,
            'topbarSubtitle' => $subtitle,
        ];
    }

    /**
     * @return list<array{section: string, label: string, route: string, icon: string, active: bool, children?: list<array{label: string, route: string, icon: string, active: bool}>}>
     */
    private function buildAdminNav(string $activeRoute): array
    {
        $moodChildRoutes = ['ac_ui_mood', 'ac_ui_emotion', 'ac_ui_influence'];
        $sleepChildRoutes = ['ac_ui_sleep', 'ac_ui_sleep_reves'];
        $items = [
            ['section' => 'Admin self-management', 'label' => 'Dashboard', 'route' => 'ac_ui_dashboard', 'icon' => 'dashboard'],
            ['section' => 'Admin self-management', 'label' => 'Profile', 'route' => 'ac_ui_profile', 'icon' => 'person'],
            ['section' => 'Admin self-management', 'label' => 'Settings', 'route' => 'ac_ui_settings', 'icon' => 'settings'],
            ['section' => 'Admin self-management', 'label' => 'Sessions', 'route' => 'ac_ui_sessions', 'icon' => 'devices'],
            ['section' => 'Admin self-management', 'label' => 'Audit logs', 'route' => 'ac_ui_audit_logs', 'icon' => 'history'],
            ['section' => 'Users management', 'label' => 'Users', 'route' => 'ac_ui_users', 'icon' => 'group'],
            ['section' => 'Users management', 'label' => 'Consultations', 'route' => 'ac_ui_consultations', 'icon' => 'medical_services'],
            ['section' => 'Users management', 'label' => 'Exercises', 'route' => 'ac_ui_exercises', 'icon' => 'self_improvement'],
            ['section' => 'Users management', 'label' => 'Forum', 'route' => 'app_admin_forum', 'icon' => 'forum'],
            [
                'section' => 'Users management',
                'label' => 'Mood',
                'route' => 'ac_ui_mood',
                'icon' => 'mood',
                'children' => [
                    ['label' => 'Mood analytics', 'route' => 'ac_ui_mood', 'icon' => 'analytics'],
                    ['label' => 'Emotion management', 'route' => 'ac_ui_emotion', 'icon' => 'sentiment_satisfied'],
                    ['label' => 'Influence management', 'route' => 'ac_ui_influence', 'icon' => 'tune'],
                ],
            ],
            [
                'section' => 'Users management',
                'label' => 'Sleep',
                'route' => 'ac_ui_sleep',
                'icon' => 'hotel',
                'children' => [
                    ['label' => 'Sommeil', 'route' => 'ac_ui_sleep', 'icon' => 'bedtime'],
                    ['label' => 'Reves management', 'route' => 'ac_ui_sleep_reves', 'icon' => 'nights_stay'],
                ],
            ],
        ];

        return array_map(
            static function (array $item) use ($activeRoute, $moodChildRoutes, $sleepChildRoutes): array {
                $isMoodGroup = $item['route'] === 'ac_ui_mood' && isset($item['children']);
                $isSleepGroup = $item['route'] === 'ac_ui_sleep' && isset($item['children']);
                $active = $item['route'] === $activeRoute;

                if ($isMoodGroup) {
                    $active = in_array($activeRoute, $moodChildRoutes, true);
                } elseif ($isSleepGroup) {
                    $active = in_array($activeRoute, $sleepChildRoutes, true);
                }

                if (!$isMoodGroup && !$isSleepGroup) {
                    return [
                        'section' => $item['section'],
                        'label' => $item['label'],
                        'route' => $item['route'],
                        'icon' => $item['icon'],
                        'active' => $active,
                    ];
                }

                $children = array_map(
                    static fn(array $child): array => [
                        ...$child,
                        'active' => $child['route'] === $activeRoute,
                    ],
                    $item['children'] ?? [],
                );

                return [
                    'section' => $item['section'],
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'icon' => $item['icon'],
                    'active' => $active,
                    'children' => $children,
                ];
            },
            $items,
        );
    }
}
