<?php

namespace App\Tests\Unit\Service\OurGame;

use App\Dto\OurGame\OurGameDownloadLinkRequest;
use App\Entity\Enum\DownloadPlatform;
use App\Entity\OurGame;
use App\Entity\OurGameDownloadLink;
use App\Service\OurGame\OurGameDownloadLinkCrudService;
use App\Service\OurGame\OurGameImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OurGameDownloadLinkCrudServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private OurGameImageStorage&MockObject $imageStorage;
    private OurGameDownloadLinkCrudService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->imageStorage = $this->createMock(OurGameImageStorage::class);

        $this->service = new OurGameDownloadLinkCrudService($this->entityManager, $this->imageStorage);
    }

    private function makeRequest(): OurGameDownloadLinkRequest
    {
        $request = new OurGameDownloadLinkRequest();
        $request->platform = 'windows';
        $request->url = 'https://example.test/download.exe';

        return $request;
    }

    public function testCreatePersistsLinkForGame(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(OurGameDownloadLink::class));
        $this->entityManager->expects($this->once())->method('flush');

        $link = $this->service->create($game, $this->makeRequest());

        self::assertSame($game, $link->getOurGame());
        self::assertSame(DownloadPlatform::Windows, $link->getPlatform());
        self::assertSame('https://example.test/download.exe', $link->getUrl());
    }

    public function testUpdateChangesPlatformAndUrl(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $link = new OurGameDownloadLink($game, DownloadPlatform::Web, 'https://example.test/old');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new OurGameDownloadLinkRequest();
        $request->platform = 'linux';
        $request->url = 'https://example.test/new';

        $updated = $this->service->update($link, $request);

        self::assertSame(DownloadPlatform::Linux, $updated->getPlatform());
        self::assertSame('https://example.test/new', $updated->getUrl());
    }

    public function testDeleteRemovesLinkAndItsImage(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $link = (new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe'))
            ->setImagePath('uploads/our_games/1/downloads/icon.png');

        $this->entityManager->expects($this->once())->method('remove')->with($link);
        $this->entityManager->expects($this->once())->method('flush');
        $this->imageStorage->expects($this->once())->method('remove')->with('uploads/our_games/1/downloads/icon.png');

        $this->service->delete($link);
    }
}
