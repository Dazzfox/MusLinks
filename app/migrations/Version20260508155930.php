<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508155930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE professional ADD type VARCHAR(20) NOT NULL, CHANGE nom_responsable nom_responsable VARCHAR(100) DEFAULT NULL, CHANGE prenom_responsable prenom_responsable VARCHAR(100) DEFAULT NULL, CHANGE profession profession VARCHAR(255) DEFAULT NULL, CHANGE siret siret VARCHAR(20) DEFAULT NULL, CHANGE adresse_rue adresse_rue VARCHAR(255) DEFAULT NULL, CHANGE code_postal code_postal VARCHAR(10) DEFAULT NULL, CHANGE ville ville VARCHAR(100) DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE professional DROP type, CHANGE nom_responsable nom_responsable VARCHAR(100) NOT NULL, CHANGE prenom_responsable prenom_responsable VARCHAR(100) NOT NULL, CHANGE profession profession VARCHAR(255) NOT NULL, CHANGE siret siret VARCHAR(20) NOT NULL, CHANGE adresse_rue adresse_rue VARCHAR(255) NOT NULL, CHANGE code_postal code_postal VARCHAR(10) NOT NULL, CHANGE ville ville VARCHAR(100) NOT NULL, CHANGE telephone telephone VARCHAR(20) NOT NULL
        SQL);
    }
}
