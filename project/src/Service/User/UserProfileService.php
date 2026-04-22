<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\Common\ServiceResult;
use App\Dto\User\UpdateProfileRequest;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Avatar\AvatarGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UserProfileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AvatarGenerator $avatarGenerator,
    ) {
    }

    /**
     * @return array{
     *     email:string,
     *     username:string,
     *     firstName:string,
     *     lastName:string,
     *     country:string,
     *     state:string,
     *     aboutMe:string,
     *     profileImageUrl:string,
     *     animeAvatarImage:string
     * }
     */
    public function toArray(User $user): array
    {
        $profile = $user->getProfile();

        return [
            'email' => $user->getEmail(),
            'username' => $profile?->getUsername() ?? '',
            'firstName' => $profile?->getFirstName() ?? '',
            'lastName' => $profile?->getLastName() ?? '',
            'country' => $profile?->getCountry() ?? '',
            'state' => $profile?->getState() ?? '',
            'aboutMe' => $profile?->getAboutMe() ?? '',
            'profileImageUrl' => $profile?->getProfileImageUrl() ?? '',
            'animeAvatarImage' => $profile?->getAnimeAvatarImage() ?? '',
        ];
    }

    public function update(User $user, UpdateProfileRequest $request): ServiceResult
    {
        if ($request->newPassword !== null || $request->confirmPassword !== null || $request->currentPassword !== null) {
            if (($request->currentPassword ?? '') === '') {
                return ServiceResult::failure('Current password is required to change password.');
            }
            if (($request->newPassword ?? '') === '' || ($request->confirmPassword ?? '') === '') {
                return ServiceResult::failure('New password and confirmation are required.');
            }
            if (!$this->passwordHasher->isPasswordValid($user, (string) $request->currentPassword)) {
                return ServiceResult::failure('Current password is invalid.');
            }
            if ($request->newPassword !== $request->confirmPassword) {
                return ServiceResult::failure('Password confirmation does not match.');
            }
        }

        $profile = $user->getProfile() ?? $this->createProfile($user);
        $user->setEmail(mb_strtolower(trim($request->email)));
        $profile->setUsername($this->nullable($request->username) ?? $profile->getUsername());
        $profile->setFirstName($this->nullable($request->firstName));
        $profile->setLastName($this->nullable($request->lastName));
        $profile->setCountry($this->nullable($request->country));
        $profile->setState($this->nullable($request->state));
        $profile->setAboutMe($this->nullable($request->aboutMe));

        $now = new \DateTimeImmutable();
        $user->setUpdatedAt($now);
        $profile->setUpdatedAt($now);

        if (($request->newPassword ?? '') !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $request->newPassword));
        }

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return ServiceResult::success('Profile updated successfully.', $this->toArray($user));
    }

    public function setProfileImage(User $user, string $imageUrl): void
    {
        $profile = $user->getProfile() ?? $this->createProfile($user);
        $normalizedImageUrl = trim($imageUrl);
        $profile
            ->setProfileImageUrl($normalizedImageUrl)
            ->setAnimeAvatarImage(null)
            ->setAnimeAvatarSourceHash(null)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($profile);
        $this->entityManager->flush();
    }

    public function getStoredAvatarIfFresh(User $user): ?string
    {
        $profile = $user->getProfile();
        if (!$profile instanceof Profile) {
            return null;
        }

        $imageUrl = trim((string) $profile->getProfileImageUrl());
        $storedAvatar = $profile->getAnimeAvatarImage();
        $storedHash = $profile->getAnimeAvatarSourceHash();
        if ($imageUrl === '' || !is_string($storedAvatar) || trim($storedAvatar) === '' || !is_string($storedHash)) {
            return null;
        }

        return hash_equals($storedHash, $this->avatarSourceHash($imageUrl)) ? $storedAvatar : null;
    }

    public function generateAndStoreAvatar(User $user): string
    {
        $profile = $user->getProfile();
        if (!$profile instanceof Profile) {
            throw new \InvalidArgumentException('Profile is required.');
        }

        $imageUrl = trim((string) $profile->getProfileImageUrl());
        if ($imageUrl === '') {
            throw new \InvalidArgumentException('Please upload a profile image first.');
        }

        $avatarImage = $this->avatarGenerator->generateFromProfileImageUrl($imageUrl);
        $profile
            ->setAnimeAvatarImage($avatarImage)
            ->setAnimeAvatarSourceHash($this->avatarSourceHash($imageUrl))
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $avatarImage;
    }

    public function deleteAccount(User $user, ?string $currentPassword, ?string $confirmText): ServiceResult
    {
        if (($currentPassword ?? '') === '') {
            return ServiceResult::failure('Current password is required.');
        }
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return ServiceResult::failure('Current password is invalid.');
        }
        if (($confirmText ?? '') !== 'DELETE') {
            return ServiceResult::failure('Type DELETE to confirm account deletion.');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return ServiceResult::success('Account deleted successfully.');
    }

    public function changePassword(User $user, ?string $currentPassword, ?string $newPassword, ?string $confirmPassword): ServiceResult
    {
        if (($currentPassword ?? '') === '') {
            return ServiceResult::failure('Current password is required.');
        }
        if (($newPassword ?? '') === '' || ($confirmPassword ?? '') === '') {
            return ServiceResult::failure('New password and confirmation are required.');
        }
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return ServiceResult::failure('Current password is invalid.');
        }
        if ($newPassword !== $confirmPassword) {
            return ServiceResult::failure('Password confirmation does not match.');
        }
        if (mb_strlen($newPassword) < 8) {
            return ServiceResult::failure('New password must be at least 8 characters.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ServiceResult::success('Password changed successfully.');
    }

    private function createProfile(User $user): Profile
    {
        $profile = new Profile();
        $now = new \DateTimeImmutable();
        $profile
            ->setId(Uuid::v4()->toRfc4122())
            ->setUser($user)
            ->setUsername($this->defaultUsername($user->getEmail()))
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        return $profile;
    }

    private function defaultUsername(string $email): string
    {
        return (string) preg_replace('/[^a-z0-9_]/', '_', strtolower(strtok($email, '@') ?: 'user'));
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function avatarSourceHash(string $imageUrl): string
    {
        return hash('sha256', trim($imageUrl));
    }
}
