<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807115827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create steam_game table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE steam_game (
                id SERIAL NOT NULL,
                game_id INT NOT NULL,
                steam_app_id INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                raw_data JSON DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                attempts INT NOT NULL,
                fetched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                last_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_STEAM_GAME_GAME_ID ON steam_game (game_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_STEAM_GAME_APP_ID ON steam_game (steam_app_id)');
        $this->addSql('CREATE INDEX IDX_STEAM_GAME_STATUS ON steam_game (status)');
        $this->addSql(
            'ALTER TABLE steam_game ADD CONSTRAINT FK_STEAM_GAME_GAME '
            .'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_game DROP CONSTRAINT FK_STEAM_GAME_GAME');
        $this->addSql('DROP TABLE steam_game');
    }
}
