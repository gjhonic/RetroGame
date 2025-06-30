<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630022446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // Переименовываем таблицу app_user в users
        $this->addSql(<<<'SQL'
            RENAME TABLE app_user TO users
        SQL);
        
        // Добавляем новые поля как nullable
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD created_at DATETIME NULL, ADD updated_at DATETIME NULL
        SQL);
        
        // Обновляем существующие записи текущей датой
        $this->addSql(<<<'SQL'
            UPDATE users SET created_at = NOW(), updated_at = NOW() WHERE created_at IS NULL OR updated_at IS NULL
        SQL);
        
        // Делаем поля NOT NULL
        $this->addSql(<<<'SQL'
            ALTER TABLE users MODIFY created_at DATETIME NOT NULL, MODIFY updated_at DATETIME NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        
        // Удаляем добавленные поля
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP created_at, DROP updated_at
        SQL);
        
        // Переименовываем таблицу обратно
        $this->addSql(<<<'SQL'
            RENAME TABLE users TO app_user
        SQL);
    }
}
