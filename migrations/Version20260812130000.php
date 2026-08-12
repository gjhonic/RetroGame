<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'our_game_post: добавлено поле title, short_description переведён в TEXT (rich HTML без ограничения длины)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE our_game_post ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE our_game_post SET title = LEFT(short_description, 255) WHERE title = \'\'');
        $this->addSql('ALTER TABLE our_game_post ALTER COLUMN title DROP DEFAULT');
        $this->addSql('ALTER TABLE our_game_post ALTER COLUMN short_description TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE our_game_post ALTER COLUMN short_description TYPE VARCHAR(500)');
        $this->addSql('ALTER TABLE our_game_post DROP COLUMN title');
    }
}
