<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902234000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passe le rôle PERSONNEL en périmètre GLOBAL pour l\'affectation automatique à la création.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE role SET perimetre = 'GLOBAL' WHERE code = 'PERSONNEL'");
        $this->addSql("UPDATE personnel_role pr
            INNER JOIN role r ON r.id = pr.role_id
            SET pr.perimetre = 'GLOBAL', pr.service_id = NULL, pr.departement_id = NULL
            WHERE r.code = 'PERSONNEL'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE role SET perimetre = 'SERVICE' WHERE code = 'PERSONNEL'");
    }
}
