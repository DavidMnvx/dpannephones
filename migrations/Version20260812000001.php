<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rubrique « Conseils & Actualités » : table blog_post.
 */
final class Version20260812000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table blog_post (rubrique Conseils & Actualités)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE blog_post (
                id INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                topic VARCHAR(40) DEFAULT NULL,
                excerpt LONGTEXT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                image VARCHAR(255) DEFAULT NULL,
                seo_title VARCHAR(255) DEFAULT NULL,
                meta_description VARCHAR(300) DEFAULT NULL,
                is_published TINYINT(1) DEFAULT 0 NOT NULL,
                published_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                source VARCHAR(100) DEFAULT NULL,
                UNIQUE INDEX UNIQ_BLOG_POST_SLUG (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blog_post');
    }
}
