<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\User\UpdateProfileRequest;
use App\Message\GenerateAnimeAvatarMessage;
use App\Service\Avatar\AvatarGenerationPendingStore;
use App\Service\ImageUploadService;
use App\Service\User\UserProfileService;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/user')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileController extends AbstractUserUiController
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
        private readonly ImageUploadService $imageUploadService,
        private readonly MessageBusInterface $messageBus,
        private readonly AvatarGenerationPendingStore $pendingStore,
    ) {
    }

    #[Route('/profile', name: 'user_ui_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, ValidatorInterface $validator): Response
    {
        $user = $this->currentUser();
        if ($request->isMethod('POST')) {
            $dto = new UpdateProfileRequest();
            $dto->email = (string) $request->request->get('email', '');
            $dto->username = $this->nullableString($request->request->get('username'));
            $dto->firstName = $this->nullableString($request->request->get('firstName'));
            $dto->lastName = $this->nullableString($request->request->get('lastName'));
            $dto->country = $this->nullableString($request->request->get('country'));
            $dto->state = $this->nullableString($request->request->get('state'));
            $dto->aboutMe = $this->nullableString($request->request->get('aboutMe'));

            $errors = $validator->validate($dto);
            if (count($errors) > 0) {
                $this->addFlash('error', (string) $errors[0]->getMessage());
            } else {
                $result = $this->userProfileService->update($user, $dto);
                if (!$result->success) {
                    $this->addFlash('error', $result->message);
                } else {
                    $file = $request->files->get('profileImage');
                    if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $file->isValid()) {
                        try {
                            $imageUrl = $this->imageUploadService->uploadProfileImage($file);
                            $this->userProfileService->setProfileImage($user, $imageUrl);
                            $this->pendingStore->markPending($user->getId());
                            $this->messageBus->dispatch(new GenerateAnimeAvatarMessage($user->getId()));
                            $this->addFlash('success', 'Profile image updated successfully.');
                            $this->addFlash('success', 'Anime avatar generation started in background.');
                        } catch (\RuntimeException $exception) {
                            $this->addFlash('error', 'Profile saved, but image upload failed.');
                        }
                    }
                    $this->addFlash('success', 'Profile details updated successfully.');

                    return $this->redirectToRoute('user_ui_profile');
                }
            }
        }

        return $this->render('user/pages/profile.html.twig', [
            'nav' => $this->buildNav('user_ui_profile'),
            'userName' => $user->getEmail(),
            'userRole' => $user->getRole(),
            'accountStatus' => $user->getAccountStatus(),
            'presenceStatus' => $user->getPresenceStatus(),
            'profile' => $this->userProfileService->toArray($user),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
