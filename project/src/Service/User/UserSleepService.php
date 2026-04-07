<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\Common\ServiceResult;
use App\Entity\SleepDream;
use App\Entity\SleepSession;
use App\Entity\User;
use App\Repository\SleepDreamRepository;
use App\Repository\SleepSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserSleepService
{
    public function __construct(
        private SleepSessionRepository $sleepSessionRepository,
        private SleepDreamRepository $sleepDreamRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function listSessions(User $user, array $filters = []): array
    {
        $sessions = $this->sleepSessionRepository->findUserFiltered($user, $filters);
        $items = array_map(fn(SleepSession $session): array => $this->sessionArray($session), $sessions);

        return [
            'items' => $items,
            'stats' => $this->sleepSessionRepository->userStats($user),
        ];
    }

    public function listDreams(User $user, array $filters = []): array
    {
        $dreams = $this->sleepDreamRepository->findUserFiltered($user, $filters);
        $items = array_map(fn(SleepDream $dream): array => $this->dreamArray($dream), $dreams);

        return [
            'items' => $items,
            'stats' => $this->sleepDreamRepository->userStats($user),
        ];
    }

    public function createSession(User $user, array $payload): ServiceResult
    {
        $session = new SleepSession();
        $session->setUser($user);
        $this->hydrateSession($session, $payload);

        return $this->persistSession($session, 'Sleep session added successfully.');
    }

    public function updateSession(User $user, int $id, array $payload): ServiceResult
    {
        $session = $this->sleepSessionRepository->find($id);
        if (!$session instanceof SleepSession || $session->getUser()->getId() !== $user->getId()) {
            return ServiceResult::failure('Sleep session not found.');
        }

        $this->hydrateSession($session, $payload);
        $session->setUpdatedAt(new \DateTimeImmutable());

        return $this->persistSession($session, 'Sleep session updated successfully.');
    }

    public function deleteSession(User $user, int $id): ServiceResult
    {
        $session = $this->sleepSessionRepository->find($id);
        if (!$session instanceof SleepSession || $session->getUser()->getId() !== $user->getId()) {
            return ServiceResult::failure('Sleep session not found.');
        }

        $this->entityManager->remove($session);
        $this->entityManager->flush();

        return ServiceResult::success('Sleep session deleted successfully.');
    }

    public function createDream(User $user, array $payload): ServiceResult
    {
        $dream = new SleepDream();
        $this->hydrateDream($user, $dream, $payload);

        return $this->persistDream($dream, 'Dream added successfully.');
    }

    public function updateDream(User $user, int $id, array $payload): ServiceResult
    {
        $dream = $this->sleepDreamRepository->find($id);
        if (
            !$dream instanceof SleepDream
            || !$dream->getSommeilId() instanceof SleepSession
            || $dream->getSommeilId()->getUser()->getId() !== $user->getId()
        ) {
            return ServiceResult::failure('Dream not found.');
        }

        $this->hydrateDream($user, $dream, $payload);
        $dream->setUpdatedAt(new \DateTimeImmutable());

        return $this->persistDream($dream, 'Dream updated successfully.');
    }

    public function deleteDream(User $user, int $id): ServiceResult
    {
        $dream = $this->sleepDreamRepository->find($id);
        if (
            !$dream instanceof SleepDream
            || !$dream->getSommeilId() instanceof SleepSession
            || $dream->getSommeilId()->getUser()->getId() !== $user->getId()
        ) {
            return ServiceResult::failure('Dream not found.');
        }

        $this->entityManager->remove($dream);
        $this->entityManager->flush();

        return ServiceResult::success('Dream deleted successfully.');
    }

    /**
     * @return array{sessions:array{total:int,avgDuration:float,insufficient:int,avgQuality:int},dreams:array{total:int,nightmares:int,attention:bool,avgIntensity:float}}
     */
    public function summary(User $user): array
    {
        return [
            'sessions' => $this->sleepSessionRepository->userStats($user),
            'dreams' => $this->sleepDreamRepository->userStats($user),
        ];
    }

    private function persistSession(SleepSession $session, string $successMessage): ServiceResult
    {
        $violations = $this->validator->validate($session);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath();
                $errors[] = ($field !== '' ? $field . ': ' : '') . $violation->getMessage();
            }

            return ServiceResult::failure(implode(' ', array_unique($errors)));
        }

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return ServiceResult::success($successMessage, $this->sessionArray($session));
    }

    private function persistDream(SleepDream $dream, string $successMessage): ServiceResult
    {
        $violations = $this->validator->validate($dream);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath();
                $errors[] = ($field !== '' ? $field . ': ' : '') . $violation->getMessage();
            }

            return ServiceResult::failure(implode(' ', array_unique($errors)));
        }

        $this->entityManager->persist($dream);
        $this->entityManager->flush();

        return ServiceResult::success($successMessage, $this->dreamArray($dream));
    }

    private function hydrateSession(SleepSession $session, array $payload): void
    {
        $session
            ->setDateNuit($this->dateValue($payload['date_nuit'] ?? $payload['sleepDate'] ?? null))
            ->setHeureCoucher($this->stringValue($payload['heure_coucher'] ?? $payload['bedTime'] ?? null))
            ->setHeureReveil($this->stringValue($payload['heure_reveil'] ?? $payload['wakeTime'] ?? null))
            ->setQualite($this->stringValue($payload['qualite'] ?? $payload['quality'] ?? null) ?? 'Moyenne')
            ->setCommentaire($this->stringValue($payload['commentaire'] ?? $payload['comment'] ?? null))
            ->setDureeSommeil($this->nullableFloat($payload['duree_sommeil'] ?? $payload['sleepDuration'] ?? null))
            ->setInterruptions($this->nullableInt($payload['interruptions'] ?? null))
            ->setHumeurReveil($this->stringValue($payload['humeur_reveil'] ?? null))
            ->setEnvironnement($this->stringValue($payload['environnement'] ?? null))
            ->setTemperature($this->nullableFloat($payload['temperature'] ?? null))
            ->setBruitNiveau($this->stringValue($payload['bruit_niveau'] ?? null));
    }

    private function hydrateDream(User $user, SleepDream $dream, array $payload): void
    {
        $sessionId = $this->nullableInt($payload['sommeil_id'] ?? $payload['sleepSessionId'] ?? null);
        $session = null;
        if ($sessionId !== null) {
            $candidate = $this->sleepSessionRepository->find((string) $sessionId);
            if ($candidate instanceof SleepSession && $candidate->getUser()->getId() === $user->getId()) {
                $session = $candidate;
            }
        }

        $dream
            ->setSommeilId($session)
            ->setTitre($this->stringValue($payload['titre'] ?? $payload['title'] ?? null))
            ->setDescription($this->stringValue($payload['description'] ?? null))
            ->setHumeur($this->stringValue($payload['humeur'] ?? null))
            ->setTypeReve($this->stringValue($payload['type_reve'] ?? $payload['dreamType'] ?? null))
            ->setIntensite($this->nullableInt($payload['intensite'] ?? null))
            ->setCouleur($this->boolValue($payload['couleur'] ?? false))
            ->setEmotions($this->stringValue($payload['emotions'] ?? null))
            ->setSymboles($this->stringValue($payload['symboles'] ?? null))
            ->setRecurrent($this->boolValue($payload['recurrent'] ?? false));
    }

    /**
     * @return array<string,mixed>
     */
    private function sessionArray(SleepSession $session): array
    {
        return [
            'id' => $session->getId(),
            'date_nuit' => $session->getDateNuit()?->format('Y-m-d'),
            'heure_coucher' => $session->getHeureCoucher(),
            'heure_reveil' => $session->getHeureReveil(),
            'qualite' => $session->getQualite(),
            'commentaire' => $session->getCommentaire(),
            'duree_sommeil' => $session->getDureeSommeil(),
            'interruptions' => $session->getInterruptions(),
            'humeur_reveil' => $session->getHumeurReveil(),
            'environnement' => $session->getEnvironnement(),
            'temperature' => $session->getTemperature(),
            'bruit_niveau' => $session->getBruitNiveau(),
            // Compatibility keys for existing templates/controllers.
            'sleepDate' => $session->getDateNuit()?->format('Y-m-d'),
            'bedTime' => $session->getHeureCoucher(),
            'wakeTime' => $session->getHeureReveil(),
            'quality' => $session->getQualite(),
            'comment' => $session->getCommentaire(),
            'sleepDuration' => $session->getDureeSommeil(),
            'insufficient' => $session->isSleepInsufficient(),
            'createdAt' => $session->getCreatedAt()->format('c'),
            'updatedAt' => $session->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dreamArray(SleepDream $dream): array
    {
        return [
            'id' => $dream->getId(),
            'sommeil_id' => $dream->getSommeilId()?->getId(),
            'titre' => $dream->getTitre(),
            'description' => $dream->getDescription(),
            'humeur' => $dream->getHumeur(),
            'type_reve' => $dream->getTypeReve(),
            'intensite' => $dream->getIntensite(),
            'couleur' => $dream->getCouleur(),
            'emotions' => $dream->getEmotions(),
            'symboles' => $dream->getSymboles(),
            'recurrent' => $dream->getRecurrent(),
            // Compatibility keys for existing templates/controllers.
            'sleepSessionId' => $dream->getSommeilId()?->getId(),
            'sleepDate' => $dream->getSommeilId()?->getDateNuit()?->format('Y-m-d'),
            'title' => $dream->getTitre(),
            'dreamType' => $dream->getTypeReve(),
            'intensity' => $dream->getIntensite(),
            'isColor' => $dream->getCouleur(),
            'isRecurring' => $dream->getRecurrent(),
            'createdAt' => $dream->getCreatedAt()->format('c'),
            'updatedAt' => $dream->getUpdatedAt()->format('c'),
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            return null;
        }

        return (float) $value;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return false;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    private function dateValue(mixed $value): ?\DateTimeImmutable
    {
        if (!is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($string);
        } catch (\Exception) {
            return null;
        }
    }
}
