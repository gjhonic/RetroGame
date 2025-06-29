<?php

namespace App\Tests\Service;

use App\Service\SlugifyProcessor;
use PHPUnit\Framework\TestCase;

class SlugifyProcessorTest extends TestCase
{
    /**
     * @dataProvider slugifyDataProvider
     */
    public function testProcess(string $input, string $expected): void
    {
        $result = SlugifyProcessor::process($input);
        $this->assertEquals($expected, $result);
    }

    public function slugifyDataProvider(): array
    {
        return [
            // Базовые тесты с кириллицей
            'кириллица в нижнем регистре' => [
                'привет мир',
                'privet-mir'
            ],
            'кириллица в верхнем регистре' => [
                'ПРИВЕТ МИР',
                'privet-mir'
            ],
            'смешанный регистр' => [
                'Привет Мир',
                'privet-mir'
            ],

            // Тесты с латиницей
            'латиница в нижнем регистре' => [
                'hello world',
                'hello-world'
            ],
            'латиница в верхнем регистре' => [
                'HELLO WORLD',
                'hello-world'
            ],
            'смешанная латиница' => [
                'Hello World',
                'hello-world'
            ],

            // Тесты с цифрами
            'с цифрами' => [
                'Game 2023',
                'game-2023'
            ],
            'только цифры' => [
                '12345',
                '12345'
            ],

            // Тесты с специальными символами
            'с запятыми' => [
                'привет, мир',
                'privet-mir'
            ],
            'с точками' => [
                'game.title',
                'gametitle'
            ],
            'с двоеточиями' => [
                'game: title',
                'game-title'
            ],
            'с дефисами' => [
                'game-title',
                'game-title'
            ],
            'с множественными дефисами' => [
                'game--title',
                'game-title'
            ],
            'с дефисами в начале и конце' => [
                '-game-title-',
                'game-title'
            ],

            // Тесты с различными символами
            'с апострофами' => [
                "game's title",
                'games-title'
            ],
            'с кавычками' => [
                '"game title"',
                'game-title'
            ],
            'с слешами' => [
                'game/title',
                'game-title'
            ],
            'с тире' => [
                'game–title',
                'game-title'
            ],
            'с длинным тире' => [
                'game—title',
                'game-title'
            ],

            // Сложные случаи
            'сложная кириллица' => [
                'Специальные символы: @#$%',
                'spetsialnye-simvoly'
            ],
            'смешанный текст' => [
                'Game Title 2023 - Приключения',
                'game-title-2023-priklyucheniya'
            ],
            'множественные пробелы' => [
                'game    title',
                'game-title'
            ],
            'пустая строка' => [
                '',
                ''
            ],
            'только пробелы' => [
                '   ',
                ''
            ],
            'только специальные символы' => [
                '@#$%^&*()',
                ''
            ],

            // Тесты с буквой ё
            'с буквой ё' => [
                'ёлка',
                'elka'
            ],

            // Тесты с буквой й
            'с буквой й' => [
                'мой',
                'moy'
            ],

            // Тесты с буквой х
            'с буквой х' => [
                'хорошо',
                'khorosho'
            ],

            // Тесты с буквой ц
            'с буквой ц' => [
                'цвет',
                'tsvet'
            ],

            // Тесты с буквой ч
            'с буквой ч' => [
                'часы',
                'chasy'
            ],

            // Тесты с буквой ш
            'с буквой ш' => [
                'школа',
                'shkola'
            ],

            // Тесты с буквой щ
            'с буквой щ' => [
                'щука',
                'shchuka'
            ],

            // Тесты с буквой ю
            'с буквой ю' => [
                'юг',
                'yug'
            ],

            // Тесты с буквой я
            'с буквой я' => [
                'яблоко',
                'yabloko'
            ],
        ];
    }

    public function testProcessWithVeryLongText(): void
    {
        $longText = str_repeat('Привет мир ', 100);
        $result = SlugifyProcessor::process($longText);

        // Проверяем, что результат не пустой и содержит только допустимые символы
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
    }

    public function testProcessWithUnicodeCharacters(): void
    {
        $text = 'Привет мир с эмодзи 😀🎮';
        $result = SlugifyProcessor::process($text);

        // Эмодзи должны быть удалены
        $this->assertEquals('privet-mir-s-emodzi', $result);
    }

    public function testProcessWithMixedLanguages(): void
    {
        $text = 'Game Приключения 2023';
        $result = SlugifyProcessor::process($text);

        $this->assertEquals('game-priklyucheniya-2023', $result);
    }
}
