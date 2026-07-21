<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute une clé étrangère color_id (nullable) sur photo pour permettre
 * qu'un article ait plusieurs couleurs, chacune avec ses propres photos.
 */
final class Version20260721000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo.color_id : lien vers ProductColor (nullable, permet articles multi-couleurs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo ADD color_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_PHOTO_COLOR FOREIGN KEY (color_id) REFERENCES product_color (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PHOTO_COLOR ON photo (color_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_PHOTO_COLOR');
        $this->addSql('DROP INDEX IDX_PHOTO_COLOR ON photo');
        $this->addSql('ALTER TABLE photo DROP color_id');
    }
}
