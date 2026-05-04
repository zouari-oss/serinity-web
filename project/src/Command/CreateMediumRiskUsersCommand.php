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
    name: 'app:create-medium-risk-users',
    description: 'Create 3 users with safe medium risk parameters.',
)]
final class CreateMediumRiskUsersCommand extends Command
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
        $io->title('Creating 3 users with safe medium risk parameters');

        $now = new \DateTimeImmutable();
        $created = 0;

        $testUsers = [
            [
                'email' => 'medium_risk_1@serinity.org',
                'password' => 'testpass123',
                'username' => 'medium_risk_user_1',
                'firstName' => 'Medium',
                'lastName' => 'Risk User 1',
            ],
            [
                'email' => 'medium_risk_2@serinity.org',
                'password' => 'testpass123',
                'username' => 'medium_risk_user_2',
                'firstName' => 'Medium',
                'lastName' => 'Risk User 2',
            ],
            [
                'email' => 'medium_risk_3@serinity.org',
                'password' => 'testpass123',
                'username' => 'medium_risk_user_3',
                'firstName' => 'Medium',
                'lastName' => 'Risk User 3',
            ],
        ];

        foreach ($testUsers as $userData) {
            $existingUser = $this->userRepository->findByEmail($userData['email']);
            if ($existingUser !== null) {
                $io->warning(sprintf('User %s already exists, skipping.', $userData['email']));
                continue;
            }

            $user = (new User())
                ->setId(Uuid::v4()->toRfc4122())
                ->setEmail(mb_strtolower(trim($userData['email'])))
                ->setRole(UserRole::PATIENT->value)
                ->setPresenceStatus(PresenceStatus::OFFLINE->value)
                ->setAccountStatus(AccountStatus::ACTIVE->value)
                ->setFaceRecognitionEnabled(false)
                ->setRiskLevel('MEDIUM')
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $user->setPassword($this->passwordHasher->hashPassword($user, $userData['password']));

            $profile = (new Profile())
                ->setId(Uuid::v4()->toRfc4122())
                ->setUsername($userData['username'])
                ->setUser($user)
                ->setFirstName($userData['firstName'])
                ->setLastName($userData['lastName'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->entityManager->persist($user);
            $this->entityManager->persist($profile);
            ++$created;
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created %d users with safe medium risk parameters:', $created));
        $io->table(
            ['Email', 'Username', 'Risk Level', 'Risk Confidence', 'Risk Prediction'],
            [
                ['medium_risk_1@serinity.org', 'medium_risk_user_1', 'medium', '0.55', '50'],
                ['medium_risk_2@serinity.org', 'medium_risk_user_2', 'medium', '0.55', '50'],
                ['medium_risk_3@serinity.org', 'medium_risk_user_3', 'medium', '0.55', '50'],
            ],
        );

        return Command::SUCCESS;
    }
}
