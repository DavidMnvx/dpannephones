<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Création de la table product_color pour gérer les couleurs
 * d'articles (utilisées surtout par les accessoires : coques, câbles, etc.).
 * Le vendeur peut définir des couleurs standard et en créer sur mesure.
 */
final class Version20260717000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table product_color + seed 12 couleurs standard';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_color (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(60) NOT NULL,
            hex VARCHAR(7) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_PRODUCT_COLOR_NAME (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $seed = [
            ['Noir',        '#000000'],
            ['Blanc',       '#FFFFFF'],
            ['Gris',        '#6B7280'],
            ['Argent',      '#C0C0C0'],
            ['Or',          '#D4AF37'],
            ['Rose Gold',   '#B76E79'],
            ['Bleu',        '#3B82F6'],
            ['Bleu nuit',   '#1E3A8A'],
            ['Rouge',       '#DC2626'],
            ['Vert',        '#16A34A'],
            ['Violet',      '#8B5CF6'],
            ['Transparent', '#F5F5F5'],
        ];
        foreach ($seed as [$name, $hex]) {
            $this->addSql(sprintf(
                "INSERT INTO product_color (name, hex, created_at) VALUES ('%s', '%s', NOW())",
                addslashes($name),
                $hex
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_color');
    }
}
