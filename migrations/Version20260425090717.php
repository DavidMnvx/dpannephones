<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260425090717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajout du champ sort_order avec DEFAULT 0 pour les lignes existantes
        $this->addSql('ALTER TABLE reparation ADD sort_order INT NOT NULL DEFAULT 0');
        // Initialiser sort_order = id pour préserver l'ordre actuel
        $this->addSql('UPDATE reparation SET sort_order = id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reparation DROP sort_order');
    }
}
