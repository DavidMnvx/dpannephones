<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Achat invité : coordonnées du client sans compte (collectées par Stripe
 * Checkout) et adresse de livraison figée sur la commande.
 */
final class Version20261014000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Commande : guest_email, guest_name et shipping_address (achat invité)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD guest_email VARCHAR(180) DEFAULT NULL, ADD guest_name VARCHAR(150) DEFAULT NULL, ADD shipping_address JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP guest_email, DROP guest_name, DROP shipping_address');
    }
}
