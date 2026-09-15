<?php

namespace App\Tests\Unit\Service\Steam;

use App\Service\Steam\Exceptions\SteamApiException;
use App\Service\Steam\SteamClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class SteamClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private SteamClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new SteamClient($this->httpClient, 'test-api-key');
    }

    public function testFetchAppDetailsReturnsDataFromFirstRequestWithoutRetrying(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([70 => ['success' => true, 'data' => ['name' => 'Half-Life']]]);

        $this->httpClient->expects($this->once())->method('request')
            ->with('GET', $this->anything(), $this->callback(
                static fn (array $options): bool => !array_key_exists('cc', $options['query']),
            ))
            ->willReturn($response);

        $data = $this->client->fetchAppDetails(70);

        self::assertSame(['name' => 'Half-Life'], $data);
    }

    public function testFetchAppDetailsRetriesWithUsRegionWhenFirstRequestHasNoData(): void
    {
        $failedResponse = $this->createMock(ResponseInterface::class);
        $failedResponse->method('toArray')->willReturn([70 => ['success' => false]]);

        $successResponse = $this->createMock(ResponseInterface::class);
        $successResponse->method('toArray')->willReturn([70 => ['success' => true, 'data' => ['name' => 'Half-Life']]]);

        $this->httpClient->expects($this->exactly(2))->method('request')
            ->willReturnCallback(static function (
                string $method,
                string $url,
                array $options,
            ) use (
                $failedResponse,
                $successResponse,
            ) {
                if (!array_key_exists('cc', $options['query'])) {
                    return $failedResponse;
                }

                self::assertSame('us', $options['query']['cc']);

                return $successResponse;
            });

        $data = $this->client->fetchAppDetails(70);

        self::assertSame(['name' => 'Half-Life'], $data);
    }

    public function testFetchAppDetailsReturnsNullWhenBothRegionsHaveNoData(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([70 => ['success' => false]]);

        $this->httpClient->expects($this->exactly(2))->method('request')->willReturn($response);

        $data = $this->client->fetchAppDetails(70);

        self::assertNull($data);
    }

    public function testFetchAppDetailsThrowsOnTransportErrorWithoutRetrying(): void
    {
        $this->httpClient->expects($this->once())->method('request')
            ->willThrowException($this->createMock(TransportExceptionInterface::class));

        $this->expectException(SteamApiException::class);

        $this->client->fetchAppDetails(70);
    }

    public function testFetchAppDetailsForRussiaRequestsWithRuRegion(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([70 => ['success' => true, 'data' => ['name' => 'Half-Life']]]);

        $this->httpClient->expects($this->once())->method('request')
            ->with('GET', $this->anything(), $this->callback(
                static fn (array $options): bool => $options['query']['cc'] === 'ru',
            ))
            ->willReturn($response);

        $data = $this->client->fetchAppDetailsForRussia(70);

        self::assertSame(['name' => 'Half-Life'], $data);
    }

    public function testFetchAppDetailsForRussiaReturnsNullWithoutRetryingOtherRegion(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([70 => ['success' => false]]);

        $this->httpClient->expects($this->once())->method('request')->willReturn($response);

        $data = $this->client->fetchAppDetailsForRussia(70);

        self::assertNull($data);
    }

    public function testFetchAppDetailsForRussiaThrowsOnTransportError(): void
    {
        $this->httpClient->expects($this->once())->method('request')
            ->willThrowException($this->createMock(TransportExceptionInterface::class));

        $this->expectException(SteamApiException::class);

        $this->client->fetchAppDetailsForRussia(70);
    }

    public function testFetchGameAppListReturnsPageData(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'response' => [
                'apps' => [['appid' => 70, 'name' => 'Half-Life']],
                'have_more_results' => true,
                'last_appid' => 70,
            ],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $page = $this->client->fetchGameAppList(100, 10);

        self::assertSame([['appid' => 70, 'name' => 'Half-Life']], $page['apps']);
        self::assertTrue($page['hasMore']);
        self::assertSame(70, $page['lastAppId']);
    }

    public function testFetchGameAppListThrowsSteamApiExceptionOnTransportErrorInsteadOfCrashing(): void
    {
        $this->httpClient->expects($this->once())->method('request')
            ->willThrowException($this->createMock(TransportExceptionInterface::class));

        $this->expectException(SteamApiException::class);

        $this->client->fetchGameAppList(100, 10);
    }
}
