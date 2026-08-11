<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout vérification email et siret_valide, suppression table subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS subscription');

        $this->addSql("ALTER TABLE professional
            ADD COLUMN email_verification_token VARCHAR(64) DEFAULT NULL,
            ADD COLUMN email_verified_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            ADD COLUMN siret_valide TINYINT(1) NOT NULL DEFAULT 0
        ");

        $this->addSql("UPDATE professional SET email_verified_at = NOW(), siret_valide = 1 WHERE statut = 'ACTIF'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE professional DROP COLUMN email_verification_token, DROP COLUMN email_verified_at, DROP COLUMN siret_valide');
    }
}
