<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Statistiques de fréquentation maison : un compteur par jour, par métrique
 * et par libellé (ex. id d'article). Ni cookie ni adresse IP.
 */
final class Version20261015000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table daily_stat : compteurs quotidiens de fréquentation de la boutique';
    }

    public function up(Schema $schema): void
    {
        // label NOT NULL DEFAULT '' : en MySQL, une contrainte unique laisse passer
        // plusieurs NULL, l'upsert ne trouverait jamais la ligne à incrémenter.
        $this->addSql("CREATE TABLE daily_stat (
            id INT AUTO_INCREMENT NOT NULL,
            date DATE NOT NULL,
            metric VARCHAR(30) NOT NULL,
            label VARCHAR(64) NOT NULL DEFAULT '',
            count INT NOT NULL DEFAULT 0,
            UNIQUE INDEX uniq_daily_stat (date, metric, label),
            INDEX idx_daily_stat_metric_date (metric, date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE daily_stat');
    }
}
