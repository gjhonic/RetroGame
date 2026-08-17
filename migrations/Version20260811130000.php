<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'cron: добавлено название крона (name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cron ADD name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cron DROP name');
    }
}
