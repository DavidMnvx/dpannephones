<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réparations universelles / services communs.
 *
 * - Permet à `reparation.model_id` d'être NULL (réparations communes à tous les modèles).
 * - Ajoute le flag `is_universal` (BOOL) et `icon` (VARCHAR FA class).
 * - Insère les 4 services communs jusqu'ici hardcodés dans le template
 *   (Tiroir SIM, Désoxydation, Transfert de données, Diagnostique) pour qu'ils
 *   soient désormais éditables depuis l'admin CRUD.
 */
final class Version20260429180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réparations universelles : model_id NULLable, ajout is_universal + icon, seed des 4 services communs';
    }

    public function up(Schema $schema): void
    {
        // 1. Rendre model_id nullable
        $this->addSql('ALTER TABLE reparation MODIFY model_id INT DEFAULT NULL');

        // 2. Ajouter is_universal (default false)
        $this->addSql('ALTER TABLE reparation ADD is_universal TINYINT(1) NOT NULL DEFAULT 0');

        // 3. Ajouter icon (nullable)
        $this->addSql('ALTER TABLE reparation ADD icon VARCHAR(50) DEFAULT NULL');

        // 4. Seed des 4 services communs (équivalent du tableau hardcodé du template)
        //    On utilise INSERT IGNORE pour ne pas planter si la migration est rejouée
        //    (par exemple si l'admin a déjà créé ces entrées à la main).
        $this->addSql("INSERT INTO reparation (name, prix, description, has_phone, has_tablet, sort_order, model_id, is_universal, icon) VALUES
            ('Tiroir SIM',           10.00, 'Remplacement tiroir SIM — pièce sur commande 24-48h.',          1, 1, 10, NULL, 1, 'fa-sim-card'),
            ('Désoxydation',         49.00, 'Traitement après contact avec liquide — résultat sous 24-48h.', 1, 1, 20, NULL, 1, 'fa-tint'),
            ('Transfert de données', 19.00, 'Migration complète de vos données vers un nouvel appareil.',    1, 1, 30, NULL, 1, 'fa-exchange-alt'),
            ('Diagnostique',          0.00, 'Analyse complète directement en boutique — sans rendez-vous.',  1, 1, 40, NULL, 1, 'fa-stethoscope')");
    }

    public function down(Schema $schema): void
    {
        // Suppression du seed
        $this->addSql("DELETE FROM reparation WHERE is_universal = 1");

        // Suppression des colonnes
        $this->addSql('ALTER TABLE reparation DROP icon');
        $this->addSql('ALTER TABLE reparation DROP is_universal');

        // Restaurer model_id NOT NULL (uniquement si pas de NULL restant)
        $this->addSql('ALTER TABLE reparation MODIFY model_id INT NOT NULL');
    }
}
