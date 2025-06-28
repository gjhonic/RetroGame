<?php

namespace App\Tests\Service;

use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\Shop;
use App\Service\SteamGameDataProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class SteamGameDataProcessorTest extends TestCase
{
    private SteamGameDataProcessor $processor;
    private EntityManagerInterface $entityManager;
    private OutputInterface $output;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->processor = new SteamGameDataProcessor($this->entityManager);
        $this->output = $this->createMock(OutputInterface::class);
    }

    public function testProcessGameDataWithValidGameData(): void
    {
        // Подготавливаем тестовые данные
        $detailsData = [
            '730' => [
                'success' => true,
                'data' => [
                    'type' => 'game',
                    'name' => 'Counter-Strike 2',
                    'short_description' => 'Counter-Strike 2 is the largest technical leap forward in Counter-Strike history.',
                    'genres' => [
                        ['description' => 'Action'],
                        ['description' => 'FPS']
                    ],
                    'price_overview' => [
                        'final' => 0,
                        'currency' => 'RUB'
                    ],
                    'is_free' => true,
                    'header_image' => 'https://example.com/image.jpg',
                    'release_date' => [
                        'date' => '2023-09-27'
                    ],
                    'recommendations' => [
                        'total' => 1000000
                    ]
                ]
            ]
        ];

        // Мокаем репозитории
        $shopRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $gameRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $genreRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        // Настраиваем моки
        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [Shop::class, $shopRepository],
                [Game::class, $gameRepository],
                [Genre::class, $genreRepository]
            ]);

        // Магазин Steam существует
        $shop = new Shop();
        $shop->setName('Steam');
        $shopRepository->method('find')->with(1)->willReturn($shop);

        // Игра не существует
        $gameRepository->method('findOneBy')->willReturn(null);

        // Жанры не существуют
        $genreRepository->method('findOneBy')->willReturn(null);

        // Ожидаем, что persist будет вызван для Game, Genre и GameShop
        $this->entityManager->expects($this->atLeast(3))
            ->method('persist');

        // Создаем переменные для массивов, передаваемых по ссылке
        $existingGameNames = [];
        $existingGameShopIds = [];

        // Выполняем тест
        $result = $this->processor->processGameData(
            $detailsData,
            $this->output,
            $existingGameNames,
            $existingGameShopIds
        );

        // Проверяем результат
        $this->assertTrue($result);
    }

    public function testProcessGameDataWithInvalidGameData(): void
    {
        // Подготавливаем невалидные данные
        $detailsData = [
            '730' => [
                'success' => false,
                'data' => null
            ]
        ];

        // Создаем переменные для массивов, передаваемых по ссылке
        $existingGameNames = [];
        $existingGameShopIds = [];

        // Выполняем тест
        $result = $this->processor->processGameData(
            $detailsData,
            $this->output,
            $existingGameNames,
            $existingGameShopIds
        );

        // Проверяем результат
        $this->assertFalse($result);
    }

    public function testProcessGameDataWithNonGameApp(): void
    {
        // Подготавливаем данные для не-игры
        $detailsData = [
            '730' => [
                'success' => true,
                'data' => [
                    'type' => 'dlc',
                    'name' => 'Some DLC',
                    'short_description' => 'Some description',
                    'genres' => [],
                    'price_overview' => null
                ]
            ]
        ];

        // Мокаем репозиторий магазина
        $shopRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager->method('getRepository')
            ->with(Shop::class)
            ->willReturn($shopRepository);

        $shop = new Shop();
        $shop->setName('Steam');
        $shopRepository->method('find')->with(1)->willReturn($shop);

        // Создаем переменные для массивов, передаваемых по ссылке
        $existingGameNames = [];
        $existingGameShopIds = [];

        // Выполняем тест
        $result = $this->processor->processGameData(
            $detailsData,
            $this->output,
            $existingGameNames,
            $existingGameShopIds
        );

        // Проверяем результат
        $this->assertFalse($result);
    }

    public function testProcessGameDataWithExistingGameShop(): void
    {
        // Подготавливаем валидные данные
        $detailsData = [
            '730' => [
                'success' => true,
                'data' => [
                    'type' => 'game',
                    'name' => 'Counter-Strike 2',
                    'short_description' => 'Description',
                    'genres' => [['description' => 'Action']],
                    'price_overview' => ['final' => 0],
                    'is_free' => true,
                    'release_date' => ['date' => '2023-09-27'],
                    'recommendations' => ['total' => 1000000]
                ]
            ]
        ];

        // Мокаем репозиторий магазина
        $shopRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager->method('getRepository')
            ->with(Shop::class)
            ->willReturn($shopRepository);

        $shop = new Shop();
        $shop->setName('Steam');
        $shopRepository->method('find')->with(1)->willReturn($shop);

        // GameShop уже существует
        $existingGameNames = [];
        $existingGameShopIds = [730 => true];

        // Выполняем тест
        $result = $this->processor->processGameData(
            $detailsData,
            $this->output,
            $existingGameNames,
            $existingGameShopIds
        );

        // Проверяем результат
        $this->assertFalse($result);
    }

    public function testIsGameMethod(): void
    {
        $processor = new SteamGameDataProcessor($this->entityManager);

        // Тест валидной игры
        $validGameData = [
            'type' => 'game',
            'short_description' => 'Description',
            'genres' => [['description' => 'Action']],
            'price_overview' => ['final' => 0]
        ];
        $this->assertTrue($processor->isGame($validGameData));

        // Тест не-игры
        $invalidGameData = [
            'type' => 'dlc',
            'short_description' => 'Description',
            'genres' => [],
            'price_overview' => null
        ];
        $this->assertFalse($processor->isGame($invalidGameData));
    }

    public function testGetOwnersCountMethod(): void
    {
        $processor = new SteamGameDataProcessor($this->entityManager);

        // Тест с рекомендациями
        $gameDataWithRecommendations = [
            'recommendations' => ['total' => 1000000]
        ];
        $this->assertEquals(1000000, $processor->getOwnersCount($gameDataWithRecommendations));

        // Тест без рекомендаций
        $gameDataWithoutRecommendations = [];
        $this->assertNull($processor->getOwnersCount($gameDataWithoutRecommendations));
    }
}
