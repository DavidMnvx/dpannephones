<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la colonne `famille` (nullable) à la table model.
 * Permet de regrouper les modèles d'une marque en sous-catégories
 * sur la page publique (ex: Samsung Série A vs Série S).
 */
final class Version20260429120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne famille à model pour les sous-catégories par marque (Série A, Série S, etc.)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE model ADD famille VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE model DROP famille');
    }
}
