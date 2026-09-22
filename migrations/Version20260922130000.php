<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Публичный каталог получает фильтр "недоступно в РФ" — для него нужна
 * доступность игры в Steam для региона RU прямо на Game (раньше была
 * только на SteamGame, привязанной к конкретной Steam-записи, а не ко
 * всем Game сразу).
 */
final class Version20260922130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'game: добавлена денормализованная доступность в РФ (is_available_in_russia)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD is_available_in_russia BOOLEAN NOT NULL DEFAULT true');
        $this->addSql('ALTER TABLE game ALTER is_available_in_russia DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP is_available_in_russia');
    }
}
