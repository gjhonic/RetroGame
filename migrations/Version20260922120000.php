<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Название товара на plati.market (поле name/name_eng в ответе Digiseller)
 * раньше использовалось только для матчинга (см. GameMatcher) и нигде не
 * сохранялось — админке нужно видеть, как объявление называется у
 * продавца, а не только название игры в нашей БД (оно может отличаться).
 */
final class Version20260922120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'plati_game: добавлено название товара на plati.market (plati_name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plati_game ADD plati_name VARCHAR(500) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE plati_game ALTER plati_name DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plati_game DROP plati_name');
    }
}
