<?php

namespace App\Service\Personnel;

use App\Entity\Grade;
use App\Entity\Personnel;
use App\Entity\Role;
use App\Entity\Service;
use App\Exception\ConflictException;
use App\Repository\GradeRepository;
use App\Repository\PersonnelRepository;
use App\Repository\RoleRepository;
use App\Repository\ServiceRepository;
use App\Service\Role\RoleProvisioner;
use Doctrine\ORM\EntityManagerInterface;

final class ImportLegacyPersonnelService
{
    private const SERVICE_CODE_MAP = [
        'MI' => 'MI',
        'URG' => 'URG',
        'INFO' => 'INFO',
        'CHURG' => 'CHIRG',
        'GYNE' => 'GYNECO',
        'PED' => 'PED',
        'LABO' => 'LABO',
        'PHAR' => 'PHAR',
        'IMG' => 'IMG',
        'INF' => 'INFIRMIE',
        'DIRF' => 'DIRF',
        'DA' => 'DIRADM',
        'LM' => 'LOGMAINT',
    ];

    private const FONCTION_ROLE_MAP = [
        'pharmacienne' => 'PHARMACIENNE',
        'responsable de la pharmacie' => 'RES_PHAR',
        'intendant' => 'ITD',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonnelRepository $personnelRepository,
        private readonly GradeRepository $gradeRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly RoleRepository $roleRepository,
        private readonly RoleProvisioner $roleProvisioner,
    ) {
    }

    /**
     * @return array{
     *     created: int,
     *     skipped: int,
     *     grades: int,
     *     warnings: list<string>,
     *     actions: list<array{sigaiId: int, nom: string, telephone: string, action: string, detail: string}>
     * }
     */
    public function previewFromJson(string $jsonPath): array
    {
        return $this->run($this->loadAgents($jsonPath), true);
    }

    /**
     * @return array{
     *     created: int,
     *     skipped: int,
     *     grades: int,
     *     warnings: list<string>,
     *     actions: list<array{sigaiId: int, nom: string, telephone: string, action: string, detail: string}>
     * }
     */
    public function importFromJson(string $jsonPath): array
    {
        return $this->run($this->loadAgents($jsonPath), false);
    }

    /**
     * @param list<array<string, mixed>> $agents
     * @return array{
     *     created: int,
     *     skipped: int,
     *     grades: int,
     *     warnings: list<string>,
     *     actions: list<array{sigaiId: int, nom: string, telephone: string, action: string, detail: string}>
     * }
     */
    private function run(array $agents, bool $dryRun): array
    {
        $existing = $this->personnelRepository->findAll();
        $services = $this->serviceRepository->findAll();
        $gradesCreated = 0;
        $created = 0;
        $skipped = 0;
        $warnings = [];
        $actions = [];
        $seenPhones = [];
        $knownGrades = [];

        usort($agents, static fn (array $left, array $right): int => ((int) ($left['sigai_id'] ?? 0)) <=> ((int) ($right['sigai_id'] ?? 0)));

        foreach ($agents as $agent) {
            $sigaiId = (int) ($agent['sigai_id'] ?? 0);
            $nom = $this->clip((string) ($agent['nom'] ?? ''), 50);
            $telephone = $this->clip(trim((string) ($agent['telephone'] ?? '')), 15);
            $label = trim($nom . ' ' . (string) ($agent['prenom'] ?? ''));

            $skip = $this->skipReason($agent, $existing, $seenPhones);
            if (null !== $skip) {
                ++$skipped;
                $actions[] = $this->action($sigaiId, $label, $telephone, 'ignorer', $skip);
                continue;
            }
            if ('' === $telephone || '' === $nom) {
                ++$skipped;
                $actions[] = $this->action($sigaiId, $label, $telephone, 'ignorer', 'Nom ou téléphone manquant.');
                continue;
            }

            $seenPhones[$telephone] = $sigaiId;
            $service = $this->resolveService($agent, $services);
            $grade = $this->resolveGrade($agent, $dryRun, $gradesCreated, $knownGrades);

            if ($dryRun) {
                ++$created;
                $detail = $service ? sprintf('Service %s.', (string) $service->getCode()) : 'Sans service.';
                $actions[] = $this->action($sigaiId, $label, $telephone, 'créer', $detail);
                continue;
            }

            $personnel = $this->persistAgent($agent, $nom, $telephone, $service, $grade);
            $existing[] = $personnel;
            ++$created;
            $actions[] = $this->action($sigaiId, $label, $telephone, 'créer', 'Enregistré.');
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'grades' => $gradesCreated,
            'warnings' => $warnings,
            'actions' => $actions,
        ];
    }

