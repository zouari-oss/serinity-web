<?php

namespace App\Tests\Service;

use App\Controller\RapportController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;

final class RapportServiceTest extends TestCase
{
    private function createController(): RapportController
    {
        $controller = new RapportController();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function createPostRequest(array $data): Request
    {
        return Request::create('/user/consultation/translate', 'POST', $data);
    }

    public function testTranslateConsultationReturnsErrorWhenTextIsEmpty(): void
    {
        $controller = $this->createController();
        $request = $this->createPostRequest([
            'text' => '',
            'lang' => 'en',
        ]);

        $response = $controller->translateConsultation($request, new MockHttpClient());

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($data['success']);
        $this->assertSame('Empty text', $data['message']);
    }
}
