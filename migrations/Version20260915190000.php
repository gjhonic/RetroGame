<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'steam_game: добавлено price_free — подтверждённо бесплатные игры '
            . 'исключаются из крона импорта цен (с backfill по уже собранной истории цен)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_game ADD price_free BOOLEAN NOT NULL DEFAULT false');
        $this->addSql(
            'UPDATE steam_game SET price_free = true WHERE game_id IN '
            . '(SELECT DISTINCT game_id FROM steam_game_prices WHERE price_kopecks = 0)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_game DROP price_free');
    }
}