    /**
     * @param list<Personnel> $existing
     * @param array<string, int> $seenPhones
     */
    private function skipReason(array $agent, array $existing, array $seenPhones): ?string
    {
        $telephone = trim((string) ($agent['telephone'] ?? ''));
        $matricule = $this->normalizeMatricule($agent['matricule'] ?? null);

        if ('' !== $telephone && isset($seenPhones[$telephone])) {
            return sprintf('Doublon SIGAI du téléphone (id %d conservé).', $seenPhones[$telephone]);
        }

        foreach ($existing as $personnel) {
            if ('' !== $telephone && $personnel->getTelephone() === $telephone) {
                return sprintf('Téléphone déjà utilisé par %s.', $this->personnelLabel($personnel));
            }
            if (null !== $matricule && $personnel->getMatricule() === $matricule) {
                return sprintf('Matricule déjà utilisé par %s.', $this->personnelLabel($personnel));
            }
            if ($this->isSamePerson($agent, $personnel)) {
                return sprintf('Déjà en base : %s.', $this->personnelLabel($personnel));
            }
        }

        return null;
    }

    private function persistAgent(
        array $agent,
        string $nom,
        string $telephone,
        ?Service $service,
        ?Grade $grade,
    ): Personnel {
        $createdAt = $this->parseDate((string) ($agent['createdAt'] ?? '')) ?? new \DateTimeImmutable();
        $type = Personnel::isValidType((string) ($agent['type'] ?? ''))
            ? Personnel::normalizeType((string) $agent['type'])
            : Personnel::TYPE_ADMINISTRATIF;
        $status = Personnel::isValidStatus((string) ($agent['status'] ?? ''))
            ? Personnel::normalizeStatus((string) $agent['status'])
            : Personnel::STATUS_ACTIF;

        $personnel = (new Personnel())
            ->setNom($nom)
            ->setPostNom($this->clip((string) ($agent['postNom'] ?? ''), 50) ?: $nom)
            ->setPrenom($this->nullableClip($agent['prenom'] ?? null, 50))
            ->setTelephone($telephone)
            ->setMatricule($this->normalizeMatricule($agent['matricule'] ?? null))
            ->setSexe(in_array($agent['sexe'] ?? '', ['M', 'F'], true) ? (string) $agent['sexe'] : 'M')
            ->setType($type)
            ->setStatus($status)
            ->setAdresse($this->nullableClip($agent['adresse'] ?? null, 100))
            ->setLieuNaissance($this->nullableClip($agent['lieuNaissance'] ?? null, 50))
            ->setCnome($this->nullableClip($agent['cnome'] ?? null, 20))
            ->setGrade($grade)
            ->setService($service)
            ->setCreatedAt($createdAt);
        $personnel->setPassword((string) ($agent['passwordHash'] ?? ''));

        $this->entityManager->persist($personnel);
        $this->roleProvisioner->assignDefaultPersonnelRole($personnel);
        $this->assignFonctionRole($personnel, (string) ($agent['fonction'] ?? ''));

        return $personnel;
    }

    private function assignFonctionRole(Personnel $personnel, string $fonction): void
    {
        $folded = $this->fold($fonction);
        foreach (self::FONCTION_ROLE_MAP as $needle => $roleCode) {
            if (!str_contains($folded, $this->fold($needle))) {
                continue;
            }
            $role = $this->roleRepository->findOneBy(['code' => $roleCode]);
            if (null === $role) {
                continue;
            }
            if ($this->roleProvisioner->hasRole($personnel, $roleCode)) {
                return;
            }
            $this->roleProvisioner->assign($personnel, $role);
            return;
        }
    }

