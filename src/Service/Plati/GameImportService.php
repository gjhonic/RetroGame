<?php

namespace App\Service\Plati;

use App\Entity\Game;
use App\Entity\PlatiGame;
use App\Repository\PlatiGameRepository;
use App\Repository\PlatiImportCursorRepository;
use App\Service\Plati\Exceptions\PlatiApiException;
use App\Service\Plati\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Импорт наличия игр на plati.market: идёт по game (самые популярные —
 * вперёд, см. PlatiGameRepository::findGamesPendingCheck()), для каждой
 * ищет товар полнотекстовым поиском Digiseller (см. PlatiClient) и среди
 * результатов отбирает те, чьё название реально содержит название игры
 * (полнотекстовый поиск сам по себе не гарантирует точного совпадения —
 * может вернуть похожие, но другие игры). Из совпадений сохраняются до
 * TOP_SELLERS_LIMIT самых продаваемых объявлений (cnt_sell) — не только
 * первое место, у разных продавцов из топа разная цена и надёжность (см.
 * класс-докблок PlatiGame). Цена не сохраняется — это отдельная задача
 * (PriceImportService), здесь фиксируется только сам факт наличия у
 * продавца и ссылка (PlatiGame).
 *
 * Игры, для которых товар не нашёлся, не помечаются никак (в отличие от
 * SteamGame::status) — они просто остаются без PlatiGame и попадут в
 * следующий круг обхода: ассортимент продавцов меняется, и то, чего нет
 * сегодня, может появиться позже, а отдельная таблица "неудачных попыток"
 * для этого не нужна.
 */
class GameImportService
{
    /** Сколько объявлений смотреть на каждую игру — топ по релевантности поиска Digiseller. */
    private const int SEARCH_LIMIT = 10;

    /** Сколько самых продаваемых совпавших продавцов сохранять на игру. */
    private const int TOP_SELLERS_LIMIT = 3;

    /** Принимает все зависимости, нужные для проверки игр на plati.market и сохранения результата. */
    public function __construct(
        private readonly PlatiClient $platiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly PlatiGameRepository $platiGameRepository,
        private readonly PlatiImportCursorRepository $cursorRepository,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly GameMatcher $gameMatcher,
    ) {
    }

    /**
     * Импортирует следующую пачку: продолжает с сохранённого курсора.
     * Если пачка пуста и курсор не в начале списка (обошли всех
     * непроверенных до конца) — курсор сбрасывается и выборка повторяется
     * один раз в рамках того же запуска, чтобы не терять целый цикл крона
     * на границе обхода.
     */
    public function importNextBatch(int $limit, int $delayMs): ImportResult
    {
        $cursor = $this->cursorRepository->getOrCreate();

        $games = $this->platiGameRepository->findGamesPendingCheck(
            $cursor->getLastPopularity(),
            $cursor->getLastGameId(),
            $limit,
        );

        $wrapped = false;
        if ($games === [] && $cursor->getLastPopularity() !== null) {
            $cursor->reset();
            $games = $this->platiGameRepository->findGamesPendingCheck(null, 0, $limit);
            $wrapped = true;
        }

        if ($games === []) {
            return new ImportResult(results: [], wrapped: $wrapped);
        }

        $results = [];

        foreach ($games as $i => $game) {
            $results[] = $this->checkGame($game);

            if ($i !== array_key_last($games)) {
                $this->rateLimiter->delay($delayMs);
            }
        }

        $lastGame = end($games);
        $lastId = $lastGame->getId();
        assert($lastId !== null);

        $cursor->setPosition($lastGame->getPopularity() ?? -1, $lastId);
        $this->entityManager->flush();

        return new ImportResult(results: $results, wrapped: $wrapped);
    }

    /** Проверяет одну игру: поиск по названию, отбор совпадений, сохранение до TOP_SELLERS_LIMIT продавцов. */
    private function checkGame(Game $game): PlatiCheckResult
    {
        try {
            $items = $this->platiClient->search($game->getName(), self::SEARCH_LIMIT);
        } catch (PlatiApiException $e) {
            return new PlatiCheckResult($game, [], 'ошибка запроса: ' . $e->getMessage());
        }

        if ($items === []) {
            return new PlatiCheckResult($game, [], 'ничего не найдено по запросу');
        }

        $matches = $this->gameMatcher->matchingItems($game->getName(), $items);
        if ($matches === []) {
            return new PlatiCheckResult(
                $game,
                [],
                sprintf('среди %d результатов поиска нет совпадений по названию', count($items)),
            );
        }

        $top = $this->gameMatcher->topMatches($game->getName(), $items, self::TOP_SELLERS_LIMIT);
        $platiGames = array_map(fn (PlatiSearchItem $item): PlatiGame => $this->storeSeller($game, $item), $top);

        return new PlatiCheckResult(
            $game,
            $platiGames,
            sprintf(
                'совпадений по названию: %d, сохранено продавцов: %d (по убыванию продаж)',
                count($matches),
                count($platiGames),
            ),
        );
    }

    /** Создаёт или обновляет запись конкретного продавца по паре (игра, ссылка). */
    private function storeSeller(Game $game, PlatiSearchItem $item): PlatiGame
    {
        $platiGame = $this->platiGameRepository->findOneByGameAndUrl($game, $item->url);
        if ($platiGame !== null) {
            $platiGame->setSellerName($item->sellerName);

            return $platiGame;
        }

        $platiGame = new PlatiGame($game, $item->url, $item->sellerName);
        $this->entityManager->persist($platiGame);

        return $platiGame;
    }
}
