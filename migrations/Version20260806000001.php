<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bannière commerciale de la boutique : marqueur "article à la une".
 */
final class Version20260806000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute article.is_featured (article mis en avant dans la bannière boutique)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD is_featured TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP is_featured');
    }
}
