<?php

namespace App\Tests\Service;

use App\Controller\MedicalAiConsultation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;

final class MedicalAiConsultationTest extends TestCase
{
    private function createController(): MedicalAiConsultation
    {
        $controller = new MedicalAiConsultation();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function createJsonRequest(array $payload): Request
    {
        return Request::create(
            '/user/medical-ai/analyse',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    public function testAnalyseReturnsBadRequestWhenMessageIsEmpty(): void
    {
        $controller = $this->createController();
        $request = $this->createJsonRequest(['message' => '']);
        $response = $controller->analyse($request, new MockHttpClient());

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($data['success']);
        $this->assertSame('Empty message.', $data['message']);
    }
}
