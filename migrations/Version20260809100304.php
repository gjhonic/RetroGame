<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260809100304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add game.popularity (Steam recommendations.total)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD popularity INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP popularity');
    }
}
