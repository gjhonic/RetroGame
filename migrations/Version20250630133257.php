<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630133257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename steam_app to steam_apps and add created_at';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('RENAME TABLE steam_app TO steam_apps');
        $this->addSql('ALTER TABLE steam_apps ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE steam_apps SET created_at = NOW()');
        $this->addSql('ALTER TABLE steam_apps MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('RENAME TABLE steam_apps TO steam_app');
        $this->addSql('ALTER TABLE steam_app DROP created_at');
    }
}
