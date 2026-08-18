<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Familles de catégories (menus déroulants de la barre boutique), pilotées
 * depuis Admin > Catégories. Pré-remplies d'après le gabarit technique pour
 * reproduire le regroupement automatique existant (PC, Protection).
 */
final class Version20260813000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'category.family : regroupement des catégories en familles (barre boutique)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD family VARCHAR(60) DEFAULT NULL');
        $this->addSql("UPDATE category SET family = 'PC' WHERE specs_template IN ('pc_gamer', 'pc_bureautique', 'pc_portable')");
        $this->addSql("UPDATE category SET family = 'Protection' WHERE specs_template IN ('coque', 'film_hydrogel')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP family');
    }
}
