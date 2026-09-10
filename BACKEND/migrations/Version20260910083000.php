<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910083000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aptitude : taille en mètres, classe Dickson, échelles OMS/Pignet/Ruffier.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('certificat_aptitude')) {
            return;
        }

        $table = $schema->getTable('certificat_aptitude');

        if ($table->hasColumn('taille_cm') && !$table->hasColumn('taille_m')) {
            $this->addSql('ALTER TABLE certificat_aptitude CHANGE taille_cm taille_m NUMERIC(5, 2) DEFAULT NULL');
            $this->addSql('UPDATE certificat_aptitude SET taille_m = ROUND(taille_m / 100, 2) WHERE taille_m IS NOT NULL AND taille_m > 3');
        }

        if ($table->hasColumn('imc_classe')) {
            $this->addSql('ALTER TABLE certificat_aptitude CHANGE imc_classe imc_classe VARCHAR(20) DEFAULT NULL');
        }
        if ($table->hasColumn('pignet_robustesse')) {
            $this->addSql('ALTER TABLE certificat_aptitude CHANGE pignet_robustesse pignet_robustesse VARCHAR(20) DEFAULT NULL');
        }
        if ($table->hasColumn('ruffier_classe')) {
            $this->addSql('ALTER TABLE certificat_aptitude CHANGE ruffier_classe ruffier_classe VARCHAR(20) DEFAULT NULL');
        }

        if (!$table->hasColumn('dickson_classe')) {
            $this->addSql('ALTER TABLE certificat_aptitude ADD dickson_classe VARCHAR(20) DEFAULT NULL');
        }

        $this->addSql("UPDATE certificat_aptitude SET imc_classe = 'OBESITE_I' WHERE imc_classe = 'OBESITE'");
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('certificat_aptitude')) {
            return;
        }

        $table = $schema->getTable('certificat_aptitude');

        if ($table->hasColumn('dickson_classe')) {
            $this->addSql('ALTER TABLE certificat_aptitude DROP dickson_classe');
        }

        if ($table->hasColumn('taille_m') && !$table->hasColumn('taille_cm')) {
            $this->addSql('UPDATE certificat_aptitude SET taille_m = ROUND(taille_m * 100, 2) WHERE taille_m IS NOT NULL AND taille_m <= 3');
            $this->addSql('ALTER TABLE certificat_aptitude CHANGE taille_m taille_cm NUMERIC(6, 2) DEFAULT NULL');
        }
    }
}
