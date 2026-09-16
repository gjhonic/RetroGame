<?php

namespace App\Service\Ggsel;

use App\Entity\Game;
use App\Entity\GgselGame;
use App\Repository\GgselGameRepository;
use App\Repository\GgselImportCursorRepository;
use App\Service\Ggsel\Exceptions\GgselApiException;
use App\Service\Ggsel\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Импорт наличия игр на ggsel.net: идёт по game (самые популярные —
 * вперёд, см. GgselGameRepository::findGamesPendingCheck()), для каждой
 * пробует найти товар по прямому слагу — транслитерация названия, при
 * необходимости с одним из известных суффиксов категории (см.
 * SLUG_SUFFIXES). Цена не сохраняется — это отдельная задача, здесь
 * фиксируется только сам факт наличия и ссылка (GgselGame).
 *
 * Игры, для которых товар не нашёлся, не помечаются никак (в отличие от
 * SteamGame::status) — они просто остаются без GgselGame и попадут в
 * следующий круг обхода: ассортимент ggsel меняется, и то, чего нет
 * сегодня, может появиться позже, а отдельная таблица "неудачных попыток"
 * для этого не нужна.
 */
class GameImportService
{
    /**
     * Суффиксы категории, которые ggsel добавляет к слагу игры, если товар
     * лежит не на "голом" слаге. Подтверждено вручную: Half-Life 2 —
     * "half-life-2" (без суффикса), Garry's Mod — "garrys-mod-keys" (товар
     * продаётся как ключи активации, а не как аккаунт). Список заведомо
     * неполный — у ggsel нет предсказуемой схемы слагов, суффиксы
     * добавляются сюда по мере обнаружения новых на реальных играх.
     *
     * @var array<int, string>
     */
    private const array SLUG_SUFFIXES = ['', '-keys'];

    /** Принимает все зависимости, нужные для проверки игр на ggsel.net и сохранения результата. */
    public function __construct(
        private readonly GgselClient $ggselClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly GgselGameRepository $ggselGameRepository,
        private readonly GgselImportCursorRepository $cursorRepository,
        private readonly SluggerInterface $slugger,
        private readonly RateLimiterInterface $rateLimiter,
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

        $games = $this->ggselGameRepository->findGamesPendingCheck(
            $cursor->getLastPopularity(),
            $cursor->getLastGameId(),
            $limit,
        );

        $wrapped = false;
        if ($games === [] && $cursor->getLastPopularity() !== null) {
            $cursor->reset();
            $games = $this->ggselGameRepository->findGamesPendingCheck(null, 0, $limit);
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

    /**
     * Проверяет одну игру: перебирает слаги-кандидаты (базовый + суффиксы
     * из SLUG_SUFFIXES) по очереди, пока один из них не найдётся. Каждая
     * попытка (URL + причина) сохраняется в результате — для подробного
     * лога (см. ImportGgselGamesCommand), куда именно ходили и почему не
     * нашли.
     */
    private function checkGame(Game $game): GgselCheckResult
    {
        $baseSlug = strtolower((string) $this->slugger->slug($game->getName()));
        if ($baseSlug === '') {
            return new GgselCheckResult($game, null, []);
        }

        [$product, $attempts] = $this->findProductByCandidateSlugs($baseSlug);

        if ($product === null) {
            return new GgselCheckResult($game, null, $attempts);
        }

        $ggselGame = $this->ggselGameRepository->findOneByGame($game);
        if ($ggselGame !== null) {
            $ggselGame->setUrl($product->url);
        } else {
            $ggselGame = new GgselGame($game, $product->url);
            $this->entityManager->persist($ggselGame);
        }

        return new GgselCheckResult($game, $ggselGame, $attempts);
    }

    /**
     * Сетевая ошибка (GgselApiException) на любом кандидате прерывает
     * перебор для этой игры целиком (сохраняется как отдельная "попытка" с
     * текстом ошибки), не роняя всю пачку — следующий круг обхода
     * попробует снова с первого кандидата.
     *
     * @return array{0: ?GgselProduct, 1: array<int, GgselSlugAttempt>}
     */
    private function findProductByCandidateSlugs(string $baseSlug): array
    {
        $attempts = [];

        foreach (self::SLUG_SUFFIXES as $suffix) {
            $slug = $baseSlug . $suffix;

            try {
                $attempt = $this->ggselClient->findBySlug($slug);
            } catch (GgselApiException $e) {
                $attempts[] = new GgselSlugAttempt(
                    sprintf(GgselClient::CATALOG_URL, $slug),
                    null,
                    'ошибка запроса: ' . $e->getMessage(),
                );

                break;
            }

            $attempts[] = $attempt;

            if ($attempt->isFound()) {
                return [$attempt->product, $attempts];
            }
        }

        return [null, $attempts];
    }
}
