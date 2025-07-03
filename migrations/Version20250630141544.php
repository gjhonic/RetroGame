<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630141544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename steambuy_app to steambuy_apps and checkedAt to createdAt';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('RENAME TABLE steambuy_app TO steambuy_apps');
        $this->addSql('ALTER TABLE steambuy_apps CHANGE checked_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('RENAME TABLE steampay_app TO steampay_apps');
        $this->addSql('ALTER TABLE steampay_apps CHANGE checked_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('RENAME TABLE steamkey_app TO steamkey_apps');
        $this->addSql('ALTER TABLE steamkey_apps CHANGE checked_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('RENAME TABLE steambuy_apps TO steambuy_app');
        $this->addSql('ALTER TABLE steambuy_app CHANGE created_at checked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('RENAME TABLE steampay_apps TO steampay_app');
        $this->addSql('ALTER TABLE steampay_app CHANGE created_at checked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('RENAME TABLE steamkey_apps TO steamkey_app');
        $this->addSql('ALTER TABLE steamkey_app CHANGE created_at checked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
