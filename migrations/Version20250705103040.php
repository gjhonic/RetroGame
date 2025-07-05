<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250705103040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Заполнение NULL значений в полях дат и синхронизация схемы БД с сущностями';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // Сначала заполняем NULL значения в таблице games
        $this->addSql(<<<'SQL'
            UPDATE games SET created_at = NOW() WHERE created_at IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE games SET updated_at = NOW() WHERE updated_at IS NULL
        SQL);
        
        // Заполняем NULL значения в таблице steam_apps
        $this->addSql(<<<'SQL'
            UPDATE steam_apps SET created_at = NOW() WHERE created_at IS NULL
        SQL);
        
        // Заполняем NULL значения в таблице steambuy_apps
        $this->addSql(<<<'SQL'
            UPDATE steambuy_apps SET created_at = NOW() WHERE created_at IS NULL
        SQL);
        
        // Заполняем NULL значения в таблице steamkey_apps
        $this->addSql(<<<'SQL'
            UPDATE steamkey_apps SET created_at = NOW() WHERE created_at IS NULL
        SQL);
        
        // Заполняем NULL значения в таблице steampay_apps
        $this->addSql(<<<'SQL'
            UPDATE steampay_apps SET created_at = NOW() WHERE created_at IS NULL
        SQL);
        
        // Заполняем NULL значения в таблице igm_apps
        $this->addSql(<<<'SQL'
            UPDATE igm_apps SET created_at = NOW() WHERE created_at IS NULL
        SQL);
        
        // Теперь изменяем поля на NOT NULL
        $this->addSql(<<<'SQL'
            ALTER TABLE games CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steam_apps CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steambuy_apps RENAME INDEX uniq_4787ae8a989d9b62 TO UNIQ_1624229B989D9B62
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steamkey_apps RENAME INDEX uniq_d1c9e9c2989d9b62 TO UNIQ_6EB5A57E989D9B62
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steampay_apps RENAME INDEX uniq_2f8163e0989d9b62 TO UNIQ_BB2BAC10989D9B62
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        
        // Откатываем изменения полей в таблице games
        $this->addSql(<<<'SQL'
            ALTER TABLE games CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steam_apps CHANGE created_at created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steambuy_apps RENAME INDEX uniq_1624229b989d9b62 TO UNIQ_4787AE8A989D9B62
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steamkey_apps RENAME INDEX uniq_6eb5a57e989d9b62 TO UNIQ_D1C9E9C2989D9B62
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE steampay_apps RENAME INDEX uniq_bb2bac10989d9b62 TO UNIQ_2F8163E0989D9B62
        SQL);
    }
}
