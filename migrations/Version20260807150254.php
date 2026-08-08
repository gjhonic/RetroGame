<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807150254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extract developers/publishers/genres/platforms into their own tables';
    }

    public function up(Schema $schema): void
    {
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
            .'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_developer ADD CONSTRAINT FK_GAME_DEVELOPER_DEVELOPER '
            .'FOREIGN KEY (developer_id) REFERENCES developer (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
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
            .'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_publisher ADD CONSTRAINT FK_GAME_PUBLISHER_PUBLISHER '
            .'FOREIGN KEY (publisher_id) REFERENCES publisher (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
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
            .'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_genre ADD CONSTRAINT FK_GAME_GENRE_GENRE '
            .'FOREIGN KEY (genre_id) REFERENCES genre (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
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
            .'FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE game_platform ADD CONSTRAINT FK_GAME_PLATFORM_PLATFORM '
            .'FOREIGN KEY (platform_id) REFERENCES platform (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql('ALTER TABLE game DROP developers, DROP publishers, DROP genres, DROP platforms');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE game
                ADD developers JSON DEFAULT NULL,
                ADD publishers JSON DEFAULT NULL,
                ADD genres JSON DEFAULT NULL,
                ADD platforms JSON DEFAULT NULL
        SQL);

        $this->addSql('DROP TABLE game_developer');
        $this->addSql('DROP TABLE game_publisher');
        $this->addSql('DROP TABLE game_genre');
        $this->addSql('DROP TABLE game_platform');
        $this->addSql('DROP TABLE developer');
        $this->addSql('DROP TABLE publisher');
        $this->addSql('DROP TABLE genre');
        $this->addSql('DROP TABLE platform');
    }
}
