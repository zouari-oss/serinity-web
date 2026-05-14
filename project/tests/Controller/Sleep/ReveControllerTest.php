<?php

namespace App\Tests\Controller\Sleep;

use App\Controller\Sleep\User\ReveController;
use App\Service\Sleep\LmStudioService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

final class ReveControllerTest extends TestCase
{
    private ReveController $controller;

    protected function setUp(): void
    {
        $this->controller = new ReveController();
        $this->controller->setContainer(new Container());
    }

    public function testGenerateDescriptionRejectsEmptyTitle(): void
    {
        $request = Request::create(
            '/reve/generate-description',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => ''
            ])
        );

        $lmStudioService = $this->createMock(LmStudioService::class);

        $response = $this->controller->generateDescription(
            $request,
            $lmStudioService
        );

        $data = json_decode($response->getContent(), true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame('Le titre est vide.', $data['message']);
    }

    public function testGenerateDescriptionReturnsGeneratedText(): void
    {
        $request = Request::create(
            '/reve/generate-description',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => 'Rêve dans une forêt'
            ])
        );

        $lmStudioService = $this->createMock(LmStudioService::class);

        $lmStudioService
            ->expects($this->once())
            ->method('generateDreamDescription')
            ->with('Rêve dans une forêt')
            ->willReturn('Description générée du rêve.');

        $response = $this->controller->generateDescription(
            $request,
            $lmStudioService
        );

        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame(
            'Description générée du rêve.',
            $data['description']
        );
    }
}