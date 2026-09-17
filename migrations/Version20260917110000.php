<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * У игры на plati.market может быть несколько продавцов (импортируются до
 * 3 самых продаваемых совпавших объявлений, см.
 * App\Service\Plati\GameImportService::TOP_SELLERS_LIMIT) — plati_game
 * перестаёт быть 1:1 с game (снимается уникальность game_id, добавляется
 * seller_name), а plati_game_prices привязывается к конкретному продавцу
 * (plati_game_id), а не к игре: у каждого продавца своя цена.
 *
 * На момент миграции у каждой игры был максимум один PlatiGame, поэтому
 * перепривязка plati_game_prices.game_id -> plati_game_id однозначна
 * (JOIN по game_id).
 */
final class Version20260917110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'plati_game: несколько продавцов на игру (снята уникальность game_id, добавлен seller_name); '
            . 'plati_game_prices: привязка к продавцу (plati_game_id) вместо игры';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plati_game ADD seller_name VARCHAR(255) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE plati_game ALTER seller_name DROP DEFAULT');
        $this->addSql('DROP INDEX uniq_plati_game_game');
        $this->addSql('CREATE INDEX idx_plati_game_game ON plati_game (game_id)');

        $this->addSql('ALTER TABLE plati_game_prices ADD plati_game_id INT');
        $this->addSql(
            'UPDATE plati_game_prices SET plati_game_id = ('
            . 'SELECT plati_game.id FROM plati_game WHERE plati_game.game_id = plati_game_prices.game_id'
            . ')',
        );
        $this->addSql('ALTER TABLE plati_game_prices ALTER plati_game_id SET NOT NULL');
        $this->addSql('ALTER TABLE plati_game_prices DROP CONSTRAINT fk_plati_game_prices_game');
        $this->addSql('DROP INDEX uniq_plati_game_prices_game_date');
        $this->addSql('ALTER TABLE plati_game_prices DROP game_id');
        $this->addSql(
            'ALTER TABLE plati_game_prices ADD CONSTRAINT fk_plati_game_prices_plati_game '
            . 'FOREIGN KEY (plati_game_id) REFERENCES plati_game (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_plati_game_prices_plati_game_date '
            . 'ON plati_game_prices (plati_game_id, date)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plati_game_prices ADD game_id INT');
        $this->addSql(
            'UPDATE plati_game_prices SET game_id = ('
            . 'SELECT plati_game.game_id FROM plati_game WHERE plati_game.id = plati_game_prices.plati_game_id'
            . ')',
        );
        $this->addSql('ALTER TABLE plati_game_prices ALTER game_id SET NOT NULL');
        $this->addSql('DROP INDEX uniq_plati_game_prices_plati_game_date');
        $this->addSql('ALTER TABLE plati_game_prices DROP CONSTRAINT fk_plati_game_prices_plati_game');
        $this->addSql('ALTER TABLE plati_game_prices DROP plati_game_id');
        $this->addSql(
            'ALTER TABLE plati_game_prices ADD CONSTRAINT fk_plati_game_prices_game '
            . 'FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_plati_game_prices_game_date ON plati_game_prices (game_id, date)');

        $this->addSql('DROP INDEX idx_plati_game_game');
        $this->addSql('CREATE UNIQUE INDEX uniq_plati_game_game ON plati_game (game_id)');
        $this->addSql('ALTER TABLE plati_game DROP seller_name');
    }
}
