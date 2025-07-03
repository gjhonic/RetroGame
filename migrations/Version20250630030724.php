<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630030724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // Переименовываем таблицу genre в genres
        $this->addSql(<<<'SQL'
            RENAME TABLE genre TO genres
        SQL);

        // Удаляем старые поля created_by и updated_by
        $this->addSql(<<<'SQL'
            ALTER TABLE genres DROP created_by, DROP updated_by
        SQL);

        // Удаляем старые поля created_by и updated_by
        $this->addSql(<<<'SQL'
            ALTER TABLE genres DROP created_at, DROP updated_at
        SQL);

        // Добавляем новые поля как nullable
        $this->addSql(<<<'SQL'
            ALTER TABLE genres ADD created_at DATETIME NULL, ADD updated_at DATETIME NULL
        SQL);

        // Обновляем существующие записи текущей датой
        $this->addSql(<<<'SQL'
            UPDATE genres SET created_at = NOW(), updated_at = NOW() WHERE created_at IS NULL OR updated_at IS NULL
        SQL);

        // Делаем поля NOT NULL
        $this->addSql(<<<'SQL'
            ALTER TABLE genres MODIFY created_at DATETIME NOT NULL, MODIFY updated_at DATETIME NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        // Возвращаем тип полей обратно к datetime_immutable
        $this->addSql(<<<'SQL'
            ALTER TABLE genres MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);

        // Добавляем обратно поля created_by и updated_by
        $this->addSql(<<<'SQL'
            ALTER TABLE genres ADD created_by VARCHAR(255) NOT NULL, ADD updated_by VARCHAR(255) DEFAULT NULL
        SQL);

        // Переименовываем таблицу обратно
        $this->addSql(<<<'SQL'
            RENAME TABLE genres TO genre
        SQL);
    }
}
