<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table photo : photos supplémentaires (galerie) pour les articles de la boutique.
 * L'image principale reste sur article.image ; ces photos s'ajoutent en galerie.
 */
final class Version20260716000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table photo (galerie multi-photos par article)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE photo (
            id INT AUTO_INCREMENT NOT NULL,
            article_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            position INT NOT NULL,
            INDEX IDX_PHOTO_ARTICLE (article_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_PHOTO_ARTICLE FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_PHOTO_ARTICLE');
        $this->addSql('DROP TABLE photo');
    }
}
