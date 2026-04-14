<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add specs to article, options to cart_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD specs JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE cart_item ADD options JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN specs');
        $this->addSql('ALTER TABLE cart_item DROP COLUMN options');
    }
}
