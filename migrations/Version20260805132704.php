<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260805132704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE horaire RENAME INDEX uniq_horaire_jour TO UNIQ_BBC83DB63C54E9B9');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_photo_article TO IDX_14B784187294869C');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_photo_color TO IDX_14B784187ADA1FB5');
        $this->addSql('ALTER TABLE product_color RENAME INDEX uniq_product_color_name TO UNIQ_C70A33B55E237E06');
        $this->addSql('ALTER TABLE reparation CHANGE sort_order sort_order INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE description description TEXT NOT NULL');
        $this->addSql('ALTER TABLE horaire RENAME INDEX uniq_bbc83db63c54e9b9 TO UNIQ_HORAIRE_JOUR');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_14b784187294869c TO IDX_PHOTO_ARTICLE');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_14b784187ada1fb5 TO IDX_PHOTO_COLOR');
        $this->addSql('ALTER TABLE product_color RENAME INDEX uniq_c70a33b55e237e06 TO UNIQ_PRODUCT_COLOR_NAME');
        $this->addSql('ALTER TABLE reparation CHANGE sort_order sort_order INT DEFAULT 0 NOT NULL');
    }
}
