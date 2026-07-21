<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table popular_phone_model + seed d'une base de modèles populaires (2020-2024).
 * Sépare le catalogue accessoires de la table model utilisée par les réparations.
 */
final class Version20260721000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table popular_phone_model + seed catalogue téléphones populaires';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE popular_phone_model (
            id INT AUTO_INCREMENT NOT NULL,
            brand VARCHAR(60) NOT NULL,
            model_name VARCHAR(120) NOT NULL,
            famille VARCHAR(60) DEFAULT NULL,
            sort_order INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_PPM_BRAND_MODEL (brand, model_name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $seed = [
            // ── Apple ──
            ['Apple', 'iPhone SE (2020)',    null],
            ['Apple', 'iPhone SE (2022)',    null],
            ['Apple', 'iPhone 12 mini',      null],
            ['Apple', 'iPhone 12',           null],
            ['Apple', 'iPhone 12 Pro',       null],
            ['Apple', 'iPhone 12 Pro Max',   null],
            ['Apple', 'iPhone 13 mini',      null],
            ['Apple', 'iPhone 13',           null],
            ['Apple', 'iPhone 13 Pro',       null],
            ['Apple', 'iPhone 13 Pro Max',   null],
            ['Apple', 'iPhone 14',           null],
            ['Apple', 'iPhone 14 Plus',      null],
            ['Apple', 'iPhone 14 Pro',       null],
            ['Apple', 'iPhone 14 Pro Max',   null],
            ['Apple', 'iPhone 15',           null],
            ['Apple', 'iPhone 15 Plus',      null],
            ['Apple', 'iPhone 15 Pro',       null],
            ['Apple', 'iPhone 15 Pro Max',   null],
            ['Apple', 'iPhone 16',           null],
            ['Apple', 'iPhone 16 Plus',      null],
            ['Apple', 'iPhone 16 Pro',       null],
            ['Apple', 'iPhone 16 Pro Max',   null],

            // ── Samsung Galaxy S ──
            ['Samsung', 'Galaxy S20',        'Série S'],
            ['Samsung', 'Galaxy S20+',       'Série S'],
            ['Samsung', 'Galaxy S20 Ultra',  'Série S'],
            ['Samsung', 'Galaxy S20 FE',     'Série S'],
            ['Samsung', 'Galaxy S21',        'Série S'],
            ['Samsung', 'Galaxy S21+',       'Série S'],
            ['Samsung', 'Galaxy S21 Ultra',  'Série S'],
            ['Samsung', 'Galaxy S21 FE',     'Série S'],
            ['Samsung', 'Galaxy S22',        'Série S'],
            ['Samsung', 'Galaxy S22+',       'Série S'],
            ['Samsung', 'Galaxy S22 Ultra',  'Série S'],
            ['Samsung', 'Galaxy S23',        'Série S'],
            ['Samsung', 'Galaxy S23+',       'Série S'],
            ['Samsung', 'Galaxy S23 Ultra',  'Série S'],
            ['Samsung', 'Galaxy S23 FE',     'Série S'],
            ['Samsung', 'Galaxy S24',        'Série S'],
            ['Samsung', 'Galaxy S24+',       'Série S'],
            ['Samsung', 'Galaxy S24 Ultra',  'Série S'],
            ['Samsung', 'Galaxy S24 FE',     'Série S'],
            ['Samsung', 'Galaxy S25',        'Série S'],
            ['Samsung', 'Galaxy S25+',       'Série S'],
            ['Samsung', 'Galaxy S25 Ultra',  'Série S'],

            // ── Samsung Galaxy A ──
            ['Samsung', 'Galaxy A13',        'Série A'],
            ['Samsung', 'Galaxy A14',        'Série A'],
            ['Samsung', 'Galaxy A15',        'Série A'],
            ['Samsung', 'Galaxy A23',        'Série A'],
            ['Samsung', 'Galaxy A24',        'Série A'],
            ['Samsung', 'Galaxy A25',        'Série A'],
            ['Samsung', 'Galaxy A33',        'Série A'],
            ['Samsung', 'Galaxy A34',        'Série A'],
            ['Samsung', 'Galaxy A35',        'Série A'],
            ['Samsung', 'Galaxy A52',        'Série A'],
            ['Samsung', 'Galaxy A53',        'Série A'],
            ['Samsung', 'Galaxy A54',        'Série A'],
            ['Samsung', 'Galaxy A55',        'Série A'],

            // ── Samsung Galaxy Z (foldables) ──
            ['Samsung', 'Galaxy Z Flip 3',   'Foldable'],
            ['Samsung', 'Galaxy Z Flip 4',   'Foldable'],
            ['Samsung', 'Galaxy Z Flip 5',   'Foldable'],
            ['Samsung', 'Galaxy Z Flip 6',   'Foldable'],
            ['Samsung', 'Galaxy Z Fold 3',   'Foldable'],
            ['Samsung', 'Galaxy Z Fold 4',   'Foldable'],
            ['Samsung', 'Galaxy Z Fold 5',   'Foldable'],
            ['Samsung', 'Galaxy Z Fold 6',   'Foldable'],

            // ── Samsung Galaxy Note ──
            ['Samsung', 'Galaxy Note 20',    'Note'],
            ['Samsung', 'Galaxy Note 20 Ultra', 'Note'],

            // ── Xiaomi ──
            ['Xiaomi', 'Xiaomi 11',          'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 11 Pro',      'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 12',          'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 12 Pro',      'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 13',          'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 13 Pro',      'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 14',          'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 14 Pro',      'Mi/Xiaomi'],
            ['Xiaomi', 'Xiaomi 14 Ultra',    'Mi/Xiaomi'],
            ['Xiaomi', 'Redmi Note 10',      'Redmi Note'],
            ['Xiaomi', 'Redmi Note 10 Pro',  'Redmi Note'],
            ['Xiaomi', 'Redmi Note 11',      'Redmi Note'],
            ['Xiaomi', 'Redmi Note 11 Pro',  'Redmi Note'],
            ['Xiaomi', 'Redmi Note 12',      'Redmi Note'],
            ['Xiaomi', 'Redmi Note 12 Pro',  'Redmi Note'],
            ['Xiaomi', 'Redmi Note 13',      'Redmi Note'],
            ['Xiaomi', 'Redmi Note 13 Pro',  'Redmi Note'],
            ['Xiaomi', 'Poco X4 Pro',        'Poco'],
            ['Xiaomi', 'Poco X5',            'Poco'],
            ['Xiaomi', 'Poco X5 Pro',        'Poco'],
            ['Xiaomi', 'Poco X6',            'Poco'],
            ['Xiaomi', 'Poco X6 Pro',        'Poco'],
            ['Xiaomi', 'Poco F5',            'Poco'],
            ['Xiaomi', 'Poco F5 Pro',        'Poco'],
            ['Xiaomi', 'Poco F6',            'Poco'],

            // ── Google Pixel ──
            ['Google', 'Pixel 6',            'Pixel'],
            ['Google', 'Pixel 6a',           'Pixel'],
            ['Google', 'Pixel 6 Pro',        'Pixel'],
            ['Google', 'Pixel 7',            'Pixel'],
            ['Google', 'Pixel 7a',           'Pixel'],
            ['Google', 'Pixel 7 Pro',        'Pixel'],
            ['Google', 'Pixel 8',            'Pixel'],
            ['Google', 'Pixel 8a',           'Pixel'],
            ['Google', 'Pixel 8 Pro',        'Pixel'],
            ['Google', 'Pixel 9',            'Pixel'],
            ['Google', 'Pixel 9 Pro',        'Pixel'],
            ['Google', 'Pixel 9 Pro XL',     'Pixel'],

            // ── Huawei ──
            ['Huawei', 'P30',                'Série P'],
            ['Huawei', 'P30 Pro',            'Série P'],
            ['Huawei', 'P40',                'Série P'],
            ['Huawei', 'P40 Pro',            'Série P'],
            ['Huawei', 'P50',                'Série P'],
            ['Huawei', 'P50 Pro',            'Série P'],
            ['Huawei', 'P60 Pro',            'Série P'],
            ['Huawei', 'Mate 40 Pro',        'Mate'],
            ['Huawei', 'Mate 50 Pro',        'Mate'],

            // ── Honor ──
            ['Honor', 'Honor 50',            null],
            ['Honor', 'Honor 70',            null],
            ['Honor', 'Honor 90',            null],
            ['Honor', 'Magic 5 Pro',         'Magic'],
            ['Honor', 'Magic 6 Pro',         'Magic'],

            // ── OnePlus ──
            ['OnePlus', 'OnePlus 8',         null],
            ['OnePlus', 'OnePlus 8 Pro',     null],
            ['OnePlus', 'OnePlus 9',         null],
            ['OnePlus', 'OnePlus 9 Pro',     null],
            ['OnePlus', 'OnePlus 10 Pro',    null],
            ['OnePlus', 'OnePlus 11',        null],
            ['OnePlus', 'OnePlus 12',        null],
            ['OnePlus', 'OnePlus Nord 2',    'Nord'],
            ['OnePlus', 'OnePlus Nord 3',    'Nord'],
            ['OnePlus', 'OnePlus Nord 4',    'Nord'],

            // ── Oppo ──
            ['Oppo', 'Find X3 Pro',          'Find'],
            ['Oppo', 'Find X5 Pro',          'Find'],
            ['Oppo', 'Find X6 Pro',          'Find'],
            ['Oppo', 'Reno 6',               'Reno'],
            ['Oppo', 'Reno 8',               'Reno'],
            ['Oppo', 'Reno 10',              'Reno'],
            ['Oppo', 'Reno 11',              'Reno'],
            ['Oppo', 'Reno 12',              'Reno'],

            // ── Nothing ──
            ['Nothing', 'Nothing Phone (1)', null],
            ['Nothing', 'Nothing Phone (2)', null],
            ['Nothing', 'Nothing Phone (2a)', null],
        ];

        foreach ($seed as $i => [$brand, $modelName, $famille]) {
            $familleSql = $famille === null ? 'NULL' : "'" . addslashes($famille) . "'";
            $this->addSql(sprintf(
                "INSERT INTO popular_phone_model (brand, model_name, famille, sort_order, created_at) VALUES ('%s', '%s', %s, %d, NOW())",
                addslashes($brand),
                addslashes($modelName),
                $familleSql,
                $i
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE popular_phone_model');
    }
}
