<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Common\ServiceResult;
use App\Entity\User;
use App\Security\TwoFactor\TotpUserContext;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

final readonly class TwoFactorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private TwoFactorCryptoService $twoFactorCryptoService,
    ) {
    }

    public function startSetup(User $user): ServiceResult
    {
        $secret = $this->totpAuthenticator->generateSecret();
        $encryptedSecret = $this->twoFactorCryptoService->encryptSecret($secret);

        $user
            ->setTotpSecretEncrypted($encryptedSecret)
            ->setTwoFactorEnabled(false)
            ->setTotpEnabledAt(null)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $qrContent = $this->totpAuthenticator->getQRContent(
            new TotpUserContext($user->getEmail(), $secret),
        );
        $writer = new SvgWriter();
        $qrCode = new QrCode(
            data: $qrContent,
            size: 260,
            margin: 10,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $qrImage = $writer->write($qrCode)->getDataUri();

        return ServiceResult::success('Two-factor setup initialized.', [
            'qrContent' => $qrContent,
            'qrCode' => $qrImage,
            'isTwoFactorEnabled' => false,
        ]);
    }

    public function verifySetup(User $user, string $code): ServiceResult
    {
        if (!$this->isCodeValid($user, $code)) {
            return ServiceResult::failure('Invalid authentication code.', [
                'error' => 'invalid_2fa_code',
            ]);
        }

        $user
            ->setTwoFactorEnabled(true)
            ->setTotpEnabledAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ServiceResult::success('Two-factor authentication enabled.', [
            'isTwoFactorEnabled' => true,
        ]);
    }

    public function disable(User $user): ServiceResult
    {
        $user
            ->setTotpSecretEncrypted(null)
            ->setTwoFactorEnabled(false)
            ->setTotpEnabledAt(null)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ServiceResult::success('Two-factor authentication disabled.', [
            'isTwoFactorEnabled' => false,
        ]);
    }

    public function isCodeValid(User $user, string $code): bool
    {
        $secret = $this->resolveSecret($user);
        if ($secret === null || trim($code) === '') {
            return false;
        }

        return $this->totpAuthenticator->checkCode(
            new TotpUserContext($user->getEmail(), $secret),
            trim($code),
        );
    }

    private function resolveSecret(User $user): ?string
    {
        $encryptedSecret = $user->getTotpSecretEncrypted();
        if (!is_string($encryptedSecret) || $encryptedSecret === '') {
            return null;
        }

        return $this->twoFactorCryptoService->decryptSecret($encryptedSecret);
    }
}
