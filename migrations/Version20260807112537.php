<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807112537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial schema: game, steam_game, steam_import_cursor, '
            . 'developer/publisher/genre/platform, user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE game (
                id SERIAL NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                release_date DATE DEFAULT NULL,
                cover_image_path VARCHAR(500) DEFAULT NULL,
                screenshot_urls JSON DEFAULT NULL,
                rating DOUBLE PRECISION DEFAULT NULL,
                metacritic_score INT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C989D9B62 ON game (slug)');

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
            . 'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE steam_import_cursor (
                id SERIAL NOT NULL,
                last_app_id INT NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE TABLE developer (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DEVELOPER_NAME ON developer (name)');

        $this->addSql('CREATE TABLE publisher (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PUBLISHER_NAME ON publisher (name)');

        $this->addSql('CREATE TABLE genre (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GENRE_NAME ON genre (name)');

        $this->addSql('CREATE TABLE platform (id SERIAL NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PLATFORM_NAME ON platform (name)');

        $this->addSql(<<<'SQL'
            CREATE TABLE game_developer (
                game_id INT NOT NULL,
                developer_id INT NOT NULL,
                PRIMARY KEY(game_id, developer_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_GAME_DEVELOPER_GAME ON game_developer (game_id)');
        $this->addSql('CREATE INDEX IDX_GAME_DEVELOPER_DEVELOPER ON game_developer (developer_id)');
        $this->addSql(
            'ALTER TABLE game_developer ADD CONSTRAINT FK_GAME_DEVELOPER_GAME '
            . 'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_developer ADD CONSTRAINT FK_GAME_DEVELOPER_DEVELOPER '
            . 'FOREIGN KEY (developer_id) REFERENCES developer (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE game_publisher (
                game_id INT NOT NULL,
                publisher_id INT NOT NULL,
                PRIMARY KEY(game_id, publisher_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_GAME_PUBLISHER_GAME ON game_publisher (game_id)');
        $this->addSql('CREATE INDEX IDX_GAME_PUBLISHER_PUBLISHER ON game_publisher (publisher_id)');
        $this->addSql(
            'ALTER TABLE game_publisher ADD CONSTRAINT FK_GAME_PUBLISHER_GAME '
            . 'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_publisher ADD CONSTRAINT FK_GAME_PUBLISHER_PUBLISHER '
            . 'FOREIGN KEY (publisher_id) REFERENCES publisher (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE game_genre (
                game_id INT NOT NULL,
                genre_id INT NOT NULL,
                PRIMARY KEY(game_id, genre_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_GAME_GENRE_GAME ON game_genre (game_id)');
        $this->addSql('CREATE INDEX IDX_GAME_GENRE_GENRE ON game_genre (genre_id)');
        $this->addSql(
            'ALTER TABLE game_genre ADD CONSTRAINT FK_GAME_GENRE_GAME '
            . 'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_genre ADD CONSTRAINT FK_GAME_GENRE_GENRE '
            . 'FOREIGN KEY (genre_id) REFERENCES genre (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE game_platform (
                game_id INT NOT NULL,
                platform_id INT NOT NULL,
                PRIMARY KEY(game_id, platform_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_GAME_PLATFORM_GAME ON game_platform (game_id)');
        $this->addSql('CREATE INDEX IDX_GAME_PLATFORM_PLATFORM ON game_platform (platform_id)');
        $this->addSql(
            'ALTER TABLE game_platform ADD CONSTRAINT FK_GAME_PLATFORM_GAME '
            . 'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_platform ADD CONSTRAINT FK_GAME_PLATFORM_PLATFORM '
            . 'FOREIGN KEY (platform_id) REFERENCES platform (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (
                id SERIAL NOT NULL,
                email VARCHAR(180) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON "user" (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE game_platform');
        $this->addSql('DROP TABLE game_genre');
        $this->addSql('DROP TABLE game_publisher');
        $this->addSql('DROP TABLE game_developer');
        $this->addSql('DROP TABLE platform');
        $this->addSql('DROP TABLE genre');
        $this->addSql('DROP TABLE publisher');
        $this->addSql('DROP TABLE developer');
        $this->addSql('DROP TABLE steam_import_cursor');
        $this->addSql('ALTER TABLE steam_game DROP CONSTRAINT FK_STEAM_GAME_GAME');
        $this->addSql('DROP TABLE steam_game');
        $this->addSql('DROP TABLE game');
    }
}
