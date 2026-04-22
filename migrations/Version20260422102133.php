<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422102133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, type VARCHAR(20) NOT NULL, value NUMERIC(10, 2) NOT NULL, is_active TINYINT(1) NOT NULL, valid_from DATETIME DEFAULT NULL, valid_until DATETIME DEFAULT NULL, usage_limit INT DEFAULT NULL, usage_count INT NOT NULL, min_cart_amount NUMERIC(10, 2) DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX promo_code_unique (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE commande ADD promo_code_id INT DEFAULT NULL, ADD promo_code_used VARCHAR(50) DEFAULT NULL, ADD discount_amount NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6EEAA67D2FAE4625 ON commande (promo_code_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D2FAE4625');
        $this->addSql('DROP TABLE promo_code');
        $this->addSql('DROP INDEX IDX_6EEAA67D2FAE4625 ON commande');
        $this->addSql('ALTER TABLE commande DROP promo_code_id, DROP promo_code_used, DROP discount_amount');
    }
}
