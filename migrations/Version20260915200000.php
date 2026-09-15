<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'steam_game: добавлено available_in_russia — последний известный статус '
            . 'доступности игры в российском Steam (с backfill по уже собранной истории цен)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_game ADD available_in_russia BOOLEAN NOT NULL DEFAULT true');
        $this->addSql(<<<'SQL'
            UPDATE steam_game SET available_in_russia = false
            WHERE game_id IN (
                SELECT latest.game_id
                FROM (
                    SELECT DISTINCT ON (game_id) game_id, price_kopecks
                    FROM steam_game_prices
                    ORDER BY game_id, date DESC
                ) latest
                WHERE latest.price_kopecks IS NULL
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_game DROP available_in_russia');
    }
}
