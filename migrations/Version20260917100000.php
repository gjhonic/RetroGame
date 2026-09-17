<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Признак "игра точно бесплатна" переносится из steam_game.price_free в
 * game.is_free — это свойство самой игры (общей сущности для всех
 * источников), а не Steam-специфичной записи (см. App\Entity\Game::isFree,
 * App\Service\Steam\PriceImportService).
 */
final class Version20260917100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Перенос признака бесплатности игры из steam_game.price_free в game.is_free';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD is_free BOOLEAN NOT NULL DEFAULT false');
        $this->addSql(
            'UPDATE game SET is_free = true FROM steam_game '
            . 'WHERE steam_game.game_id = game.id AND steam_game.price_free = true',
        );
        $this->addSql('ALTER TABLE steam_game DROP price_free');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_game ADD price_free BOOLEAN NOT NULL DEFAULT false');
        $this->addSql(
            'UPDATE steam_game SET price_free = true FROM game '
            . 'WHERE steam_game.game_id = game.id AND game.is_free = true',
        );
        $this->addSql('ALTER TABLE game DROP is_free');
    }
}
