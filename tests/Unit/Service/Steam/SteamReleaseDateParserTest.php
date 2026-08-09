<?php

namespace App\Tests\Unit\Service\Steam;

use App\Service\Steam\SteamReleaseDateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SteamReleaseDateParserTest extends TestCase
{
    private SteamReleaseDateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SteamReleaseDateParser();
    }

    #[DataProvider('provideParsableDates')]
    public function testParseRecognizesRussianSteamDateFormat(string $input, string $expectedIsoDate): void
    {
        $result = $this->parser->parse($input);

        self::assertNotNull($result);
        self::assertSame($expectedIsoDate, $result->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideParsableDates(): iterable
    {
        yield 'июль с точкой' => ['12 июл. 2010 г.', '2010-07-12'];
        yield 'ноябрь' => ['1 ноя. 2000 г.', '2000-11-01'];
        yield 'май' => ['1 мая. 2003 г.', '2003-05-01'];
        yield 'январь, однозначный день' => ['5 янв. 2020 г.', '2020-01-05'];
        yield 'без завершающего "г."' => ['20 дек. 1999', '1999-12-20'];
    }

    #[DataProvider('provideUnparsableDates')]
    public function testParseReturnsNullForUnrecognizedInput(?string $input): void
    {
        self::assertNull($this->parser->parse($input));
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function provideUnparsableDates(): iterable
    {
        yield 'null' => [null];
        yield 'пустая строка' => [''];
        yield 'только год' => ['2024'];
        yield 'скоро' => ['Скоро'];
        yield 'неизвестный месяц' => ['12 хмм. 2010 г.'];
    }
}
