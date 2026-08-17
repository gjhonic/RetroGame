<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'game: добавлена avg_popularity (средняя популярность — popularity / лет с релиза)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD avg_popularity DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP avg_popularity');
    }
}
