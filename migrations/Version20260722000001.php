<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Photo principale par coloris : photo.is_main.
 * Permet de désigner, pour chaque couleur d'un article, la photo affichée
 * en premier quand le client sélectionne cette couleur.
 */
final class Version20260722000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute photo.is_main (photo principale par coloris)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo ADD is_main TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP is_main');
    }
}
