<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime les colonnes formule/forfait_paye/date_expiration, obsolètes depuis
 * le passage à un modèle sans abonnement payant — elles n'existent plus dans
 * l'entité Professional depuis plusieurs migrations mais n'avaient jamais été
 * retirées de la base. Vérifié avant migration : les 329 fiches en prod ont
 * toutes formule='', forfait_paye=0, date_expiration=NULL (aucune donnée réelle).
 */
final class Version20260812180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire les colonnes professional.formule/forfait_paye/date_expiration, inutilisées depuis le retrait des abonnements payants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE professional DROP date_expiration, DROP formule, DROP forfait_paye, CHANGE nom_responsable nom_responsable VARCHAR(100) DEFAULT NULL, CHANGE prenom_responsable prenom_responsable VARCHAR(100) DEFAULT NULL, CHANGE profession profession VARCHAR(255) DEFAULT NULL, CHANGE siret siret VARCHAR(20) DEFAULT NULL, CHANGE adresse_rue adresse_rue VARCHAR(255) DEFAULT NULL, CHANGE code_postal code_postal VARCHAR(10) DEFAULT NULL, CHANGE ville ville VARCHAR(100) DEFAULT NULL, CHANGE latitude latitude DOUBLE PRECISION DEFAULT NULL, CHANGE longitude longitude DOUBLE PRECISION DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE site_web site_web VARCHAR(255) DEFAULT NULL, CHANGE image_name image_name VARCHAR(255) DEFAULT NULL, CHANGE genre genre VARCHAR(10) DEFAULT NULL, CHANGE site_ecommerce site_ecommerce VARCHAR(255) DEFAULT NULL, CHANGE login_token login_token VARCHAR(64) DEFAULT NULL, CHANGE login_token_expires login_token_expires DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE email_verification_token email_verification_token VARCHAR(64) DEFAULT NULL, CHANGE email_verified_at email_verified_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE pays pays VARCHAR(5) DEFAULT 'FR'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE professional ADD date_expiration DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD formule VARCHAR(20) NOT NULL, ADD forfait_paye TINYINT(1) NOT NULL, CHANGE nom_responsable nom_responsable VARCHAR(100) NOT NULL, CHANGE prenom_responsable prenom_responsable VARCHAR(100) NOT NULL, CHANGE profession profession VARCHAR(255) NOT NULL, CHANGE siret siret VARCHAR(20) NOT NULL, CHANGE adresse_rue adresse_rue VARCHAR(255) NOT NULL, CHANGE code_postal code_postal VARCHAR(10) NOT NULL, CHANGE ville ville VARCHAR(100) NOT NULL, CHANGE telephone telephone VARCHAR(20) NOT NULL, CHANGE pays pays VARCHAR(5) DEFAULT 'FR' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }
}
