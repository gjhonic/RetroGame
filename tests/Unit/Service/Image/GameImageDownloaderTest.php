<?php

namespace App\Tests\Unit\Service\Image;

use App\Service\Image\GameImageDownloader;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class GameImageDownloaderTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private Filesystem&MockObject $filesystem;
    private GameImageDownloader $downloader;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);

        $this->downloader = new GameImageDownloader($this->httpClient, $this->filesystem, '/app/public');
    }

    public function testDownloadCoverSavesFileAndReturnsRelativePath(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('binary-image-data');
        $this->httpClient->method('request')->willReturn($response);

        $this->filesystem->expects($this->once())->method('mkdir')->with('/app/public/uploads/games');
        $this->filesystem->expects($this->once())->method('dumpFile')
            ->with('/app/public/uploads/games/70.jpg', 'binary-image-data');

        $path = $this->downloader->downloadCover('https://example.test/70/header.jpg', 70);

        self::assertSame('uploads/games/70.jpg', $path);
    }

    public function testDownloadCoverGuessesExtensionFromUrl(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('data');
        $this->httpClient->method('request')->willReturn($response);

        $path = $this->downloader->downloadCover('https://example.test/covers/70.png?t=123', 70);

        self::assertSame('uploads/games/70.png', $path);
    }

    public function testDownloadCoverDefaultsToJpgWhenUrlHasNoExtension(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('data');
        $this->httpClient->method('request')->willReturn($response);

        $path = $this->downloader->downloadCover('https://example.test/covers/no-extension', 70);

        self::assertSame('uploads/games/70.jpg', $path);
    }

    public function testDownloadCoverReturnsNullOnTransportException(): void
    {
        $this->httpClient->method('request')
            ->willThrowException($this->createMock(TransportExceptionInterface::class));

        $this->filesystem->expects($this->never())->method('dumpFile');

        $path = $this->downloader->downloadCover('https://example.test/broken.jpg', 70);

        self::assertNull($path);
    }
}
