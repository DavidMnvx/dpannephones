<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table horaire + seed des 7 jours';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE horaire (
            id INT AUTO_INCREMENT NOT NULL,
            jour_numero INT NOT NULL,
            jour_nom VARCHAR(20) NOT NULL,
            matin_ouverture TIME DEFAULT NULL COMMENT \'(DC2Type:time)\',
            matin_fermeture TIME DEFAULT NULL COMMENT \'(DC2Type:time)\',
            apresmidi_ouverture TIME DEFAULT NULL COMMENT \'(DC2Type:time)\',
            apresmidi_fermeture TIME DEFAULT NULL COMMENT \'(DC2Type:time)\',
            ferme TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_HORAIRE_JOUR (jour_numero),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $rows = [
            [1, 'Lundi',    '09:00:00', '12:00:00', '14:00:00', '18:00:00', 0],
            [2, 'Mardi',    '09:00:00', '12:00:00', '14:00:00', '18:00:00', 0],
            [3, 'Mercredi', '09:00:00', '12:00:00', '14:00:00', '18:00:00', 0],
            [4, 'Jeudi',    '09:00:00', '12:00:00', '14:00:00', '18:00:00', 0],
            [5, 'Vendredi', '09:00:00', '12:00:00', '14:00:00', '18:00:00', 0],
            [6, 'Samedi',   '09:00:00', '12:00:00', null,       null,       0],
            [7, 'Dimanche', null,       null,       null,       null,       1],
        ];

        foreach ($rows as [$num, $nom, $mo, $mf, $ao, $af, $ferme]) {
            $mo = $mo === null ? 'NULL' : "'$mo'";
            $mf = $mf === null ? 'NULL' : "'$mf'";
            $ao = $ao === null ? 'NULL' : "'$ao'";
            $af = $af === null ? 'NULL' : "'$af'";

            $this->addSql(sprintf(
                "INSERT INTO horaire (jour_numero, jour_nom, matin_ouverture, matin_fermeture, apresmidi_ouverture, apresmidi_fermeture, ferme) VALUES (%d, '%s', %s, %s, %s, %s, %d)",
                $num, $nom, $mo, $mf, $ao, $af, $ferme
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE horaire');
    }
}
