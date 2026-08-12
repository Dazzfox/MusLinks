<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811195912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_professional_statut_visible ON professional (statut, is_visible)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE FULLTEXT INDEX idx_professional_fulltext ON professional (nom_societe, profession, domaine_activite, description)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX idx_professional_statut_visible ON professional
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_professional_fulltext ON professional
        SQL);
    }
}
