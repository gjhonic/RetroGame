<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630021114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // Сначала добавляем поля как nullable
        $this->addSql(<<<'SQL'
            ALTER TABLE game_shop ADD created_at DATETIME NULL, ADD updated_at DATETIME NULL
        SQL);

        // Обновляем существующие записи текущей датой
        $this->addSql(<<<'SQL'
            UPDATE game_shop SET created_at = NOW(), updated_at = NOW() WHERE created_at IS NULL OR updated_at IS NULL
        SQL);

        // Делаем поля NOT NULL
        $this->addSql(<<<'SQL'
            ALTER TABLE game_shop MODIFY created_at DATETIME NOT NULL, MODIFY updated_at DATETIME NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE game_shop DROP created_at, DROP updated_at
        SQL);
    }
}
