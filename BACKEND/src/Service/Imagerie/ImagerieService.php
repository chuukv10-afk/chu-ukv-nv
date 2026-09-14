<?php

namespace App\Service\Imagerie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Imagerie\ConfirmImagerieImageInput;
use App\DTO\Imagerie\CreateEtudeImagerieInput;
use App\DTO\Imagerie\ImagerieListQuery;
use App\DTO\Imagerie\InterpretEtudeImagerieInput;
use App\DTO\Storage\PrepareStoredFileInput;
use App\Entity\DemandeExamen;
use App\Entity\EtudeImagerie;
use App\Entity\Examen;
use App\Entity\ImageImagerie;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DemandeExamenRepository;
use App\Repository\EtudeImagerieRepository;
use App\Repository\ExamenRepository;
use App\Repository\PatientRepository;
use App\Security\Permission\CliniquePermissions;
use App\Service\Storage\ObjectStorage;
use App\Service\Storage\StoredFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ImagerieService
{
    public const MAX_SIZE_BYTES = 52_428_800;
    public const MAX_IMAGES = 30;
    private const TIMEZONE = 'Africa/Kinshasa';
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EtudeImagerieRepository $etudeRepository,
        private readonly PatientRepository $patientRepository,
        private readonly ExamenRepository $examenRepository,
        private readonly DemandeExamenRepository $demandeExamenRepository,
        private readonly ObjectStorage $objectStorage,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ImagerieListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->etudeRepository->paginate($query->page, $query->limit, $query->search, $query->statut, $query->patientId, $query->statuts);

        return new PaginatedResult(
            array_map(fn (EtudeImagerie $etude): array => $this->serializeSummary($etude), $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function getById(int $id): EtudeImagerie
    {
        $etude = $this->etudeRepository->find($id);
        if (!$etude instanceof EtudeImagerie) {
            throw new NotFoundException('Étude d\'imagerie non trouvée.');
        }

        return $etude;
    }

    public function create(CreateEtudeImagerieInput $input): EtudeImagerie
    {
        $this->assertValid($input);
        $demande = null;
        if (null !== $input->demandeExamenId && $input->demandeExamenId > 0) {
            $demande = $this->demandeExamenRepository->find($input->demandeExamenId);
            if (!$demande instanceof DemandeExamen) {
                throw new NotFoundException('Demande d\'examen non trouvée.');
            }
            if (null !== $this->etudeRepository->findOneBy(['demandeExamen' => $demande])) {
                throw new ConflictException('Cette demande a déjà une étude d\'imagerie.');
            }
            $examen = $demande->getExamen();
            $patient = $demande->getConsultation()?->getVisite()?->getDpi()?->getPatient();
            if (null === $examen || null === $patient) {
                throw new ConflictException('La demande d\'examen n\'est pas rattachée à un patient.');
            }
            if (!$examen->getTypeExamen()?->isImagerie()) {
                throw new ConflictException('Cet examen n\'est pas un examen d\'imagerie.');
            }
            if (DemandeExamen::STATUT_DEMANDE === $demande->getStatut()) {
                $demande->setStatut(DemandeExamen::STATUT_EN_COURS);
            }
            $consultation = $demande->getConsultation();
            $indication = $input->indication ?: $demande->getNoteMedecin();
        } else {
            $patientId = trim((string) $input->patientId);
            if ('' === $patientId || null === $input->examenId) {
                throw new BadRequestHttpException('Indiquez le patient et l\'examen, ou une demande d\'examen.');
            }
            try {
                $patient = $this->patientRepository->find(Uuid::fromString($patientId));
            } catch (\InvalidArgumentException) {
                throw new NotFoundException('Patient ou examen introuvable.');
            }
            $examen = $this->examenRepository->find($input->examenId);
            if (null === $patient || !$examen instanceof Examen) {
                throw new NotFoundException('Patient ou examen introuvable.');
            }
            if (!$examen->getTypeExamen()?->isImagerie()) {
                throw new ConflictException('Sélectionnez un examen de type imagerie.');
            }
            $consultation = null;
            $indication = $input->indication;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $etude = (new EtudeImagerie())
            ->setNumero($this->nextNumero($now))
            ->setStatut(EtudeImagerie::STATUT_EN_ATTENTE)
            ->setPatient($patient)
            ->setExamen($examen)
            ->setConsultation($consultation)
            ->setDemandeExamen($demande)
            ->setIndication($indication)
            ->setCreatedAt($now)
            ->setCreatedBy($this->currentPersonnel());

        $this->entityManager->persist($etude);
        $this->entityManager->flush();

        return $etude;
    }

    public function interpret(int $id, InterpretEtudeImagerieInput $input): EtudeImagerie
    {
        $this->assertValid($input);
        $etude = $this->requireEditable($id);
        if ($etude->getImages()->isEmpty()) {
            throw new ConflictException('Chargez au moins une image avant d\'interpréter.');
        }

        $etude
            ->setTechnique('' === trim((string) $input->technique) ? null : trim((string) $input->technique))
            ->setConstatations(trim($input->constatations))
            ->setConclusion(trim($input->conclusion))
            ->setStatut(EtudeImagerie::STATUT_INTERPRETE)
            ->setInterpretePar($this->currentPersonnel())
            ->setInterpreteAt(new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)));

        $demande = $etude->getDemandeExamen();
        if ($demande instanceof DemandeExamen && DemandeExamen::STATUT_VALIDE !== $demande->getStatut()) {
            $demande->setResultat('Compte-rendu disponible dans Imagerie (accès restreint).');
            if (in_array($demande->getStatut(), [DemandeExamen::STATUT_DEMANDE, DemandeExamen::STATUT_EN_COURS], true)) {
                $demande->setStatut(DemandeExamen::STATUT_RESULTAT_DISPONIBLE);
            }
        }

        $this->entityManager->flush();

        return $etude;
    }

    public function validate(int $id): EtudeImagerie
    {
        $etude = $this->getById($id);
        if (EtudeImagerie::STATUT_INTERPRETE !== $etude->getStatut()) {
            throw new ConflictException('Seule une étude interprétée peut être validée.');
        }

        $etude
            ->setStatut(EtudeImagerie::STATUT_VALIDE)
            ->setValidePar($this->currentPersonnel())
            ->setValideAt(new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)));

        $demande = $etude->getDemandeExamen();
        if ($demande instanceof DemandeExamen && DemandeExamen::canTransition((string) $demande->getStatut(), DemandeExamen::STATUT_VALIDE)) {
            $demande->setStatut(DemandeExamen::STATUT_VALIDE);
        }

        $this->entityManager->flush();

        return $etude;
    }

    public function cancel(int $id): EtudeImagerie
    {
        $etude = $this->getById($id);
        if (EtudeImagerie::STATUT_VALIDE === $etude->getStatut()) {
            throw new ConflictException('Une étude validée ne peut plus être annulée.');
        }
        $etude->setStatut(EtudeImagerie::STATUT_ANNULEE);
        $this->entityManager->flush();

        return $etude;
    }

    public function delete(int $id): void
    {
        $etude = $this->getById($id);
        if (EtudeImagerie::STATUT_VALIDE === $etude->getStatut()) {
            throw new ConflictException('Impossible de supprimer une étude validée.');
        }
        foreach ($etude->getImages() as $image) {
            $this->objectStorage->delete((string) $image->getStorageKey());
        }
        $this->entityManager->remove($etude);
        $this->entityManager->flush();
    }

    /**
     * @return array{mode: string, filename: string, mimeType: string, uploadUrl?: string}
     */
    public function prepareImage(int $id, PrepareStoredFileInput $input): array
    {
        $this->assertValid($input);
        $etude = $this->requireEditable($id);
        if ($etude->getImages()->count() >= self::MAX_IMAGES) {
            throw new ConflictException('Maximum ' . self::MAX_IMAGES . ' images par étude.');
        }
        $extension = $this->assertMimeAndSize($input->mimeType, $input->size);
        $filename = sprintf('%d-%s.%s', (int) $etude->getId(), Uuid::v7()->toRfc4122(), $extension);
        $payload = [
            'mode' => 'local',
            'filename' => $filename,
            'mimeType' => $input->mimeType,
        ];
        if ($this->objectStorage->isS3()) {
            $payload['mode'] = 's3';
            $payload['uploadUrl'] = $this->objectStorage->presignPut($this->storageKey($filename), $input->mimeType);
        }

        return $payload;
    }

    public function confirmImage(int $id, ConfirmImagerieImageInput $input): EtudeImagerie
    {
        $this->assertValid($input);
        $etude = $this->requireEditable($id);
        $this->assertOwnedFilename($etude, $input->filename);
        $this->assertMimeAndSize($input->mimeType, $input->size);
        $key = $this->storageKey($input->filename);
        if (!$this->objectStorage->exists($key)) {
            throw new BadRequestHttpException('Le fichier n\'a pas été reçu dans le stockage.');
        }

        $image = (new ImageImagerie())
            ->setStorageKey($key)
            ->setOriginalName($input->originalName)
            ->setMimeType($input->mimeType)
            ->setSizeBytes($input->size)
            ->setUploadedAt(new \DateTimeImmutable())
            ->setUploadedBy($this->currentPersonnel());
        $etude->addImage($image);
        $this->entityManager->persist($image);
        if (EtudeImagerie::STATUT_EN_ATTENTE === $etude->getStatut()) {
            $etude->setStatut(EtudeImagerie::STATUT_IMAGES);
        }
        $this->entityManager->flush();

        return $etude;
    }

    public function uploadLocal(int $id, string $mimeType, int $size, string $originalName, string $contents): EtudeImagerie
    {
        $etude = $this->requireEditable($id);
        $extension = $this->assertMimeAndSize($mimeType, $size);
        if ('' === $contents) {
            throw new BadRequestHttpException('Fichier vide.');
        }
        $filename = sprintf('%d-%s.%s', (int) $etude->getId(), Uuid::v7()->toRfc4122(), $extension);
        $this->objectStorage->put($this->storageKey($filename), $contents, $mimeType);

        return $this->confirmImage($id, new ConfirmImagerieImageInput($filename, $originalName, $mimeType, $size));
    }

    public function deleteImage(int $etudeId, int $imageId): EtudeImagerie
    {
        $etude = $this->requireEditable($etudeId);
        $image = $this->requireImage($etude, $imageId);
        $this->objectStorage->delete((string) $image->getStorageKey());
        $etude->removeImage($image);
        $this->entityManager->remove($image);
        if ($etude->getImages()->isEmpty() && EtudeImagerie::STATUT_IMAGES === $etude->getStatut()) {
            $etude->setStatut(EtudeImagerie::STATUT_EN_ATTENTE);
        }
        $this->entityManager->flush();

        return $etude;
    }

    public function readImage(int $etudeId, int $imageId): StoredFile
    {
        $etude = $this->getById($etudeId);
        $image = $this->requireImage($etude, $imageId);
        $file = $this->objectStorage->get((string) $image->getStorageKey());
        if (null === $file) {
            throw new NotFoundException('Image introuvable.');
        }

        return $file;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(EtudeImagerie $etude): array
    {
        $patient = $etude->getPatient();
        $examen = $etude->getExamen();

        return [
            'id' => $etude->getId(),
            'numero' => $etude->getNumero(),
            'statut' => $etude->getStatut(),
            'indication' => $etude->getIndication(),
            'imagesCount' => $etude->getImages()->count(),
            'createdAt' => $etude->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'interpreteAt' => $etude->getInterpreteAt()?->format(\DateTimeInterface::ATOM),
            'patient' => $patient ? [
                'id' => $patient->getId()?->toRfc4122(),
                'nom' => $patient->getNom(),
                'postNom' => $patient->getPostNom(),
                'prenom' => $patient->getPrenom(),
                'fullName' => $patient->getFullName(),
            ] : null,
            'examen' => $examen ? [
                'id' => $examen->getId(),
                'code' => $examen->getCode(),
                'libelle' => $examen->getLibelle(),
                'typeExamen' => $examen->getTypeExamen() ? [
                    'id' => $examen->getTypeExamen()->getId(),
                    'code' => $examen->getTypeExamen()->getCode(),
                    'libelle' => $examen->getTypeExamen()->getLibelle(),
                    'imagerie' => $examen->getTypeExamen()->isImagerie(),
                ] : null,
            ] : null,
            'demandeExamenId' => $etude->getDemandeExamen()?->getId(),
            'consultationId' => $etude->getConsultation()?->getId(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(EtudeImagerie $etude): array
    {
        $data = $this->serializeSummary($etude);
        $canSeeInterpretation = $this->canSeeInterpretation();
        $data['canSeeInterpretation'] = $canSeeInterpretation;
        $data['technique'] = $canSeeInterpretation ? $etude->getTechnique() : null;
        $data['constatations'] = $canSeeInterpretation ? $etude->getConstatations() : null;
        $data['conclusion'] = $canSeeInterpretation ? $etude->getConclusion() : null;
        $data['interpretePar'] = $canSeeInterpretation ? $this->serializePersonnel($etude->getInterpretePar()) : null;
        $data['validePar'] = $canSeeInterpretation ? $this->serializePersonnel($etude->getValidePar()) : null;
        $data['valideAt'] = $canSeeInterpretation ? $etude->getValideAt()?->format(\DateTimeInterface::ATOM) : null;
        $data['interpreteAt'] = $canSeeInterpretation ? $etude->getInterpreteAt()?->format(\DateTimeInterface::ATOM) : null;
        $data['images'] = [];
        foreach ($etude->getImages() as $image) {
            $apiUrl = sprintf(
                '/api/v1/imagerie/etudes/%d/images/%d?v=%d',
                (int) $etude->getId(),
                (int) $image->getId(),
                $image->getUploadedAt()?->getTimestamp() ?? time(),
            );
            $viewUrl = null;
            if ($this->objectStorage->isS3()) {
                try {
                    $viewUrl = $this->objectStorage->presignGet((string) $image->getStorageKey());
                } catch (\Throwable) {
                    $viewUrl = null;
                }
            }
            $data['images'][] = [
                'id' => $image->getId(),
                'originalName' => $image->getOriginalName(),
                'mimeType' => $image->getMimeType(),
                'sizeBytes' => $image->getSizeBytes(),
                'uploadedAt' => $image->getUploadedAt()?->format(\DateTimeInterface::ATOM),
                'url' => $apiUrl,
                'viewUrl' => $viewUrl,
            ];
        }

        return $data;
    }

    private function requireEditable(int $id): EtudeImagerie
    {
        $etude = $this->getById($id);
        if (in_array($etude->getStatut(), [EtudeImagerie::STATUT_VALIDE, EtudeImagerie::STATUT_ANNULEE], true)) {
            throw new ConflictException('Cette étude n\'est plus modifiable.');
        }

        return $etude;
    }

    private function requireImage(EtudeImagerie $etude, int $imageId): ImageImagerie
    {
        foreach ($etude->getImages() as $image) {
            if ($image->getId() === $imageId) {
                return $image;
            }
        }

        throw new NotFoundException('Image non trouvée.');
    }

    private function nextNumero(\DateTimeImmutable $date): string
    {
        return $this->etudeRepository->nextNumeroForPrefix('IMG-' . $date->format('Ymd') . '-');
    }

    private function storageKey(string $filename): string
    {
        return 'imagerie/etudes/' . $filename;
    }

    private function assertOwnedFilename(EtudeImagerie $etude, string $filename): void
    {
        $prefix = (int) $etude->getId() . '-';
        if (!str_starts_with($filename, $prefix)) {
            throw new BadRequestHttpException('Nom de fichier invalide.');
        }
    }

    private function assertMimeAndSize(string $mimeType, int $size): string
    {
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if (null === $extension) {
            throw new BadRequestHttpException('Format non supporté. Utilisez JPG, PNG, WebP ou PDF.');
        }
        if ($size > self::MAX_SIZE_BYTES) {
            throw new BadRequestHttpException('Le fichier ne doit pas dépasser 50 Mo.');
        }

        return $extension;
    }

    /** @return array{id: string, nom: string}|null */
    private function serializePersonnel(?Personnel $personnel): ?array
    {
        if (!$personnel instanceof Personnel) {
            return null;
        }

        return [
            'id' => $personnel->getId()?->toRfc4122(),
            'nom' => trim(implode(' ', array_filter([
                $personnel->getNom(),
                $personnel->getPostNom(),
                $personnel->getPrenom(),
            ]))),
        ];
    }

    private function canSeeInterpretation(): bool
    {
        return $this->security->isGranted(CliniquePermissions::IMAGERIE_INTERPRET)
            || $this->security->isGranted(CliniquePermissions::IMAGERIE_VALIDATE)
            || $this->security->isGranted(CliniquePermissions::IMAGERIE_EXPORT);
    }

    private function currentPersonnel(): ?Personnel
    {
        $user = $this->security->getUser();

        return $user instanceof Personnel ? $user : null;
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
