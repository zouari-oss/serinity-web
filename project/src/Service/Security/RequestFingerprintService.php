<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\Request;

final class RequestFingerprintService
{
    public function build(Request $request): string
    {
        $ip = (string) ($request->getClientIp() ?? 'unknown');
        $userAgent = mb_substr((string) $request->headers->get('User-Agent', ''), 0, 200);

        return hash('sha256', $ip . '|' . $userAgent);
    }
}
