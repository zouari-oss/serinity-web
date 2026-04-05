<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Profile;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Enum\PresenceStatus;
use App\Enum\UserRole;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:create-test-users',
    description: 'Create reproducible users for admin dashboard testing.',
)]
final class CreateTestUsersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Creating test users');

        $created = 0;
        $skipped = 0;

        [$wasCreated, ] = $this->createUser(
            email: 'admin@serinity.org',
            plainPassword: 'admin123',
            role: UserRole::ADMIN,
            accountStatus: AccountStatus::ACTIVE,
            username: 'admin_user',
            firstName: 'Admin',
            lastName: 'User',
            country: 'Tunisia',
        );
        $wasCreated ? ++$created : ++$skipped;

        for ($i = 1; $i <= 5; ++$i) {
            [$therapistCreated, ] = $this->createUser(
                email: sprintf('therapist%d@serinity.org', $i),
                plainPassword: 'password123',
                role: UserRole::THERAPIST,
                accountStatus: $i <= 3 ? AccountStatus::ACTIVE : AccountStatus::DISABLED,
                username: sprintf('therapist_%d', $i),
                firstName: 'Therapist',
                lastName: sprintf('Number %d', $i),
            );
            $therapistCreated ? ++$created : ++$skipped;
        }

        for ($i = 1; $i <= 15; ++$i) {
            [$patientCreated, ] = $this->createUser(
                email: sprintf('patient%d@serinity.org', $i),
                plainPassword: 'password123',
                role: UserRole::PATIENT,
                accountStatus: $i <= 12 ? AccountStatus::ACTIVE : AccountStatus::DISABLED,
                username: sprintf('patient_%d', $i),
                firstName: $i % 2 === 0 ? 'Patient' : null,
                lastName: $i % 2 === 0 ? sprintf('Number %d', $i) : null,
            );
            $patientCreated ? ++$created : ++$skipped;
        }

        $this->entityManager->flush();

        $io->success(sprintf('Completed: %d created, %d skipped (already existed).', $created, $skipped));
        $io->table(
            ['Role', 'Credentials'],
            [
                ['Admin', 'admin@serinity.org / admin123'],
                ['Therapist sample', 'therapist1@serinity.org / password123'],
                ['Patient sample', 'patient1@serinity.org / password123'],
            ],
        );

        return Command::SUCCESS;
    }

    /**
     * @return array{0: bool, 1: User}
     */
    private function createUser(
        string $email,
        string $plainPassword,
        UserRole $role,
        AccountStatus $accountStatus,
        string $username,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $country = null,
    ): array {
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            return [false, $existingUser];
        }

        $now = new \DateTimeImmutable();

        $user = (new User())
            ->setId(Uuid::v4()->toRfc4122())
            ->setEmail(mb_strtolower(trim($email)))
            ->setRole($role->value)
            ->setPresenceStatus(PresenceStatus::OFFLINE->value)
            ->setAccountStatus($accountStatus->value)
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $profile = (new Profile())
            ->setId(Uuid::v4()->toRfc4122())
            ->setUsername($this->ensureUniqueUsername($username))
            ->setUser($user)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setCountry($country)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);

        return [true, $user];
    }

    private function ensureUniqueUsername(string $baseUsername): string
    {
        $username = $baseUsername;
        $counter = 1;

        while ($this->profileRepository->findOneBy(['username' => $username]) !== null) {
            ++$counter;
            $username = sprintf('%s_%d', $baseUsername, $counter);
        }

        return $username;
    }
}
