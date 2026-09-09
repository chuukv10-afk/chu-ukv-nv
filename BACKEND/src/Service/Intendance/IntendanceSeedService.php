<?php

namespace App\Service\Intendance;

use App\Entity\FamilleBien;
use App\Entity\TypeBien;
use App\Repository\FamilleBienRepository;
use App\Repository\TypeBienRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntendanceSeedService
{
    /**
     * @var list<array{code: string, libelle: string, ordre: int}>
     */
    private const FAMILLES = [
        ['code' => 'EQ', 'libelle' => 'Équipement biomédical / technique', 'ordre' => 10],
        ['code' => 'MO', 'libelle' => 'Mobilier', 'ordre' => 20],
        ['code' => 'AC', 'libelle' => 'Accessoire', 'ordre' => 30],
        ['code' => 'EL', 'libelle' => 'Électrique', 'ordre' => 40],
        ['code' => 'CL', 'libelle' => 'Froid / climatisation', 'ordre' => 50],
        ['code' => 'IT', 'libelle' => 'Informatique', 'ordre' => 60],
    ];

    /**
     * @var list<array{famille: string, code: string, libelle: string, ordre: int}>
     */
    private const TYPES = [
        ['famille' => 'IT', 'code' => 'ORDI', 'libelle' => 'Ordinateur', 'ordre' => 10],
        ['famille' => 'IT', 'code' => 'PORTABLE', 'libelle' => 'Ordinateur portable', 'ordre' => 20],
        ['famille' => 'IT', 'code' => 'ECRAN', 'libelle' => 'Écran', 'ordre' => 30],
        ['famille' => 'IT', 'code' => 'IMPRIM', 'libelle' => 'Imprimante', 'ordre' => 40],
        ['famille' => 'IT', 'code' => 'ONDULEUR', 'libelle' => 'Onduleur', 'ordre' => 50],
        ['famille' => 'IT', 'code' => 'SWITCH', 'libelle' => 'Switch / routeur', 'ordre' => 60],
        ['famille' => 'IT', 'code' => 'TEL', 'libelle' => 'Téléphone', 'ordre' => 70],
        ['famille' => 'IT', 'code' => 'AUTRE-IT', 'libelle' => 'Autre (informatique)', 'ordre' => 90],
        ['famille' => 'MO', 'code' => 'TABLE', 'libelle' => 'Table', 'ordre' => 10],
        ['famille' => 'MO', 'code' => 'BUREAU', 'libelle' => 'Bureau (meuble)', 'ordre' => 20],
        ['famille' => 'MO', 'code' => 'CHAISE', 'libelle' => 'Chaise', 'ordre' => 30],
        ['famille' => 'MO', 'code' => 'FAUTEUIL', 'libelle' => 'Fauteuil', 'ordre' => 40],
        ['famille' => 'MO', 'code' => 'LIT', 'libelle' => 'Lit', 'ordre' => 50],
        ['famille' => 'MO', 'code' => 'BRANCARD', 'libelle' => 'Brancard', 'ordre' => 60],
        ['famille' => 'MO', 'code' => 'ARMOIRE', 'libelle' => 'Armoire', 'ordre' => 70],
        ['famille' => 'MO', 'code' => 'ETAGERE', 'libelle' => 'Étagère', 'ordre' => 80],
        ['famille' => 'MO', 'code' => 'CHARIOT', 'libelle' => 'Chariot', 'ordre' => 90],
        ['famille' => 'MO', 'code' => 'AUTRE-MO', 'libelle' => 'Autre (mobilier)', 'ordre' => 99],
        ['famille' => 'EQ', 'code' => 'MONITEUR', 'libelle' => 'Moniteur de surveillance', 'ordre' => 10],
        ['famille' => 'EQ', 'code' => 'DEFIB', 'libelle' => 'Défibrillateur', 'ordre' => 20],
        ['famille' => 'EQ', 'code' => 'VENTIL', 'libelle' => 'Ventilateur médical', 'ordre' => 30],
        ['famille' => 'EQ', 'code' => 'POUSSE', 'libelle' => 'Pousse-seringue', 'ordre' => 40],
        ['famille' => 'EQ', 'code' => 'ASPIR', 'libelle' => 'Aspirateur', 'ordre' => 50],
        ['famille' => 'EQ', 'code' => 'ECG', 'libelle' => 'ECG', 'ordre' => 60],
        ['famille' => 'EQ', 'code' => 'ECHO', 'libelle' => 'Échographe', 'ordre' => 70],
        ['famille' => 'EQ', 'code' => 'ANALYS', 'libelle' => 'Analyseur de laboratoire', 'ordre' => 80],
        ['famille' => 'EQ', 'code' => 'CENTRI', 'libelle' => 'Centrifugeuse', 'ordre' => 90],
        ['famille' => 'EQ', 'code' => 'INCUB', 'libelle' => 'Incubateur / couveuse', 'ordre' => 100],
        ['famille' => 'EQ', 'code' => 'OXYM', 'libelle' => 'Oxymètre', 'ordre' => 110],
        ['famille' => 'EQ', 'code' => 'TENSIO', 'libelle' => 'Tensiomètre', 'ordre' => 120],
        ['famille' => 'EQ', 'code' => 'AUTRE-EQ', 'libelle' => 'Autre (équipement)', 'ordre' => 199],
        ['famille' => 'CL', 'code' => 'CLIM', 'libelle' => 'Climatiseur', 'ordre' => 10],
        ['famille' => 'CL', 'code' => 'FRIGO', 'libelle' => 'Réfrigérateur', 'ordre' => 20],
        ['famille' => 'CL', 'code' => 'CONGEL', 'libelle' => 'Congélateur', 'ordre' => 30],
        ['famille' => 'CL', 'code' => 'AUTRE-CL', 'libelle' => 'Autre (froid / clim)', 'ordre' => 90],
        ['famille' => 'EL', 'code' => 'LAMPE', 'libelle' => 'Lampe / éclairage', 'ordre' => 10],
        ['famille' => 'EL', 'code' => 'EXTINCT', 'libelle' => 'Extincteur', 'ordre' => 20],
        ['famille' => 'EL', 'code' => 'AUTRE-EL', 'libelle' => 'Autre (électrique)', 'ordre' => 90],
        ['famille' => 'AC', 'code' => 'SUPPORT', 'libelle' => 'Support / pied', 'ordre' => 10],
        ['famille' => 'AC', 'code' => 'CABLE', 'libelle' => 'Câble / accessoire', 'ordre' => 20],
        ['famille' => 'AC', 'code' => 'AUTRE-AC', 'libelle' => 'Autre (accessoire)', 'ordre' => 90],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FamilleBienRepository $familleBienRepository,
        private readonly TypeBienRepository $typeBienRepository,
    ) {
    }

    /**
     * @return array{familles: int, types: int}
     */
    public function seed(): array
    {
        $now = new \DateTimeImmutable();
        $famillesCreated = 0;
        $typesCreated = 0;
        $familles = [];

        foreach (self::FAMILLES as $row) {
            $famille = $this->familleBienRepository->findOneBy(['code' => $row['code']]);
            if (null === $famille) {
                $famille = (new FamilleBien())
                    ->setCode($row['code'])
                    ->setLibelle($row['libelle'])
                    ->setOrdre($row['ordre'])
                    ->setStatut(FamilleBien::STATUT_ACTIF)
                    ->setSeed(true)
                    ->setCreatedAt($now);
                $this->entityManager->persist($famille);
                ++$famillesCreated;
            } else {
                $famille->setSeed(true);
            }
            $familles[$row['code']] = $famille;
        }

        $this->entityManager->flush();

        foreach (self::TYPES as $row) {
            $type = $this->typeBienRepository->findOneBy(['code' => $row['code']]);
            $famille = $familles[$row['famille']] ?? $this->familleBienRepository->findOneBy(['code' => $row['famille']]);
            if (null === $famille) {
                continue;
            }
            if (null === $type) {
                $type = (new TypeBien())
                    ->setCode($row['code'])
                    ->setLibelle($row['libelle'])
                    ->setFamille($famille)
                    ->setOrdre($row['ordre'])
                    ->setStatut(TypeBien::STATUT_ACTIF)
                    ->setSeed(true)
                    ->setCreatedAt($now);
                $this->entityManager->persist($type);
                ++$typesCreated;
            } else {
                $type->setSeed(true);
            }
        }

        $this->entityManager->flush();

        return ['familles' => $famillesCreated, 'types' => $typesCreated];
    }
}
