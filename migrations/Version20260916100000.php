<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Источник наличия игр сменился с ggsel.net на plati.market (ggsel.net
 * защищён антибот-челленджем Qrator, HTML-скрапинг невозможен без
 * headless-браузера; plati.market открыт через публичный Web API
 * Digiseller — см. App\Service\Plati\PlatiClient). Данных под старыми
 * именами по факту не было — все прошлые попытки на ggsel.net упирались
 * в 401 — поэтому переименовываем таблицы вместо переноса данных.
 */
final class Version20260916100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Переименование ggsel_game/ggsel_import_cursor в plati_game/plati_import_cursor '
            . '(источник импорта сменился с ggsel.net на plati.market)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ggsel_game RENAME TO plati_game');
        $this->addSql('ALTER TABLE plati_game RENAME CONSTRAINT ggsel_game_pkey TO plati_game_pkey');
        $this->addSql('ALTER TABLE plati_game RENAME CONSTRAINT fk_ggsel_game_game TO fk_plati_game_game');
        $this->addSql('ALTER INDEX uniq_ggsel_game_game RENAME TO uniq_plati_game_game');

        $this->addSql('ALTER TABLE ggsel_import_cursor RENAME TO plati_import_cursor');
        $this->addSql(
            'ALTER TABLE plati_import_cursor RENAME CONSTRAINT ggsel_import_cursor_pkey TO plati_import_cursor_pkey',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE plati_import_cursor RENAME CONSTRAINT plati_import_cursor_pkey TO ggsel_import_cursor_pkey',
        );
        $this->addSql('ALTER TABLE plati_import_cursor RENAME TO ggsel_import_cursor');

        $this->addSql('ALTER INDEX uniq_plati_game_game RENAME TO uniq_ggsel_game_game');
        $this->addSql('ALTER TABLE plati_game RENAME CONSTRAINT fk_plati_game_game TO fk_ggsel_game_game');
        $this->addSql('ALTER TABLE plati_game RENAME CONSTRAINT plati_game_pkey TO ggsel_game_pkey');
        $this->addSql('ALTER TABLE plati_game RENAME TO ggsel_game');
    }
}
