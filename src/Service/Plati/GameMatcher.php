<?php

namespace App\Service\Plati;

/**
 * Отбирает среди результатов полнотекстового поиска Digiseller те, чьё
 * название реально содержит название игры (сам поиск точного совпадения
 * не гарантирует), и выбирает из них самое продаваемое (cnt_sell) —
 * общая логика для GameImportService (проверка наличия) и
 * PriceImportService (обновление цены уже найденных игр).
 */
class GameMatcher
{
    /**
     * Признаки офлайн-аккаунтов и подобных товаров, которые не являются
     * ключом/подарком игры (нам нужны только они) — продавец даёт доступ
     * к своему аккаунту с игрой вместо передачи игры покупателю. Реальный
     * случай: у Black Myth: Wukong в топе по продажам оказались именно
     * такие объявления по 100 руб. вместо ключа/гифта за полную цену.
     */
    private const array EXCLUDED_KEYWORDS = [
        'офлайн',
        'оффлайн',
        'offline',
        'аккаунт',
        'account',
    ];

    /**
     * Среди совпадающих по названию объявлений (см. matchingItems())
     * возвращает до $limit самых продаваемых (cnt_sell), от большего к
     * меньшему — не самых дешёвых и не первых по порядку выдачи, а тех,
     * которым реально доверяют покупатели. У разных продавцов из топа
     * разная цена и надёжность, поэтому сохраняется не только первое
     * место (см. класс-докблок PlatiGame).
     *
     * @param array<int, PlatiSearchItem> $items
     *
     * @return array<int, PlatiSearchItem>
     */
    public function topMatches(string $gameName, array $items, int $limit): array
    {
        $matches = $this->matchingItems($gameName, $items);
        usort($matches, static fn (PlatiSearchItem $a, PlatiSearchItem $b): int => $b->cntSell <=> $a->cntSell);

        return \array_slice($matches, 0, $limit);
    }

    /**
     * Ищет среди объявлений то, что ведёт на конкретную уже сохранённую
     * карточку товара (PlatiGame::getUrl()) — для обновления цены именно
     * этого продавца, а не подмены её ценой другого совпавшего объявления
     * (см. PriceImportService).
     *
     * @param array<int, PlatiSearchItem> $items
     */
    public function findByUrl(array $items, string $url): ?PlatiSearchItem
    {
        foreach ($items as $item) {
            if ($item->url === $url) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Объявления, чьё название (родное или английское) реально содержит
     * название игры.
     *
     * @param array<int, PlatiSearchItem> $items
     *
     * @return array<int, PlatiSearchItem>
     */
    public function matchingItems(string $gameName, array $items): array
    {
        $needle = self::normalize($gameName);

        return array_values(array_filter(
            $items,
            static fn (PlatiSearchItem $item): bool => (str_contains(self::normalize($item->name), $needle)
                || str_contains(self::normalize($item->nameEng), $needle))
                && !self::isAccountSale($item),
        ));
    }

    /** Определяет по названию объявления, что это офлайн-аккаунт, а не ключ/подарок (см. EXCLUDED_KEYWORDS). */
    private static function isAccountSale(PlatiSearchItem $item): bool
    {
        $name = self::normalize($item->name) . ' ' . self::normalize($item->nameEng);

        foreach (self::EXCLUDED_KEYWORDS as $keyword) {
            if (str_contains($name, self::normalize($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Приводит название к виду, устойчивому к маркетинговому шуму
     * продавцов (эмодзи, апострофы разного начертания, лишние пробелы):
     * нижний регистр, апострофы/кавычки убираются целиком (а не заменяются
     * на пробел — иначе "Garry's Mod" превратится в "garry s mod" и не
     * совпадёт с "garrys mod"), всё остальное несловесное — в пробел.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['\'', '’', '´', '`'], '', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