    /**
     * @param list<Service> $services
     */
    private function resolveService(array $agent, array $services): ?Service
    {
        $mappedCode = self::SERVICE_CODE_MAP[(string) ($agent['serviceCode'] ?? '')] ?? null;
        if (null !== $mappedCode) {
            foreach ($services as $service) {
                if ($service->getCode() === $mappedCode) {
                    return $service;
                }
            }
        }

        $wanted = $this->fold((string) ($agent['serviceLibelle'] ?? ''));
        if ('' === $wanted) {
            return null;
        }
        foreach ($services as $service) {
            if ($this->fold((string) $service->getLibelle()) === $wanted) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @param array<string, ?Grade> $knownGrades
     */
    private function resolveGrade(array $agent, bool $dryRun, int &$gradesCreated, array &$knownGrades): ?Grade
    {
        $code = $this->clip((string) ($agent['gradeCode'] ?? ''), 8);
        if ('' === $code) {
            return null;
        }
        if (array_key_exists($code, $knownGrades)) {
            return $knownGrades[$code];
        }

        $existing = $this->gradeRepository->findOneBy(['code' => $code]);
        if (null !== $existing) {
            $knownGrades[$code] = $existing;

            return $existing;
        }
        ++$gradesCreated;
        if ($dryRun) {
            $knownGrades[$code] = null;

            return null;
        }

        $grade = (new Grade())
            ->setCode($code)
            ->setLibelle($this->clip((string) ($agent['gradeLibelle'] ?? $code), 100) ?: $code)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($grade);
        $knownGrades[$code] = $grade;

        return $grade;
    }

    private function isSamePerson(array $agent, Personnel $personnel): bool
    {
        $agentTokens = $this->nameTokens(
            (string) ($agent['nom'] ?? ''),
            (string) ($agent['postNom'] ?? ''),
            (string) ($agent['prenom'] ?? ''),
        );
        $existingTokens = $this->nameTokens(
            (string) $personnel->getNom(),
            (string) $personnel->getPostNom(),
            (string) $personnel->getPrenom(),
        );
        if ([] !== $agentTokens && $agentTokens === $existingTokens) {
            return true;
        }

        $agentNom = $this->fold((string) ($agent['nom'] ?? ''));
        $agentPrenom = $this->fold((string) ($agent['prenom'] ?? ''));

        return '' !== $agentNom && $agentNom === $this->fold((string) $personnel->getNom())
            && '' !== $agentPrenom && $agentPrenom === $this->fold((string) $personnel->getPrenom());
    }

    /** @return list<string> */
    private function nameTokens(string $nom, string $postNom, string $prenom): array
    {
        $tokens = [];
        foreach ([$nom, $postNom, $prenom] as $part) {
            $folded = $this->fold($part);
            if (strlen($folded) > 1) {
                $tokens[$folded] = $folded;
            }
        }
        $values = array_values($tokens);
        sort($values);

        return $values;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadAgents(string $jsonPath): array
    {
        if (!is_readable($jsonPath)) {
            throw new ConflictException(sprintf('Fichier introuvable : %s', $jsonPath));
        }
        $payload = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($payload) || !isset($payload['personnels']) || !is_array($payload['personnels'])) {
            throw new ConflictException('JSON agents SIGAI invalide (clé personnels manquante).');
        }

        /** @var list<array<string, mixed>> $personnels */
        $personnels = array_values($payload['personnels']);

        return $personnels;
    }

    private function normalizeMatricule(mixed $value): ?string
    {
        $text = $this->clip(trim((string) $value), 20);
        if ('' === $text || in_array(strtoupper($text), ['NU', 'NI', 'N/A', '-'], true)) {
            return null;
        }

        return $text;
    }

    private function nullableClip(mixed $value, int $max): ?string
    {
        $text = $this->clip(trim((string) $value), $max);

        return '' === $text ? null : $text;
    }

    private function clip(string $value, int $max): string
    {
        $trimmed = trim($value);
        if (mb_strlen($trimmed) <= $max) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $max);
    }

    private function fold(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (false === $ascii) {
            $ascii = $value;
        }

        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', $ascii));
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if ('' === trim($value)) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function personnelLabel(Personnel $personnel): string
    {
        return trim(sprintf(
            '%s %s %s (%s)',
            (string) $personnel->getNom(),
            (string) $personnel->getPostNom(),
            (string) $personnel->getPrenom(),
            (string) $personnel->getTelephone(),
        ));
    }

    /**
     * @return array{sigaiId: int, nom: string, telephone: string, action: string, detail: string}
     */
    private function action(int $sigaiId, string $nom, string $telephone, string $action, string $detail): array
    {
        return [
            'sigaiId' => $sigaiId,
            'nom' => $nom,
            'telephone' => $telephone,
            'action' => $action,
            'detail' => $detail,
        ];
    }
}
