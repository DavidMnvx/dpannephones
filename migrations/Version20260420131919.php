<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420131919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, commande_id INT DEFAULT NULL, article_id INT DEFAULT NULL, rating SMALLINT NOT NULL, title VARCHAR(100) NOT NULL, comment LONGTEXT NOT NULL, display_name_choice VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, admin_response LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, admin_response_at DATETIME DEFAULT NULL, INDEX IDX_794381C6A76ED395 (user_id), INDEX IDX_794381C682EA2E54 (commande_id), INDEX IDX_794381C67294869C (article_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C682EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C67294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6A76ED395');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C682EA2E54');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C67294869C');
        $this->addSql('DROP TABLE review');
    }
}
