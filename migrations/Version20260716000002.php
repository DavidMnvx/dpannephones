<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grade des articles : élargit la colonne à 10 chars et la rend nullable.
 * Permet d'accepter "Neuf" comme valeur et l'absence de grade (article sans classement).
 */
final class Version20260716000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Article.grade : VARCHAR(10) NULLABLE (accueil valeur "Neuf" + absence de grade)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article MODIFY grade VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article MODIFY grade VARCHAR(2) NOT NULL');
    }
}
