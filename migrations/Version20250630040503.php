<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630040503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // Переименовываем таблицу game в games
        $this->addSql(<<<'SQL'
            RENAME TABLE game TO games
        SQL);
        
        // Удаляем поля created_by и updated_by
        $this->addSql(<<<'SQL'
            ALTER TABLE games DROP created_by, DROP updated_by
        SQL);
        
        // Переименовываем поле owners_count в steam_popularity
        $this->addSql(<<<'SQL'
            ALTER TABLE games CHANGE owners_count steam_popularity INT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Переименовываем таблицу обратно
        $this->addSql(<<<'SQL'
            RENAME TABLE games TO game
        SQL);
    }
}
