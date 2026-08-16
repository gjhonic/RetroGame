<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user: уникальный ник (несколько NULL по-прежнему допустимы в PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_NICKNAME ON "user" (nickname)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_NICKNAME');
    }
}
