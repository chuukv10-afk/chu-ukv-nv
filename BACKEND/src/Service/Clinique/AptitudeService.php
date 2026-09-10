<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\AptitudeListQuery;
use App\DTO\Clinique\UpsertAptitudeInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\CertificatAptitude;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\CertificatAptitudeRepository;
use App\Repository\ServiceRepository;
use App\Service\Patient\PatientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AptitudeService
{
    private const TIMEZONE = 'Africa/Kinshasa';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificatAptitudeRepository $repository,
        private readonly ServiceRepository $serviceRepository,
        private readonly PatientService $patientService,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    public function paginate(AptitudeListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->repository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->annee,
            $query->statut,
            $query->verdict,
            $query->motif,
            $query->serviceId,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(AptitudeListQuery $query): array
    {
        $this->assertValid($query);
        $items = $this->repository->findForExport(
            $query->search,
            $query->annee,
            $query->statut,
            $query->verdict,
            $query->motif,
            $query->serviceId,
        );

        $rows = [];
        $index = 1;
        foreach ($items as $item) {
            $rows[] = [
                (string) $index,
                $item->getNumero() ?? '—',
                $item->getFullName(),
                $item->getSexe(),
                $item->getService()?->getLibelle(),
                $this->motifLabel($item),
                $item->getVerdict(),
                $item->getStatut(),
                $item->getSigneAt()?->format('d/m/Y'),
                $item->getValideJusqua()?->format('d/m/Y'),
            ];
            ++$index;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return ['N°', 'Numéro', 'Candidat', 'Sexe', 'Service', 'Motif', 'Verdict', 'Statut', 'Signé le', 'Valable jusqu\'au'];
    }

    /** @return list<array{id: int, code: string, libelle: string}> */
    public function listServices(): array
    {
        $services = $this->serviceRepository->findBy([], ['libelle' => 'ASC']);

        return array_map(static fn (Service $service): array => [
            'id' => $service->getId(),
            'code' => $service->getCode(),
            'libelle' => $service->getLibelle(),
        ], $services);
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return [
            'statuts' => CertificatAptitude::getStatuts(),
            'motifs' => CertificatAptitude::getMotifs(),
            'verdicts' => CertificatAptitude::getVerdicts(),
        ];
    }

    public function getById(int $id): CertificatAptitude
    {
        $certificat = $this->repository->find($id);
        if (null === $certificat) {
            throw new NotFoundException('Certificat d\'aptitude non trouvé.');
        }

        return $certificat;
    }

    public function create(UpsertAptitudeInput $input): CertificatAptitude
    {
        $this->assertValid($input);
        $certificat = new CertificatAptitude();
        $certificat->setAnnee($this->currentYear());
        $this->hydrate($certificat, $input);
        $this->entityManager->persist($certificat);
        $this->entityManager->flush();

        return $certificat;
    }

    public function update(int $id, UpsertAptitudeInput $input): CertificatAptitude
    {
        $this->assertValid($input);
        $certificat = $this->getById($id);
        $this->assertBrouillon($certificat);
        $this->hydrate($certificat, $input);
        $this->entityManager->flush();

        return $certificat;
    }

    public function delete(int $id): void
    {
        $certificat = $this->getById($id);
        $this->assertBrouillon($certificat);
        $this->entityManager->remove($certificat);
        $this->entityManager->flush();
    }

    public function signer(int $id): CertificatAptitude
    {
        $certificat = $this->getById($id);
        $this->assertBrouillon($certificat);
        $this->assertSignable($certificat);

        $personnel = $this->currentPersonnel();
        if (!$personnel instanceof Personnel) {
            throw new ConflictException('Impossible de déterminer le médecin signataire.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $year = (int) $now->format('Y');
        $this->entityManager->beginTransaction();
        try {
            $sequence = $this->repository->nextSequenceForYear($year);

            $certificat
                ->setStatut(CertificatAptitude::STATUT_SIGNE)
                ->setAnnee($year)
                ->setNumero(sprintf('%04d / CHU-UKV / CAP / %d', $sequence, $year))
                ->setSigneAt($now)
                ->setValideJusqua($now->modify('+3 months'))
                ->setSignePar($personnel);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $certificat;
    }

    public function annuler(int $id): CertificatAptitude
    {
        $certificat = $this->getById($id);
        if (! $certificat->isSigne()) {
            throw new ConflictException('Seul un certificat signé peut être annulé.');
        }

        $certificat
            ->setStatut(CertificatAptitude::STATUT_ANNULE)
            ->setAnnuleAt(new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)));
        $this->entityManager->flush();

        return $certificat;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(CertificatAptitude $certificat): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $expired = $certificat->isSigne()
            && null !== $certificat->getValideJusqua()
            && $certificat->getValideJusqua() < $now;

        return [
            'id' => $certificat->getId(),
            'numero' => $certificat->getNumero(),
            'annee' => $certificat->getAnnee(),
            'statut' => $certificat->getStatut(),
            'nom' => $certificat->getNom(),
            'postNom' => $certificat->getPostNom(),
            'prenom' => $certificat->getPrenom(),
            'fullName' => $certificat->getFullName(),
            'sexe' => $certificat->getSexe(),
            'motif' => $certificat->getMotif(),
            'motifLabel' => $this->motifLabel($certificat),
            'verdict' => $certificat->getVerdict(),
            'verdictPropose' => $certificat->getVerdictPropose(),
            'service' => $this->serializeService($certificat->getService()),
            'patientId' => $certificat->getPatient()?->getId()?->toRfc4122(),
            'signeAt' => $certificat->getSigneAt()?->format(\DateTimeInterface::ATOM),
            'valideJusqua' => $certificat->getValideJusqua()?->format(\DateTimeInterface::ATOM),
            'expired' => $expired,
            'createdAt' => $certificat->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(CertificatAptitude $certificat): array
    {
        $signePar = $certificat->getSignePar();

        return [
            ...$this->serializeSummary($certificat),
            'etatCivil' => $certificat->getEtatCivil(),
            'dateNaissance' => $certificat->getDateNaissance()?->format('Y-m-d'),
            'lieuNaissance' => $certificat->getLieuNaissance(),
            'adresse' => $certificat->getAdresse(),
            'motifAutre' => $certificat->getMotifAutre(),
            'poidsKg' => $this->asFloat($certificat->getPoidsKg()),
            'tailleM' => $this->asFloat($certificat->getTailleM()),
            'perimetreThoraciqueCm' => $this->asFloat($certificat->getPerimetreThoraciqueCm()),
            'p1' => $certificat->getP1(),
            'p2' => $certificat->getP2(),
            'p3' => $certificat->getP3(),
            'imc' => $this->asFloat($certificat->getImc()),
            'imcClasse' => $certificat->getImcClasse(),
            'imcClasseProposee' => AptitudeCalculator::imcClasse($this->asFloat($certificat->getImc())),
            'pignet' => $this->asFloat($certificat->getPignet()),
            'pignetRobustesse' => $certificat->getPignetRobustesse(),
            'ruffier' => $this->asFloat($certificat->getRuffier()),
            'dickson' => $this->asFloat($certificat->getDickson()),
            'ruffierClasse' => $certificat->getRuffierClasse(),
            'dicksonClasse' => $certificat->getDicksonClasse(),
            'signePar' => null !== $signePar ? [
                'id' => (string) $signePar->getId(),
                'fullName' => trim(sprintf(
                    '%s %s %s',
                    $signePar->getPrenom() ?? '',
                    $signePar->getNom() ?? '',
                    $signePar->getPostNom() ?? '',
                )),
            ] : null,
            'annuleAt' => $certificat->getAnnuleAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $certificat->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function hydrate(CertificatAptitude $certificat, UpsertAptitudeInput $input): void
    {
        $service = $this->serviceRepository->find($input->serviceId);
        if (!$service instanceof Service) {
            throw new NotFoundException('Service non trouvé.');
        }

        if (CertificatAptitude::MOTIF_AUTRE === $input->motif && (null === $input->motifAutre || '' === trim($input->motifAutre))) {
            throw new ConflictException('Précisez le motif lorsque « Autre » est sélectionné.');
        }

        $patient = null;
        if (null !== $input->patientId) {
            $patient = $this->patientService->getById($input->patientId);
        }

        $poids = $this->toFloat($input->poidsKg);
        $taille = AptitudeCalculator::tailleMetres($this->toFloat($input->tailleM));
        $perimetre = $this->toFloat($input->perimetreThoraciqueCm);
        $p1 = $this->toInt($input->p1);
        $p2 = $this->toInt($input->p2);
        $p3 = $this->toInt($input->p3);
        $computed = AptitudeCalculator::compute($poids, $taille, $perimetre, $p1, $p2, $p3);

        $verdict = $this->normalizeVerdict($input->verdict);
        if (null === $verdict) {
            $verdict = $computed['verdictPropose'];
        }

        $imcClasse = $this->normalizeImcClasse($input->imcClasse);
        if (null === $imcClasse) {
            $imcClasse = $computed['imcClasse'];
        }

        $certificat
            ->setService($service)
            ->setPatient($patient)
            ->setNom(mb_strtoupper(trim($input->nom)))
            ->setPostNom(mb_strtoupper(trim($input->postNom)))
            ->setPrenom($this->blankToNull($input->prenom))
            ->setSexe(strtoupper(trim($input->sexe)))
            ->setEtatCivil($this->blankToNull($input->etatCivil))
            ->setDateNaissance($this->parseDate($input->dateNaissance))
            ->setLieuNaissance($this->blankToNull($input->lieuNaissance))
            ->setAdresse($this->blankToNull($input->adresse))
            ->setMotif($input->motif)
            ->setMotifAutre(CertificatAptitude::MOTIF_AUTRE === $input->motif ? $this->blankToNull($input->motifAutre) : null)
            ->setPoidsKg($this->toDecimal($poids))
            ->setTailleM($this->toDecimal($taille))
            ->setPerimetreThoraciqueCm($this->toDecimal($perimetre))
            ->setP1($p1)
            ->setP2($p2)
            ->setP3($p3)
            ->setImc($this->toDecimal($computed['imc']))
            ->setImcClasse($imcClasse)
            ->setPignet($this->toDecimal($computed['pignet']))
            ->setPignetRobustesse($computed['pignetRobustesse'])
            ->setRuffier($this->toDecimal($computed['ruffier']))
            ->setDickson($this->toDecimal($computed['dickson']))
            ->setRuffierClasse($computed['ruffierClasse'])
            ->setDicksonClasse($computed['dicksonClasse'])
            ->setVerdictPropose($computed['verdictPropose'])
            ->setVerdict($verdict);
    }

    private function assertSignable(CertificatAptitude $certificat): void
    {
        $missing = [];
        if ('' === trim($certificat->getNom()) || '' === trim($certificat->getPostNom())) {
            $missing[] = 'identité';
        }
        if (null === $certificat->getDateNaissance() || null === $this->blankToNull($certificat->getLieuNaissance())) {
            $missing[] = 'naissance';
        }
        if (null === $this->blankToNull($certificat->getAdresse())) {
            $missing[] = 'adresse';
        }
        if (null === $certificat->getPoidsKg() || null === $certificat->getTailleM() || null === $certificat->getPerimetreThoraciqueCm()) {
            $missing[] = 'mesures anthropométriques';
        }
        if (null === $certificat->getP1() || null === $certificat->getP2() || null === $certificat->getP3()) {
            $missing[] = 'fréquences cardiaques';
        }
        if (null === $certificat->getVerdict()) {
            $missing[] = 'verdict';
        }

        if ([] !== $missing) {
            throw new ConflictException('Complétez le certificat avant signature : ' . implode(', ', $missing) . '.');
        }
    }

    private function assertBrouillon(CertificatAptitude $certificat): void
    {
        if (!$certificat->isBrouillon()) {
            throw new ConflictException('Seul un brouillon peut être modifié ou supprimé.');
        }
    }

    private function motifLabel(CertificatAptitude $certificat): string
    {
        return match ($certificat->getMotif()) {
            CertificatAptitude::MOTIF_ADMISSION_UKV => 'Admission Universitaire UKV',
            CertificatAptitude::MOTIF_EMPLOI => 'Emploi',
            CertificatAptitude::MOTIF_AUTRE => $certificat->getMotifAutre() ?: 'Autre',
            default => $certificat->getMotif(),
        };
    }

    /** @return array{id: int, code: string, libelle: string}|null */
    private function serializeService(?Service $service): ?array
    {
        if (null === $service) {
            return null;
        }

        return [
            'id' => $service->getId(),
            'code' => (string) $service->getCode(),
            'libelle' => (string) $service->getLibelle(),
        ];
    }

    private function currentPersonnel(): ?Personnel
    {
        $user = $this->security->getUser();

        return $user instanceof Personnel ? $user : null;
    }

    private function currentYear(): int
    {
        return (int) (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y');
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (false === $date) {
            throw new ConflictException('Date de naissance invalide.');
        }

        return $date->setTime(0, 0);
    }

    private function normalizeVerdict(?string $verdict): ?string
    {
        if (null === $verdict || '' === trim($verdict)) {
            return null;
        }

        $normalized = strtoupper(trim($verdict));
        if (!in_array($normalized, CertificatAptitude::getVerdicts(), true)) {
            throw new ConflictException('Verdict invalide.');
        }

        return $normalized;
    }

    private function normalizeImcClasse(?string $classe): ?string
    {
        if (null === $classe || '' === trim($classe)) {
            return null;
        }

        $normalized = strtoupper(trim($classe));
        if (!in_array($normalized, CertificatAptitude::getImcClasses(), true)) {
            throw new ConflictException('Interprétation OMS invalide.');
        }

        return $normalized;
    }

    private function toFloat(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ConflictException('Valeur numérique invalide.');
        }

        return (float) $value;
    }

    private function toInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ConflictException('Valeur numérique invalide.');
        }

        return (int) $value;
    }

    private function toDecimal(?float $value): ?string
    {
        return null === $value ? null : number_format($value, 2, '.', '');
    }

    private function asFloat(?string $value): ?float
    {
        return null === $value || '' === $value ? null : (float) $value;
    }

    private function blankToNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function assertValid(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }
    }
}
