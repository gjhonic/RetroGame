<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915184413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'steam_price_import_cursor: добавлено last_popularity — курсор импорта цен '
            . 'теперь идёт по убыванию популярности игры, а не по id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_price_import_cursor ADD last_popularity INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE steam_price_import_cursor DROP last_popularity');
    }
}
