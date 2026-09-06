<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiel plaintes + données initiales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plainte (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(15) NOT NULL, libelle VARCHAR(100) NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_PLAINTE_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $rows = [
            ['FIEVRE', 'Fièvre', 1],
            ['DOULEUR', 'Douleur', 2],
            ['NAUSEES', 'Nausées / Vomissements', 3],
            ['DYSPNEE', 'Dyspnée', 4],
            ['CEPHALEE', 'Céphalées', 5],
            ['VERTIGES', 'Vertiges', 6],
            ['ASTHENIE', 'Asthénie', 7],
            ['DIARRHEE', 'Diarrhée', 8],
            ['CONSTIP', 'Constipation', 9],
            ['PRURIT', 'Prurit', 10],
            ['OEDEMES', 'Œdèmes', 11],
            ['SAIGNEMENT', 'Saignement', 12],
            ['CONFUSION', 'Confusion', 13],
            ['INSOMNIE', 'Insomnie', 14],
            ['ANXIETE', 'Anxiété', 15],
            ['AUTRE', 'Autre', 99],
        ];

        foreach ($rows as [$code, $libelle, $ordre]) {
            $this->addSql(
                'INSERT INTO plainte (code, libelle, ordre, statut, created_at) VALUES (:code, :libelle, :ordre, :statut, :created_at)',
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
        $this->addSql('DROP TABLE plainte');
    }
}
