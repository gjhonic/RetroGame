<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807142151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop cover_image_url from game (replaced by cover_image_path)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP cover_image_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD cover_image_url VARCHAR(500) DEFAULT NULL');
    }
}
