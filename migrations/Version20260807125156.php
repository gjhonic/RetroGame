<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807125156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cover_image_path to game';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD cover_image_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP cover_image_path');
    }
}
