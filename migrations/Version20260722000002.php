<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tunnel de commande : snapshot du point relais Mondial Relay sur la commande.
 */
final class Version20260722000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute commande.relay_point (point relais Mondial Relay choisi)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD relay_point JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP relay_point');
    }
}
