<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630033644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE genres ADD name_russia VARCHAR(255) DEFAULT NULL
        SQL);

        // Заполняем поле name_russia переводами жанров на русский язык
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Экшен' WHERE name = 'Action'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Приключения' WHERE name = 'Adventure'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Инди' WHERE name = 'Indie'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Стратегия' WHERE name = 'Strategy'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Ролевая игра' WHERE name = 'RPG'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Казуальная' WHERE name = 'Casual'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Симулятор' WHERE name = 'Simulation'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Гонки' WHERE name = 'Racing'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Обнаженность' WHERE name = 'Nudity'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Насилие' WHERE name = 'Violent'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Спорт' WHERE name = 'Sports'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Ранний доступ' WHERE name = 'Early Access'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Массовая многопользовательская' WHERE name = 'Massively Multiplayer'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Анимация и моделирование' WHERE name = 'Animation & Modeling'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Кровь' WHERE name = 'Gore'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Аудио продакшн' WHERE name = 'Audio Production'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Утилиты' WHERE name = 'Utilities'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Видео продакшн' WHERE name = 'Video Production'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Дизайн и иллюстрация' WHERE name = 'Design & Illustration'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Образование' WHERE name = 'Education'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Веб-публикация' WHERE name = 'Web Publishing'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Разработка игр' WHERE name = 'Game Development'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Редактирование фото' WHERE name = 'Photo Editing'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Обучение ПО' WHERE name = 'Software Training'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Бесплатная игра' WHERE name = 'Free To Play'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Сексуальный контент' WHERE name = 'Sexual Content'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE genres SET name_russia = 'Бухгалтерия' WHERE name = 'Accounting'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE genres RENAME INDEX uniq_835033f85e237e06 TO UNIQ_A8EBE5165E237E06
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users RENAME INDEX uniq_88bdf3e9e7927c74 TO UNIQ_1483A5E9E7927C74
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE genres DROP name_russia
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE genres RENAME INDEX uniq_a8ebe5165e237e06 TO UNIQ_835033F85E237E06
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO UNIQ_88BDF3E9E7927C74
        SQL);
    }
}
