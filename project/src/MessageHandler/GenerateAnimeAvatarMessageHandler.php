<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateAnimeAvatarMessage;
use App\Repository\UserRepository;
use App\Service\Avatar\AvatarGenerationPendingStore;
use App\Service\User\UserProfileService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateAnimeAvatarMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProfileService $userProfileService,
        private AvatarGenerationPendingStore $pendingStore,
    ) {
    }

    public function __invoke(GenerateAnimeAvatarMessage $message): void
    {
        try {
            $user = $this->userRepository->find($message->userId);
            if ($user === null) {
                return;
            }

            $this->userProfileService->generateAndStoreAvatar($user);
        } catch (\InvalidArgumentException|\RuntimeException) {
            // keep failures non-fatal for worker and allow retries from API/UI
        } finally {
            $this->pendingStore->clear($message->userId);
        }
    }
}
