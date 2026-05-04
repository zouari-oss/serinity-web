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
    name: 'app:create-prediction-test-users',
    description: 'Create users to test prediction classification (0, 1, 2).',
)]
final class CreatePredictionTestUsersCommand extends Command
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
        $io->title('Creating prediction test users');

        $now = new \DateTimeImmutable();
        $created = 0;

        // Test prediction 0 - should show "Pred: 0" badge (no classification)
        $pred0User = [
            'email' => 'test_pred_0@serinity.org',
            'password' => 'testpass123',
            'username' => 'test_pred_0',
            'firstName' => 'Prediction',
            'lastName' => 'Zero',
            'riskLevel' => null,
            'riskConfidence' => 0.95,
            'riskPrediction' => 0,
        ];

        // Test prediction 1 - should show "MEDIUM" badge
        $pred1User = [
            'email' => 'test_pred_1@serinity.org',
            'password' => 'testpass123',
            'username' => 'test_pred_1',
            'firstName' => 'Prediction',
            'lastName' => 'One',
            'riskLevel' => 'MEDIUM',
            'riskConfidence' => 0.87,
            'riskPrediction' => 1,
        ];

        // Test prediction 2 - should show "DANGER" badge
        $pred2User = [
            'email' => 'test_pred_2@serinity.org',
            'password' => 'testpass123',
            'username' => 'test_pred_2',
            'firstName' => 'Prediction',
            'lastName' => 'Two',
            'riskLevel' => 'DANGER',
            'riskConfidence' => 0.92,
            'riskPrediction' => 2,
        ];

        foreach ([$pred0User, $pred1User, $pred2User] as $userData) {
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

        $io->success(sprintf('Created %d prediction test users:', $created));
        $io->table(
            ['Email', 'Username', 'Prediction', 'Risk Level', 'Confidence', 'Display'],
            [
                [
                    'test_pred_0@serinity.org',
                    'test_pred_0',
                    '0',
                    'null (no classification)',
                    '0.95',
                    'Pred: 0 (badge-info)',
                ],
                [
                    'test_pred_1@serinity.org',
                    'test_pred_1',
                    '1',
                    'MEDIUM',
                    '0.87',
                    'MEDIUM (badge-warning)',
                ],
                [
                    'test_pred_2@serinity.org',
                    'test_pred_2',
                    '2',
                    'DANGER',
                    '0.92',
                    'DANGER (badge-danger)',
                ],
            ],
        );

        return Command::SUCCESS;
    }
}
