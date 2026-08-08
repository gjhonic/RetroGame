<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807112537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game table';
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
                cover_image_url VARCHAR(500) DEFAULT NULL,
                rating DOUBLE PRECISION DEFAULT NULL,
                metacritic_score INT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C989D9B62 ON game (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game');
    }
}
