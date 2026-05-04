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
    name: 'app:create-risk-examples',
    description: 'Create 2 example users: one safe (low risk) and one risky (high risk).',
)]
final class CreateRiskExampleUsersCommand extends Command
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
        $io->title('Creating risk example users');

        $now = new \DateTimeImmutable();
        $created = 0;

        // Safe user: Low risk - typical normal behavior
        $safeUser = [
            'email' => 'safe_user@serinity.org',
            'password' => 'safepass123',
            'username' => 'safe_user',
            'firstName' => 'Safe',
            'lastName' => 'User',
            'riskLevel' => 'SAFE',
            'riskConfidence' => 0.95,
            'riskPrediction' => 0,
        ];

        // Risky user: High risk - suspicious behavior pattern
        $riskyUser = [
            'email' => 'risky_user@serinity.org',
            'password' => 'riskypass123',
            'username' => 'risky_user',
            'firstName' => 'Risky',
            'lastName' => 'User',
            'riskLevel' => 'DANGER',
            'riskConfidence' => 0.90,
            'riskPrediction' => 2,
        ];

        foreach ([$safeUser, $riskyUser] as $userData) {
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
                ->setRiskLevel($userData['riskLevel'])
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

        $io->success(sprintf('Created %d example users:', $created));
        $io->table(
            ['Email', 'Username', 'Risk Level', 'Confidence', 'Prediction', 'Example Behavior'],
            [
                [
                    'safe_user@serinity.org',
                    'safe_user',
                    'low',
                    '0.95',
                    '0',
                    'No IP/device changes, daytime login, consistent OS',
                ],
                [
                    'risky_user@serinity.org',
                    'risky_user',
                    'high',
                    '0.90',
                    '2',
                    'Multiple IP/device changes, night login, OS variations',
                ],
            ],
        );

        $io->newLine();
        $io->section('Risk Prediction Examples');
        $io->text('Safe User Behavior (Low Risk - prediction: 0):');
        $io->listing([
            'session_duration: 3600s (normal)',
            'is_revoked: 0',
            'ip_change_count: 0',
            'device_change_count: 0',
            'location_change: 0',
            'login_hour: 14 (afternoon)',
            'is_night_login: 0',
            'os_variation: 0',
            'Expected: probabilities [0.95, 0.04, 0.01]',
        ]);

        $io->text('Risky User Behavior (High Risk - prediction: 2):');
        $io->listing([
            'session_duration: 180s (very short)',
            'is_revoked: 0',
            'ip_change_count: 5+ (multiple locations)',
            'device_change_count: 3+ (multiple devices)',
            'location_change: 1 (significant change)',
            'login_hour: 3 (middle of night)',
            'is_night_login: 1',
            'os_variation: 5+ (many OS changes)',
            'Expected: probabilities [0.02, 0.08, 0.90]',
        ]);

        return Command::SUCCESS;
    }
}
