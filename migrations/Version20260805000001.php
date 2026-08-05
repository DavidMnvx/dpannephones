<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catégories boutique gérables depuis l'admin : table category + seed des
 * catégories historiques (mêmes slugs que l'ancienne constante en dur).
 */
final class Version20260805000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table category (catégories boutique dynamiques) + seed des catégories existantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (
            id INT AUTO_INCREMENT NOT NULL,
            slug VARCHAR(50) NOT NULL,
            label VARCHAR(80) NOT NULL,
            icon VARCHAR(50) NOT NULL DEFAULT \'fas fa-box\',
            shipping_tier INT NOT NULL DEFAULT 1,
            specs_template VARCHAR(30) DEFAULT NULL,
            position INT NOT NULL DEFAULT 0,
            UNIQUE INDEX category_slug_unique (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql(<<<'SQL'
            INSERT INTO category (slug, label, icon, shipping_tier, specs_template, position) VALUES
            ('pc_gamer',             'PC Gamer',               'fas fa-gamepad',    4, 'pc_gamer',       10),
            ('pc_gamer_occasion',    'PC Gamer d''occasion',   'fas fa-gamepad',    4, 'pc_gamer',       20),
            ('pc_bureautique',       'PC Bureautique',         'fas fa-desktop',    4, 'pc_bureautique', 30),
            ('pc_portable',          'PC Portable',            'fas fa-laptop',     3, 'pc_portable',    40),
            ('pc_portable_occasion', 'PC Portable d''occasion','fas fa-laptop',     3, 'pc_portable',    50),
            ('accessoires',          'Accessoires',            'fas fa-headphones', 1, 'accessoires',    60),
            ('coque',                'Coques',                 'fas fa-shield-alt', 1, 'coque',          70),
            ('film_hydrogel',        'Film Hydrogel',          'fas fa-tint',       1, 'film_hydrogel',  80),
            ('telephone',            'Téléphones',             'fas fa-mobile-alt', 2, 'telephone',      90)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category');
    }
}
