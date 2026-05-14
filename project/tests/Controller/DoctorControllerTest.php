<?php

namespace App\Tests\Controller;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DoctorControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSaveRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/user/rdv/save', [
            'doctor_id' => '1',
            'motif' => 'Consultation',
            'description' => 'Simple test payload',
            'dateTime' => '2026-07-10 10:00:00',
        ]);

        self::assertResponseRedirects();
    }
}
