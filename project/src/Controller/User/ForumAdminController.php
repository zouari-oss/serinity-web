<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\Category;
use App\Entity\User;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use App\Service\StatisticsService;
use App\Service\User\UserNavService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/forum/admin')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ForumAdminController extends AbstractUserUiController
{
    #[Route('', name: 'app_admin_forum', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository, UserNavService $navService): Response
    {
        $user = $this->currentUser();
        if (($redirect = $this->redirectIfNotTherapist($user)) instanceof Response) {
            return $redirect;
        }

        return $this->render('admin/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
            ...$this->buildLayoutData($user, $navService, 'Forum Backoffice', 'Manage categories and insights'),
        ]);
    }

    #[Route('/categories/new', name: 'app_admin_category_new')]
    public function newCategory(Request $request, CategoryService $categoryService, UserNavService $navService): Response
    {
        $user = $this->currentUser();
        if (($redirect = $this->redirectIfNotTherapist($user)) instanceof Response) {
            return $redirect;
        }

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
            ...$this->buildLayoutData($user, $navService, 'Forum Backoffice', 'Add a new category'),
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'app_admin_category_edit')]
    public function editCategory(Category $category, Request $request, CategoryService $categoryService, UserNavService $navService): Response
    {
        $user = $this->currentUser();
        if (($redirect = $this->redirectIfNotTherapist($user)) instanceof Response) {
            return $redirect;
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categoryService->save($category);

            return $this->redirectToRoute('app_admin_forum');
        }

        return $this->render('admin/category_form.html.twig', [
            'form' => $form,
            'mode' => 'edit',
            ...$this->buildLayoutData($user, $navService, 'Forum Backoffice', 'Update category details'),
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'])]
    public function deleteCategory(Category $category, Request $request, CategoryService $categoryService): Response
    {
        $user = $this->currentUser();
        if (($redirect = $this->redirectIfNotTherapist($user)) instanceof Response) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('delete' . $category->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('app_admin_forum');
        }

        $categoryService->delete($category);

        return $this->redirectToRoute('app_admin_forum');
    }

    #[Route('/statistics', name: 'app_admin_statistics')]
    public function statistics(StatisticsService $statisticsService, UserNavService $navService): Response
    {
        $user = $this->currentUser();
        if (($redirect = $this->redirectIfNotTherapist($user)) instanceof Response) {
            return $redirect;
        }

        return $this->render('admin/statistics.html.twig', [
            'stats' => $statisticsService->getForumStatistics(),
            ...$this->buildLayoutData($user, $navService, 'Forum Backoffice', 'Forum statistics'),
        ]);
    }

    private function redirectIfNotTherapist(User $user): ?Response
    {
        if ($user->getRole() !== 'THERAPIST') {
            return $this->redirectToRoute('user_ui_forum');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLayoutData(User $user, UserNavService $navService, string $title, string $subtitle): array
    {
        return [
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $user->getEmail(),
            'topbarTitle' => $title,
            'topbarSubtitle' => $subtitle,
        ];
    }
}
