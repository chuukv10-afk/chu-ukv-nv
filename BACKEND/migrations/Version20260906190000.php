<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pharmacie P1 : unités et familles de médicament + données initiales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE unite_medicament (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(15) NOT NULL, libelle VARCHAR(100) NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_UNITE_MEDICAMENT_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE famille_medicament (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(15) NOT NULL, libelle VARCHAR(100) NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FAMILLE_MEDICAMENT_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $unites = [
            ['CPR', 'Comprimé', 1],
            ['GEL', 'Gélule', 2],
            ['AMP', 'Ampoule', 3],
            ['FLAC', 'Flacon', 4],
            ['ML', 'Millilitre', 5],
            ['UI', 'Unité internationale', 6],
            ['SACH', 'Sachet', 7],
            ['TUBE', 'Tube', 8],
        ];

        foreach ($unites as [$code, $libelle, $ordre]) {
            $this->addSql(
                'INSERT INTO unite_medicament (code, libelle, ordre, statut, created_at) VALUES (:code, :libelle, :ordre, :statut, :created_at)',
                [
                    'code' => $code,
                    'libelle' => $libelle,
                    'ordre' => $ordre,
                    'statut' => 'ACTIF',
                    'created_at' => $now,
                ],
            );
        }

        $familles = [
            ['ATB', 'Antibiotiques', 1],
            ['ANTALG', 'Antalgiques', 2],
            ['AINS', 'Anti-inflammatoires', 3],
            ['SOLUTE', 'Solutés', 4],
            ['CARDIO', 'Cardiologie', 5],
            ['GASTRO', 'Gastro-entérologie', 6],
            ['RESP', 'Pneumologie', 7],
            ['NEURO', 'Neurologie', 8],
            ['VIT', 'Vitamines', 9],
            ['AUTRE', 'Autre', 99],
        ];

        foreach ($familles as [$code, $libelle, $ordre]) {
            $this->addSql(
                'INSERT INTO famille_medicament (code, libelle, ordre, statut, created_at) VALUES (:code, :libelle, :ordre, :statut, :created_at)',
                [
                    'code' => $code,
                    'libelle' => $libelle,
                    'ordre' => $ordre,
                    'statut' => 'ACTIF',
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE unite_medicament');
        $this->addSql('DROP TABLE famille_medicament');
    }
}
