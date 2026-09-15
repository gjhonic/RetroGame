<?php

namespace App\Service\Steam;

use App\Entity\Enum\SteamGameStatus;
use App\Entity\SteamGame;

/**
 * Итог одного запуска импорта/повтора — что обработали и как продолжить.
 */
final class ImportResult
{
    /**
     * Сохраняет обработанные записи и данные для продолжения импорта.
     * failureMessage не null — не удалось получить саму страницу каталога
     * от Steam (сетевая ошибка/таймаут GetAppList, см. GameImportService) —
     * тогда steamGames всегда пустой, а курсор не сдвинут: следующий запуск
     * повторит тот же last_appid.
     *
     * @param array<int, SteamGame> $steamGames
     */
    public function __construct(
        public readonly array $steamGames,
        public readonly bool $hasMore = false,
        public readonly int $lastAppId = 0,
        public readonly ?string $failureMessage = null,
    ) {
    }

    /** Считает, сколько обработанных записей имеют указанный статус. */
    public function countByStatus(SteamGameStatus $status): int
    {
        return count(array_filter(
            $this->steamGames,
            static fn (SteamGame $steamGame): bool => $steamGame->getStatus() === $status,
        ));
    }
}
