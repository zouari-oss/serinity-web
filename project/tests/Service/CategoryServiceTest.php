<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Category;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\String\Slugger\SluggerInterface;

class CategoryServiceTest extends TestCase
{
    public function testSaveGeneratesSlugWhenMissing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $category = $this->createMock(Category::class);

        $category->expects($this->once())
            ->method('getSlug')
            ->willReturn(null);
        $category->expects($this->once())
            ->method('getName')
            ->willReturn('My Category');
        $category->expects($this->once())
            ->method('setSlug')
            ->with('my-category');

        $slugger->expects($this->once())
            ->method('slug')
            ->with('My Category')
            ->willReturn(new UnicodeString('My-Category'));

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($category);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new CategoryService($entityManager, $slugger);
        $service->save($category);
    }

    public function testSaveDoesNotOverrideExistingSlug(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $category = $this->createMock(Category::class);

        $category->expects($this->once())
            ->method('getSlug')
            ->willReturn('existing-slug');
        $category->expects($this->never())
            ->method('setSlug');
        $slugger->expects($this->never())
            ->method('slug');

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($category);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new CategoryService($entityManager, $slugger);
        $service->save($category);
    }

    public function testSaveGeneratesSlugWhenSlugIsEmptyString(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $category = $this->createMock(Category::class);

        $category->expects($this->once())
            ->method('getSlug')
            ->willReturn('');
        $category->expects($this->once())
            ->method('getName')
            ->willReturn('Another Category');
        $category->expects($this->once())
            ->method('setSlug')
            ->with('another-category');

        $slugger->expects($this->once())
            ->method('slug')
            ->with('Another Category')
            ->willReturn(new UnicodeString('Another-Category'));

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($category);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new CategoryService($entityManager, $slugger);
        $service->save($category);
    }

    public function testSaveCastsNullNameToEmptyString(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $category = $this->createMock(Category::class);

        $category->expects($this->once())
            ->method('getSlug')
            ->willReturn(null);
        $category->expects($this->once())
            ->method('getName')
            ->willReturn(null);
        $category->expects($this->once())
            ->method('setSlug')
            ->with('');

        $slugger->expects($this->once())
            ->method('slug')
            ->with('')
            ->willReturn(new UnicodeString(''));

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($category);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new CategoryService($entityManager, $slugger);
        $service->save($category);
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $category = $this->createMock(Category::class);

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($category);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new CategoryService($entityManager, $slugger);
        $service->delete($category);
    }
}
