<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Article.description : VARCHAR(255) → TEXT.
 * Permet des descriptions produit plus longues (~65 000 caractères)
 * sans erreur SQL en cas de saisie longue.
 */
final class Version20260717000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Article.description : VARCHAR(255) → TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article MODIFY description TEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article MODIFY description VARCHAR(255) NOT NULL');
    }
}
