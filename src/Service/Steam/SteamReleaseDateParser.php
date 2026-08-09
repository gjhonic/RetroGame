<?php

namespace App\Service\Steam;

/**
 * Разбирает дату выхода из ответа Steam appdetails.
 *
 * Steam не отдаёт машинный формат даты — только строку на языке запроса
 * (например, «12 июл. 2010 г.»), поэтому разбираем русские сокращения
 * месяцев вручную. Если строка не распознана (например, «Скоро» или
 * только год) — возвращаем null, это не повод проваливать импорт.
 */
class SteamReleaseDateParser
{
    private const MONTHS = [
        'янв' => 1,
        'фев' => 2,
        'мар' => 3,
        'апр' => 4,
        'май' => 5,
        // "мая" (родительный падеж) — единственный месяц, где 3-я буква отличается от именительного.
        'мая' => 5,
        'июн' => 6,
        'июл' => 7,
        'авг' => 8,
        'сен' => 9,
        'окт' => 10,
        'ноя' => 11,
        'дек' => 12,
    ];

    public function parse(?string $date): ?\DateTimeImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})\s+([а-яё]+)\.?\s+(\d{4})/ui', $date, $matches) !== 1) {
            return null;
        }

        $month = self::MONTHS[mb_strtolower(mb_substr($matches[2], 0, 3))] ?? null;

        if ($month === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', (int) $matches[3], $month, (int) $matches[1]));
        } catch (\Exception) {
            return null;
        }
    }
}
