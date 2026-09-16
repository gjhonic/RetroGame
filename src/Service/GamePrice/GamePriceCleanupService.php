<?php

namespace App\Service\GamePrice;

use App\Entity\GamePrice;
use App\Repository\GamePriceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Удаляет "промежуточные" снимки цены игры: если цена несколько дней подряд
 * не менялась, для графика достаточно хранить снимок до начала периода и
 * снимок после его окончания (цену "до" и "после" изменения) — снимки между
 * ними не нужны. Крон app:games:cleanup-prices раз в неделю обрабатывает
 * окно из последних $weeks недель.
 */
class GamePriceCleanupService
{
    public function __construct(
        private readonly GamePriceRepository $gamePriceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function cleanup(int $weeks, ?\DateTimeImmutable $now = null): GamePriceCleanupResult
    {
        $now ??= new \DateTimeImmutable('today');
        $from = $now->modify(sprintf('-%d weeks', $weeks));

        $prices = $this->gamePriceRepository->findAllInRangeOrderedByGame($from, $now);

        $deletedCount = 0;
        $processedGameIds = [];
        $run = [];
        $currentGameId = null;

        foreach ($prices as $price) {
            $gameId = $price->getGame()->getId();

            if ($gameId !== $currentGameId) {
                $deletedCount += $this->removeMiddleOfRuns($run);
                $run = [];
                $currentGameId = $gameId;
                $processedGameIds[$gameId] = true;
            }

            $run[] = $price;
        }
        $deletedCount += $this->removeMiddleOfRuns($run);

        $this->entityManager->flush();

        return new GamePriceCleanupResult($deletedCount, count($processedGameIds));
    }

    /**
     * Разбивает отсортированные по дате снимки одной игры на серии подряд
     * идущих записей с одинаковой ценой и удаляет всё, кроме первой и
     * последней записи каждой серии.
     *
     * @param array<int, GamePrice> $prices
     */
    private function removeMiddleOfRuns(array $prices): int
    {
        $deleted = 0;
        $runStart = 0;
        $count = count($prices);

        for ($i = 1; $i <= $count; ++$i) {
            $continuesRun = $i < $count && $prices[$i]->getPriceKopecks() === $prices[$runStart]->getPriceKopecks();
            if ($continuesRun) {
                continue;
            }

            $run = array_slice($prices, $runStart, $i - $runStart);
            foreach (array_slice($run, 1, -1) as $middlePrice) {
                $this->entityManager->remove($middlePrice);
                ++$deleted;
            }

            $runStart = $i;
        }

        return $deleted;
    }
}
