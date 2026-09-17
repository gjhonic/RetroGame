<?php

namespace App\Service\Steam;

use App\Entity\Game;
use App\Entity\GamePrice;
use App\Entity\SteamGame;
use App\Repository\GamePriceRepository;
use App\Repository\SteamGameRepository;
use App\Repository\SteamPriceImportCursorRepository;
use App\Service\Steam\Exceptions\SteamApiException;
use App\Service\Steam\Interfaces\RandomDelayRangeInterface;
use App\Service\Steam\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Импорт цен игр из Steam (регион RU) — ежедневный снимок цены в GamePrice.
 * Единственное место, где происходит поход в Steam и сохранение снимка —
 * fetchAndStorePrice(): и пачечный importNextBatch() (крон
 * app:games:import-prices), и точечный importPriceForGame() (кнопка в
 * админке, тесты) вызывают именно его — логика не дублируется.
 */
class PriceImportService
{
    /** Принимает все зависимости, нужные для импорта цен и сохранения снимков. */
    public function __construct(
        private readonly SteamClient $steamClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly SteamGameRepository $steamGameRepository,
        private readonly GamePriceRepository $gamePriceRepository,
        private readonly SteamPriceImportCursorRepository $cursorRepository,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly RandomDelayRangeInterface $randomDelayRange,
    ) {
    }

    /**
     * Импортирует цены следующей пачки игр, продолжая с сохранённого
     * курсора. Первый запуск за календарный день сбрасывает курсор в
     * начало списка (см. SteamPriceImportCursor::reset()) — каждый день
     * начинаем заново с самых популярных игр, а не докручиваем вчерашний
     * круг: игры, до которых очередь за день не дошла, просто остаются
     * без свежего снимка на этот день. Пауза между запросами — случайная
     * величина в диапазоне [$minDelayMs, $maxDelayMs], чтобы не долбить
     * Steam ровным ритмом.
     */
    public function importNextBatch(int $limit, int $minDelayMs, int $maxDelayMs): PriceImportResult
    {
        $cursor = $this->cursorRepository->getOrCreate();
        $today = new \DateTimeImmutable('today');

        $startedNewDay = $cursor->getUpdatedAt() < $today;
        if ($startedNewDay) {
            $cursor->reset();
            $this->entityManager->flush();
        }

        $steamGames = $this->steamGameRepository->findBatchForPriceImport(
            $cursor->getLastPopularity(),
            $cursor->getLastSteamGameId(),
            $limit,
        );

        if ($steamGames === []) {
            return new PriceImportResult(
                prices: [],
                lastSteamGameId: $cursor->getLastSteamGameId(),
                startedNewDay: $startedNewDay,
            );
        }

        $prices = [];
        $skipped = 0;

        foreach ($steamGames as $i => $steamGame) {
            $price = $this->fetchAndStorePrice($steamGame, $today);
            if ($price === null) {
                ++$skipped;
            } else {
                $prices[] = $price;
            }

            if ($i !== array_key_last($steamGames)) {
                $this->rateLimiter->delay($this->randomDelayRange->next($minDelayMs, $maxDelayMs));
            }
        }

        $lastSteamGame = end($steamGames);
        $lastId = $lastSteamGame->getId();
        assert($lastId !== null);
        $lastGame = $lastSteamGame->getGame();
        assert($lastGame instanceof Game);
        $lastPopularity = $lastGame->getPopularity() ?? -1;

        $cursor->setPosition($lastPopularity, $lastId);
        $this->entityManager->flush();

        return new PriceImportResult($prices, $skipped, $lastId, $startedNewDay, $lastPopularity);
    }

    /**
     * Импортирует/обновляет цену ОДНОЙ игры на сегодня — точечный вход в ту
     * же логику, что и importNextBatch(), но без пачки/курсора/задержек.
     * Используется кнопкой "Импортировать цену" в админке
     * (Api\Admin\GameApiController::importPrice()).
     */
    public function importPriceForGame(SteamGame $steamGame): ?GamePrice
    {
        return $this->fetchAndStorePrice($steamGame, new \DateTimeImmutable('today'));
    }

    /**
     * Запрашивает у Steam цену игры в регионе RU и сохраняет снимок на
     * указанный день (обновляет уже существующий снимок за этот день, если
     * он есть, — идемпотентно при повторных запусках). При сетевой ошибке
     * ничего не сохраняет и возвращает null: игру догонит следующий проход
     * по каталогу (для пачки) либо пользователь админки увидит ошибку и
     * сможет повторить запрос (для точечного вызова).
     */
    private function fetchAndStorePrice(SteamGame $steamGame, \DateTimeImmutable $date): ?GamePrice
    {
        $game = $steamGame->getGame();
        assert($game instanceof Game);

        try {
            $details = $this->steamClient->fetchAppDetailsForRussia($steamGame->getSteamAppId());
        } catch (SteamApiException) {
            return null;
        }

        $price = $this->gamePriceRepository->findOneByGameAndDate($game, $date);
        $isNew = $price === null;
        $price ??= new GamePrice($game, $date);

        $this->applyDetails($price, $details);

        if ($price->isFree()) {
            $game->markFree();
        }

        $steamGame->setAvailableInRussia($price->isAvailableInRussia());

        if ($isNew) {
            $this->entityManager->persist($price);
        }

        $this->entityManager->flush();

        return $price;
    }

    /**
     * @param array<string, mixed>|null $details
     */
    private function applyDetails(GamePrice $price, ?array $details): void
    {
        if ($details === null) {
            $price->markUnavailable();

            return;
        }

        if (($details['is_free'] ?? false) === true) {
            $price->markFree();

            return;
        }

        $finalPriceKopecks = $details['price_overview']['final'] ?? null;
        if ($finalPriceKopecks === null) {
            $price->markUnavailable();

            return;
        }

        $price->markPriced((int) $finalPriceKopecks);
    }
}
