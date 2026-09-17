<?php

namespace App\Service\Plati;

use App\Entity\PlatiGame;
use App\Entity\PlatiGamePrice;
use App\Repository\PlatiGamePriceRepository;
use App\Repository\PlatiGameRepository;
use App\Repository\PlatiPriceImportCursorRepository;
use App\Service\Plati\Exceptions\PlatiApiException;
use App\Service\Plati\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Импорт цен уже найденных на plati.market продавцов (PlatiGame) —
 * ежедневный снимок цены в PlatiGamePrice, по образцу
 * Steam\PriceImportService. Цена не приходит вместе с фактом наличия
 * (GameImportService её не сохраняет), поэтому здесь повторяется тот же
 * полнотекстовый поиск (GameMatcher), что и при первичном импорте, но
 * среди результатов ищется именно карточка сохранённого продавца (по
 * ссылке, GameMatcher::findByUrl()) — не просто самая продаваемая: у
 * одной игры несколько продавцов (см. класс-докблок PlatiGame), и цена
 * одного не должна перезаписывать цену другого.
 */
class PriceImportService
{
    /** Сколько объявлений смотреть на каждую игру — топ по релевантности поиска Digiseller. */
    private const int SEARCH_LIMIT = 10;

    /** Принимает все зависимости, нужные для импорта цен и сохранения снимков. */
    public function __construct(
        private readonly PlatiClient $platiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly PlatiGameRepository $platiGameRepository,
        private readonly PlatiGamePriceRepository $platiGamePriceRepository,
        private readonly PlatiPriceImportCursorRepository $cursorRepository,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly GameMatcher $gameMatcher,
    ) {
    }

    /**
     * Импортирует цены следующей пачки уже найденных игр, продолжая с
     * сохранённого курсора. Первый запуск за календарный день сбрасывает
     * курсор в начало списка — каждый день начинаем заново с самых
     * популярных игр (тот же приём, что и Steam\PriceImportService).
     */
    public function importNextBatch(int $limit, int $delayMs): PriceImportResult
    {
        $cursor = $this->cursorRepository->getOrCreate();
        $today = new \DateTimeImmutable('today');

        $startedNewDay = $cursor->getUpdatedAt() < $today;
        if ($startedNewDay) {
            $cursor->reset();
            $this->entityManager->flush();
        }

        $platiGames = $this->platiGameRepository->findBatchForPriceImport(
            $cursor->getLastPopularity(),
            $cursor->getLastPlatiGameId(),
            $limit,
        );

        if ($platiGames === []) {
            return new PriceImportResult(
                prices: [],
                lastPlatiGameId: $cursor->getLastPlatiGameId(),
                startedNewDay: $startedNewDay,
            );
        }

        $prices = [];
        $skipped = 0;

        foreach ($platiGames as $i => $platiGame) {
            $price = $this->fetchAndStorePrice($platiGame, $today);
            if ($price === null) {
                ++$skipped;
            } else {
                $prices[] = $price;
            }

            if ($i !== array_key_last($platiGames)) {
                $this->rateLimiter->delay($delayMs);
            }
        }

        $lastPlatiGame = end($platiGames);
        $lastId = $lastPlatiGame->getId();
        assert($lastId !== null);
        $lastGame = $lastPlatiGame->getGame();
        $lastPopularity = $lastGame->getPopularity() ?? -1;

        $cursor->setPosition($lastPopularity, $lastId);
        $this->entityManager->flush();

        return new PriceImportResult($prices, $skipped, $lastId, $startedNewDay, $lastPopularity);
    }

    /**
     * Ищет игру повторно и сохраняет снимок цены на указанный день
     * (обновляет уже существующий снимок за этот день, если он есть —
     * идемпотентно при повторных запусках). При сетевой ошибке ничего не
     * сохраняет и возвращает null: игру догонит следующий проход по
     * очереди.
     */
    private function fetchAndStorePrice(PlatiGame $platiGame, \DateTimeImmutable $date): ?PlatiGamePrice
    {
        $game = $platiGame->getGame();

        try {
            $items = $this->platiClient->search($game->getName(), self::SEARCH_LIMIT);
        } catch (PlatiApiException) {
            return null;
        }

        $item = $this->gameMatcher->findByUrl($items, $platiGame->getUrl());

        $price = $this->platiGamePriceRepository->findOneByPlatiGameAndDate($platiGame, $date);
        $isNew = $price === null;
        $price ??= new PlatiGamePrice($platiGame, $date);

        if ($item !== null && $item->priceRur !== null) {
            $price->markPriced($item->priceRur * 100);
        } else {
            $price->markUnavailable();
        }

        if ($isNew) {
            $this->entityManager->persist($price);
        }

        $this->entityManager->flush();

        return $price;
    }
}
