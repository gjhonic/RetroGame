<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'game/dlc/developer/publisher: name -> text вместо varchar(255) '
            . '(крон импорта падал на SQLSTATE[22001] value too long for type character varying(255) '
            . 'на длинных/транслитерированных названиях из Steam)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ALTER name TYPE TEXT');
        $this->addSql('ALTER TABLE dlc ALTER name TYPE TEXT');
        $this->addSql('ALTER TABLE developer ALTER name TYPE TEXT');
        $this->addSql('ALTER TABLE publisher ALTER name TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ALTER name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE dlc ALTER name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE developer ALTER name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE publisher ALTER name TYPE VARCHAR(255)');
    }
}
