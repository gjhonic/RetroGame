<?php

namespace App\Tests\Unit\Service\OurGame;

use App\Entity\OurGame;
use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameSlugGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AllowMockObjectsWithoutExpectations]
class OurGameSlugGeneratorTest extends TestCase
{
    private OurGameRepository&MockObject $ourGameRepository;
    private OurGameSlugGenerator $generator;

    protected function setUp(): void
    {
        $this->ourGameRepository = $this->createMock(OurGameRepository::class);
        $this->generator = new OurGameSlugGenerator(new AsciiSlugger(), $this->ourGameRepository);
    }

    public function testGenerateReturnsSlugifiedNameWhenFree(): void
    {
        $this->ourGameRepository->method('findOneBySlug')->willReturn(null);

        self::assertSame('die-again', $this->generator->generate('Die Again'));
    }

    public function testGenerateAppendsIncrementingSuffixOnCollision(): void
    {
        $existing = new OurGame('Die Again', 'die-again');
        $reflection = new \ReflectionProperty(OurGame::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($existing, 1);

        $this->ourGameRepository->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug) => $slug === 'die-again' ? $existing : null,
        );

        self::assertSame('die-again-2', $this->generator->generate('Die Again'));
    }

    public function testGenerateIgnoresCollisionWithTheGameBeingEdited(): void
    {
        $existing = new OurGame('Die Again', 'die-again');
        $reflection = new \ReflectionProperty(OurGame::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($existing, 5);

        $this->ourGameRepository->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug) => $slug === 'die-again' ? $existing : null,
        );

        self::assertSame('die-again', $this->generator->generate('Die Again', 5));
    }
}
