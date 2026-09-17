<?php

namespace App\Tests\Unit\Service\Plati;

use App\Service\Plati\Exceptions\PlatiApiException;
use App\Service\Plati\PlatiClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class PlatiClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private PlatiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new PlatiClient($this->httpClient);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mockResponse(array $data): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);

        return $response;
    }

    public function testSearchMapsItemsFromResponse(): void
    {
        $this->httpClient->expects($this->once())->method('request')
            ->with('GET', 'https://api.digiseller.ru/api/products/search2', $this->callback(
                static fn (array $options): bool => $options['query'] === [
                    'query' => 'Garry\'s Mod',
                    'pagesize' => 10,
                    'pagenum' => 1,
                    'owner' => 1,
                    'lang' => 'ru',
                ],
            ))
            ->willReturn($this->mockResponse([
                'items' => [
                    'item' => [
                        [
                            'id' => '2982996',
                            'name' => "Garry's Mod STEAM•RU",
                            'name_eng' => "Garry's Mod STEAM RU",
                            'url' => 'https://plati.market/itm/2982996',
                            'cnt_sell' => '3143',
                            'seller_name' => 'DarkAwe',
                            'price_rur' => '174',
                        ],
                    ],
                ],
            ]));

        $items = $this->client->search("Garry's Mod", 10);

        self::assertCount(1, $items);
        self::assertSame('2982996', $items[0]->id);
        self::assertSame("Garry's Mod STEAM•RU", $items[0]->name);
        self::assertSame('https://plati.market/itm/2982996', $items[0]->url);
        self::assertSame(3143, $items[0]->cntSell);
        self::assertSame('DarkAwe', $items[0]->sellerName);
        self::assertSame(174, $items[0]->priceRur);
    }

    public function testSearchMapsMissingSellerNameToEmptyString(): void
    {
        $this->httpClient->method('request')->willReturn($this->mockResponse([
            'items' => ['item' => [[
                'id' => '1',
                'name' => 'Item Without Seller',
                'name_eng' => 'Item Without Seller',
                'url' => 'https://plati.market/itm/1',
                'cnt_sell' => '1',
            ]]],
        ]));

        $items = $this->client->search('Item Without Seller', 10);

        self::assertSame('', $items[0]->sellerName);
    }

    public function testSearchMapsMissingPriceRurToNull(): void
    {
        $this->httpClient->method('request')->willReturn($this->mockResponse([
            'items' => ['item' => [[
                'id' => '1',
                'name' => 'Item Without Price',
                'name_eng' => 'Item Without Price',
                'url' => 'https://plati.market/itm/1',
                'cnt_sell' => '1',
                'credit_price' => null,
            ]]],
        ]));

        $items = $this->client->search('Item Without Price', 10);

        self::assertNull($items[0]->priceRur);
    }

    public function testSearchReturnsEmptyArrayWhenNoItemsInResponse(): void
    {
        $this->httpClient->method('request')->willReturn($this->mockResponse(['items' => ['item' => []]]));

        self::assertSame([], $this->client->search('Unknown Game', 10));
    }

    public function testSearchReturnsEmptyArrayWhenItemsKeyMissing(): void
    {
        $this->httpClient->method('request')->willReturn($this->mockResponse(['result' => ['total' => 0]]));

        self::assertSame([], $this->client->search('Unknown Game', 10));
    }

    public function testSearchThrowsPlatiApiExceptionOnTransportError(): void
    {
        $this->httpClient->method('request')->willThrowException(
            $this->createMock(TransportExceptionInterface::class),
        );

        $this->expectException(PlatiApiException::class);

        $this->client->search('Half-Life', 10);
    }
}
