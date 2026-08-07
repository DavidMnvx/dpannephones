<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rayon Occasions unifié (option B) : les catégories "PC Gamer d'occasion" et
 * "PC Portable d'occasion" disparaissent. Leurs articles retournent dans la
 * catégorie parente avec le marqueur "occasion" (is_new = 0) — le rayon
 * Occasions de la boutique les regroupe désormais automatiquement.
 */
final class Version20260806000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Fusionne les catégories d'occasion dans leurs parentes (rayon Occasions unifié)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE article SET categorie = 'pc_gamer', is_new = 0 WHERE categorie = 'pc_gamer_occasion'");
        $this->addSql("UPDATE article SET categorie = 'pc_portable', is_new = 0 WHERE categorie = 'pc_portable_occasion'");
        $this->addSql("DELETE FROM category WHERE slug IN ('pc_gamer_occasion', 'pc_portable_occasion')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO category (slug, label, icon, shipping_tier, specs_template, position) VALUES
            ('pc_gamer_occasion',    'PC Gamer d''occasion',   'fas fa-gamepad', 4, 'pc_gamer',    20),
            ('pc_portable_occasion', 'PC Portable d''occasion','fas fa-laptop',  3, 'pc_portable', 50)
        SQL);
    }
}
