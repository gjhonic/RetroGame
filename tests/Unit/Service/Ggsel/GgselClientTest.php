<?php

namespace App\Tests\Unit\Service\Ggsel;

use App\Service\Ggsel\Exceptions\GgselApiException;
use App\Service\Ggsel\GgselClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class GgselClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private GgselClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new GgselClient($this->httpClient);
    }

    /** Минимальная разметка страницы товара — то, что реально парсит GgselClient. */
    private static function productPageHtml(int $offerCount, string $canonicalUrl): string
    {
        return sprintf(
            '<html><head><link rel="canonical" href="%s"/>'
            . '<script type="application/ld+json">{"@context":"https://schema.org/","@type":"Product",'
            . '"name":"Resident Evil 2 Remake","offers":{"@type":"AggregateOffer","url":"%s","offerCount":%d}}</script>'
            . '</head><body></body></html>',
            $canonicalUrl,
            $canonicalUrl,
            $offerCount,
        );
    }

    private function mockResponse(int $statusCode, string $content = ''): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($content);

        return $response;
    }

    public function testFindBySlugReturnsFoundAttemptWithCanonicalUrlWhenPageHasNonEmptyOffer(): void
    {
        $html = self::productPageHtml(3, 'https://ggsel.net/catalog/resident-evil-2');
        $this->httpClient->expects($this->once())->method('request')
            ->with('GET', 'https://ggsel.net/catalog/resident-evil-2', $this->anything())
            ->willReturn($this->mockResponse(200, $html));

        $attempt = $this->client->findBySlug('resident-evil-2');

        self::assertSame('https://ggsel.net/catalog/resident-evil-2', $attempt->url);
        self::assertTrue($attempt->isFound());
        self::assertSame('https://ggsel.net/catalog/resident-evil-2', $attempt->product?->url);
        self::assertSame('найдено', $attempt->reason);
    }

    public function testFindBySlugReturnsHttpStatusReasonOnNon200Status(): void
    {
        $this->httpClient->method('request')->willReturn($this->mockResponse(404));

        $attempt = $this->client->findBySlug('unknown-game');

        self::assertFalse($attempt->isFound());
        self::assertSame('HTTP 404', $attempt->reason);
    }

    public function testFindBySlugReturnsReasonWhenNoProductJsonLdPresent(): void
    {
        $html = '<html><body>not found</body></html>';
        $this->httpClient->method('request')->willReturn($this->mockResponse(200, $html));

        $attempt = $this->client->findBySlug('unknown-game');

        self::assertFalse($attempt->isFound());
        self::assertStringContainsString('нет данных о товаре', $attempt->reason);
    }

    public function testFindBySlugReturnsReasonWhenOfferCountIsZero(): void
    {
        $html = self::productPageHtml(0, 'https://ggsel.net/catalog/empty-category');
        $this->httpClient->method('request')->willReturn($this->mockResponse(200, $html));

        $attempt = $this->client->findBySlug('empty-category');

        self::assertFalse($attempt->isFound());
        self::assertStringContainsString('offerCount = 0', $attempt->reason);
    }

    public function testFindBySlugFallsBackToOffersUrlWhenNoCanonicalLink(): void
    {
        $html = '<html><head>'
            . '<script type="application/ld+json">{"@type":"Product","offers":'
            . '{"@type":"AggregateOffer","url":"https://ggsel.net/catalog/from-offers","offerCount":1}}</script>'
            . '</head></html>';
        $this->httpClient->method('request')->willReturn($this->mockResponse(200, $html));

        $attempt = $this->client->findBySlug('from-offers');

        self::assertTrue($attempt->isFound());
        self::assertSame('https://ggsel.net/catalog/from-offers', $attempt->product?->url);
    }

    public function testFindBySlugThrowsGgselApiExceptionOnTransportError(): void
    {
        $this->httpClient->method('request')->willThrowException(
            $this->createMock(TransportExceptionInterface::class),
        );

        $this->expectException(GgselApiException::class);

        $this->client->findBySlug('resident-evil-2');
    }
}
