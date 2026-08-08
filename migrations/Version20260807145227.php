<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807145227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add developers, publishers, genres, platforms, screenshot_urls to game';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE game
                ADD developers JSON DEFAULT NULL,
                ADD publishers JSON DEFAULT NULL,
                ADD genres JSON DEFAULT NULL,
                ADD platforms JSON DEFAULT NULL,
                ADD screenshot_urls JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE game
                DROP developers,
                DROP publishers,
                DROP genres,
                DROP platforms,
                DROP screenshot_urls
        SQL);
    }
}
