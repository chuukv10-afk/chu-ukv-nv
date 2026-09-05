<?php

namespace App\Service\Personnel;

use App\DTO\Admin\CreatePersonnelInput;
use App\DTO\Admin\PersonnelListQuery;
use App\DTO\Admin\PersonnelRoleAssignmentInput;
use App\DTO\Admin\UpdatePersonnelInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\Departement;
use App\Entity\Grade;
use App\Entity\Personnel;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Entity\Service;
use App\Entity\Specialite;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DepartementRepository;
use App\Repository\GradeRepository;
use App\Repository\PersonnelRepository;
use App\Repository\RoleRepository;
use App\Repository\ServiceRepository;
use App\Repository\SpecialiteRepository;
use App\Security\Permission\AdminPermissions;
use App\Security\PermissionChecker;
use App\Service\Role\RoleProvisioner;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PersonnelService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly PersonnelRepository $personnelRepository,
        private readonly GradeRepository $gradeRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly SpecialiteRepository $specialiteRepository,
        private readonly RoleRepository $roleRepository,
        private readonly DepartementRepository $departementRepository,
        private readonly RoleProvisioner $roleProvisioner,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly PermissionChecker $permissionChecker,
        private readonly Security $security,
        private readonly PersonnelAvatarService $avatarService,
        private readonly PersonnelExportService $exportService,
    ) {
    }

    public function paginate(PersonnelListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $accessScope = null;
        $viewer = $this->security->getUser();
        if ($viewer instanceof Personnel) {
            $accessScope = $this->permissionChecker->resolveAccessScope($viewer, AdminPermissions::PERSONNEL_READ);
        }

        $result = $this->personnelRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->status,
            $query->type,
            $query->serviceId,
            $query->sexe,
            $accessScope,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function buildExportRows(PersonnelListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $accessScope = null;
        $viewer = $this->security->getUser();
        if ($viewer instanceof Personnel) {
            $accessScope = $this->permissionChecker->resolveAccessScope($viewer, AdminPermissions::PERSONNEL_READ);
        }

        $items = $this->personnelRepository->findForExport(
            $query->search,
            $query->status,
            $query->type,
            $query->serviceId,
            $query->sexe,
            $accessScope,
        );

        return array_map(
            fn (Personnel $personnel): array => $this->exportService->buildRow($personnel),
            $items,
        );
    }

    public function getById(string $id): Personnel
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundException('Personnel non trouvé.');
        }

        $personnel = $this->personnelRepository->find(Uuid::fromString($id));
        if (null === $personnel || Personnel::STATUS_SUPPRIME === $personnel->getStatus()) {
            throw new NotFoundException('Personnel non trouvé.');
        }

        return $personnel;
    }

    public function create(CreatePersonnelInput $input): Personnel
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $this->assertUniqueTelephone($input->telephone);
        $this->assertUniqueMatricule($input->matricule);

        $personnel = (new Personnel())
            ->setNom(trim($input->nom))
            ->setPostNom($this->normalizeOptionalText($input->postNom) ?? '')
            ->setPrenom($this->normalizeOptionalText($input->prenom))
            ->setTelephone(trim($input->telephone))
            ->setMatricule(trim($input->matricule))
            ->setSexe(strtoupper(trim($input->sexe)))
            ->setType(Personnel::normalizeType($input->type))
            ->setStatus(Personnel::normalizeStatus($input->status))
            ->setAdresse($this->normalizeOptionalText($input->adresse))
            ->setLieuNaissance($this->normalizeOptionalText($input->lieuNaissance))
            ->setCnome($this->normalizeOptionalText($input->cnome))
            ->setCreatedAt(new \DateTimeImmutable());

        $personnel->setPassword($this->passwordHasher->hashPassword($personnel, $input->password));
        $personnel->setGrade($this->resolveGrade($input->gradeId));
        $service = $this->resolveService($input->serviceId);
        $this->assertViewerCanAssignService($service, AdminPermissions::PERSONNEL_CREATE);
        $personnel->setService($service);
        $this->syncSpecialites($personnel, $input->specialiteIds);

        $this->eM->persist($personnel);

        if ([] !== $input->roleAssignments) {
            $this->syncRoleAssignments($personnel, $input->roleAssignments);
        }

        $this->roleProvisioner->assignDefaultPersonnelRole($personnel);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictException($this->resolveUniqueConstraintMessage($exception));
        }

        return $personnel;
    }

    public function update(string $id, UpdatePersonnelInput $input): Personnel
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $personnel = $this->getById($id);
        $this->assertViewerCanAccessPersonnel($personnel, AdminPermissions::PERSONNEL_UPDATE);
        $personnelId = $personnel->getId();
        if (null === $personnelId) {
            throw new NotFoundException('Personnel non trouvé.');
        }

        $normalizedTelephone = trim($input->telephone);
        $normalizedMatricule = trim($input->matricule);

        if (null !== $this->personnelRepository->findOneByTelephoneForAnotherPersonnel($normalizedTelephone, $personnelId)) {
            throw new ConflictException('Ce numéro de téléphone est déjà utilisé.');
        }

        if (null !== $this->personnelRepository->findOneByMatriculeForAnotherPersonnel($normalizedMatricule, $personnelId)) {
            throw new ConflictException('Ce matricule est déjà utilisé.');
        }

        $personnel
            ->setNom(trim($input->nom))
            ->setPostNom($this->normalizeOptionalText($input->postNom) ?? '')
            ->setPrenom($this->normalizeOptionalText($input->prenom))
            ->setTelephone($normalizedTelephone)
            ->setMatricule($normalizedMatricule)
            ->setSexe(strtoupper(trim($input->sexe)))
            ->setType(Personnel::normalizeType($input->type))
            ->setStatus(Personnel::normalizeStatus($input->status))
            ->setAdresse($this->normalizeOptionalText($input->adresse))
            ->setLieuNaissance($this->normalizeOptionalText($input->lieuNaissance))
            ->setCnome($this->normalizeOptionalText($input->cnome))
            ->setGrade($this->resolveGrade($input->gradeId));

        $service = $this->resolveService($input->serviceId);
        $this->assertViewerCanAssignService($service, AdminPermissions::PERSONNEL_UPDATE);
        $personnel->setService($service);

        if (null !== $input->password && '' !== trim($input->password)) {
            $personnel->setPassword($this->passwordHasher->hashPassword($personnel, $input->password));
        }

        if (null !== $input->specialiteIds) {
            $this->syncSpecialites($personnel, $input->specialiteIds);
        }

        if (null !== $input->roleAssignments) {
            $this->syncRoleAssignments($personnel, $input->roleAssignments);
        }

        $this->roleProvisioner->assignDefaultPersonnelRole($personnel);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictException($this->resolveUniqueConstraintMessage($exception));
        }

        return $personnel;
    }

    public function delete(string $id): void
    {
        $personnel = $this->getById($id);
        $this->assertViewerCanAccessPersonnel($personnel, AdminPermissions::PERSONNEL_DELETE);
        $this->avatarService->delete($personnel);
        $personnel->setStatus(Personnel::STATUS_SUPPRIME);
        $this->eM->flush();
    }

    public function uploadAvatar(string $id, UploadedFile $file): Personnel
    {
        $personnel = $this->getById($id);
        $this->assertViewerCanAccessPersonnel($personnel, AdminPermissions::PERSONNEL_UPDATE);
        $this->avatarService->upload($personnel, $file);

        try {
            $this->eM->flush();
        } catch (\Throwable) {
            $this->avatarService->delete($personnel);
            throw new ConflictException('Impossible d\'enregistrer l\'avatar.');
        }

        return $personnel;
    }

    public function deleteAvatar(string $id): Personnel
    {
        $personnel = $this->getById($id);
        $this->assertViewerCanAccessPersonnel($personnel, AdminPermissions::PERSONNEL_UPDATE);
        $this->avatarService->delete($personnel);
        $this->eM->flush();

        return $personnel;
    }

    public function resolveAvatarPath(string $id): ?string
    {
        $personnel = $this->getById($id);

        return $this->avatarService->resolvePath($personnel);
    }

    public function resolveAvatarMimeType(string $id): ?string
    {
        $personnel = $this->getById($id);

        return $this->avatarService->resolveMimeType($personnel);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Personnel $personnel): array
    {
        $specialites = [];
        foreach ($personnel->getSpecialites() as $specialite) {
            $specialites[] = [
                'id' => $specialite->getId(),
                'code' => $specialite->getCode(),
                'libelle' => $specialite->getLibelle(),
            ];
        }

        $roleAssignments = [];
        foreach ($personnel->getRoleAssignments() as $assignment) {
            $role = $assignment->getRole();
            $roleAssignments[] = [
                'id' => $assignment->getId()?->toRfc4122(),
                'roleId' => $role?->getId()?->toRfc4122(),
                'roleCode' => $role?->getCode(),
                'roleLibelle' => $role?->getLibelle(),
                'perimetre' => $assignment->getPerimetre(),
                'serviceId' => $assignment->getService()?->getId(),
                'serviceLibelle' => $assignment->getService()?->getLibelle(),
                'departementId' => $assignment->getDepartement()?->getId(),
                'departementLibelle' => $assignment->getDepartement()?->getLibelle(),
            ];
        }

        $grade = $personnel->getGrade();
        $service = $personnel->getService();

        return [
            'id' => $personnel->getId()?->toRfc4122(),
            'matricule' => $personnel->getMatricule(),
            'nom' => $personnel->getNom(),
            'postNom' => $personnel->getPostNom(),
            'prenom' => $personnel->getPrenom(),
            'telephone' => $personnel->getTelephone(),
            'sexe' => $personnel->getSexe(),
            'type' => $personnel->getType(),
            'status' => $personnel->getStatus(),
            'adresse' => $personnel->getAdresse(),
            'lieuNaissance' => $personnel->getLieuNaissance(),
            'cnome' => $personnel->getCnome(),
            'avatarUrl' => $this->avatarService->buildAvatarUrl($personnel),
            'grade' => null !== $grade ? [
                'id' => $grade->getId(),
                'code' => $grade->getCode(),
                'libelle' => $grade->getLibelle(),
            ] : null,
            'service' => null !== $service ? [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ] : null,
            'specialites' => $specialites,
            'specialiteIds' => array_map(static fn (array $item): int => (int) $item['id'], $specialites),
            'roleAssignments' => $roleAssignments,
            'rolesCount' => count($roleAssignments),
            'createdAt' => $personnel->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSummary(Personnel $personnel): array
    {
        $full = $this->serialize($personnel);

        return [
            'id' => $full['id'],
            'matricule' => $full['matricule'],
            'nom' => $full['nom'],
            'postNom' => $full['postNom'],
            'prenom' => $full['prenom'],
            'telephone' => $full['telephone'],
            'sexe' => $full['sexe'],
            'type' => $full['type'],
            'status' => $full['status'],
            'grade' => $full['grade'],
            'service' => $full['service'],
            'avatarUrl' => $full['avatarUrl'],
            'roleAssignments' => $full['roleAssignments'],
            'rolesCount' => $full['rolesCount'],
            'createdAt' => $full['createdAt'],
        ];
    }

    private function assertUniqueTelephone(string $telephone): void
    {
        if ($this->personnelRepository->existsByTelephone($telephone)) {
            throw new ConflictException('Ce numéro de téléphone est déjà utilisé.');
        }
    }

    private function assertUniqueMatricule(string $matricule): void
    {
        if ($this->personnelRepository->existsByMatricule($matricule)) {
            throw new ConflictException('Ce matricule est déjà utilisé.');
        }
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }

    private function resolveUniqueConstraintMessage(UniqueConstraintViolationException $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'telephone') || str_contains($message, 'uniq') && str_contains($message, 'phone')) {
            return 'Ce numéro de téléphone est déjà utilisé.';
        }

        if (str_contains($message, 'matricule')) {
            return 'Ce matricule est déjà utilisé.';
        }

        if (str_contains($message, 'personnel_role') || str_contains($message, 'uniq_personnel_role')) {
            return 'Ce rôle est déjà affecté à ce personnel.';
        }

        return 'Une contrainte d\'unicité a été violée. Vérifiez les informations saisies.';
    }

    private function resolveGrade(?int $gradeId): ?Grade
    {
        if (null === $gradeId) {
            return null;
        }

        $grade = $this->gradeRepository->find($gradeId);
        if (null === $grade) {
            throw new NotFoundException('Grade non trouvé.');
        }

        return $grade;
    }

    private function resolveService(?int $serviceId): ?Service
    {
        if (null === $serviceId) {
            return null;
        }

        $service = $this->serviceRepository->find($serviceId);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }

        return $service;
    }

    private function resolveDepartement(?int $departementId): ?Departement
    {
        if (null === $departementId) {
            return null;
        }

        $departement = $this->departementRepository->find($departementId);
        if (null === $departement) {
            throw new NotFoundException('Département non trouvé.');
        }

        return $departement;
    }

    private function resolveRole(string $roleId): Role
    {
        if (!Uuid::isValid($roleId)) {
            throw new NotFoundException('Rôle non trouvé.');
        }

        $role = $this->roleRepository->find(Uuid::fromString($roleId));
        if (null === $role) {
            throw new NotFoundException('Rôle non trouvé.');
        }

        return $role;
    }

    /**
     * @param list<int> $specialiteIds
     */
    private function syncSpecialites(Personnel $personnel, array $specialiteIds): void
    {
        $targetSpecialites = [];
        $seenIds = [];

        foreach ($specialiteIds as $specialiteId) {
            if (!is_int($specialiteId) && !is_numeric($specialiteId)) {
                throw new NotFoundException('Spécialité non trouvée.');
            }

            $id = (int) $specialiteId;
            if (isset($seenIds[$id])) {
                continue;
            }

            $specialite = $this->specialiteRepository->find($id);
            if (null === $specialite) {
                throw new NotFoundException('Spécialité non trouvée.');
            }

            $seenIds[$id] = true;
            $targetSpecialites[] = $specialite;
        }

        foreach ($personnel->getSpecialites()->toArray() as $current) {
            if (!in_array($current, $targetSpecialites, true)) {
                $personnel->removeSpecialite($current);
            }
        }

        foreach ($targetSpecialites as $specialite) {
            $personnel->addSpecialite($specialite);
        }
    }

    /**
     * @param list<PersonnelRoleAssignmentInput|array<string, mixed>> $assignments
     */
    private function syncRoleAssignments(Personnel $personnel, array $assignments): void
    {
        $parsedAssignments = $this->parseRoleAssignmentInputs($assignments);
        $targetByRoleId = [];

        foreach ($parsedAssignments as $assignmentInput) {
            $targetByRoleId[$this->normalizeRoleId($assignmentInput->roleId)] = $assignmentInput;
        }

        foreach ($personnel->getRoleAssignments()->toArray() as $assignment) {
            if (Role::CODE_PERSONNEL === $assignment->getRole()?->getCode()) {
                continue;
            }

            $currentRoleId = $assignment->getRole()?->getId()?->toRfc4122();
            if (null === $currentRoleId || !isset($targetByRoleId[$this->normalizeRoleId($currentRoleId)])) {
                $personnel->removeRoleAssignment($assignment);
                $this->eM->remove($assignment);
            }
        }

        foreach ($targetByRoleId as $assignmentInput) {
            $role = $this->resolveRole($assignmentInput->roleId);

            if (Role::CODE_PERSONNEL === $role->getCode()) {
                continue;
            }

            $existing = $this->findRoleAssignmentByRoleId($personnel, $assignmentInput->roleId);
            if (null !== $existing) {
                $this->updateRoleAssignmentScope($existing, $assignmentInput);

                continue;
            }

            $service = $this->resolveService($assignmentInput->serviceId);
            $departement = $this->resolveDepartement($assignmentInput->departementId);
            $personnel->assignRole($role, $service, $departement);
        }
    }

    /**
     * @param list<PersonnelRoleAssignmentInput|array<string, mixed>> $assignments
     *
     * @return list<PersonnelRoleAssignmentInput>
     */
    private function parseRoleAssignmentInputs(array $assignments): array
    {
        $parsedAssignments = [];
        $seenRoleIds = [];

        foreach ($assignments as $assignmentInput) {
            if (!$assignmentInput instanceof PersonnelRoleAssignmentInput) {
                $assignmentInput = new PersonnelRoleAssignmentInput(
                    roleId: (string) ($assignmentInput['roleId'] ?? ''),
                    serviceId: isset($assignmentInput['serviceId']) ? (int) $assignmentInput['serviceId'] : null,
                    departementId: isset($assignmentInput['departementId']) ? (int) $assignmentInput['departementId'] : null,
                );
            }

            $errors = $this->validator->validate($assignmentInput);
            if (count($errors) > 0) {
                throw new ValidationFailedException($assignmentInput, $errors);
            }

            $normalizedRoleId = $this->normalizeRoleId($assignmentInput->roleId);
            if (isset($seenRoleIds[$normalizedRoleId])) {
                throw new ConflictException('Un rôle ne peut être affecté qu\'une seule fois.');
            }

            $seenRoleIds[$normalizedRoleId] = true;
            $parsedAssignments[] = $assignmentInput;
        }

        return $parsedAssignments;
    }

    private function normalizeRoleId(string $roleId): string
    {
        return strtolower(trim($roleId));
    }

    private function findRoleAssignmentByRoleId(Personnel $personnel, string $roleId): ?PersonnelRole
    {
        $normalizedRoleId = $this->normalizeRoleId($roleId);

        foreach ($personnel->getRoleAssignments() as $assignment) {
            $currentRoleId = $assignment->getRole()?->getId()?->toRfc4122();
            if (null !== $currentRoleId && $this->normalizeRoleId($currentRoleId) === $normalizedRoleId) {
                return $assignment;
            }
        }

        return null;
    }

    private function updateRoleAssignmentScope(PersonnelRole $assignment, PersonnelRoleAssignmentInput $input): void
    {
        match ($assignment->getPerimetre()) {
            PersonnelRole::PERIMETRE_SERVICE => $assignment
                ->setService($this->resolveService($input->serviceId))
                ->setDepartement(null),
            PersonnelRole::PERIMETRE_DEPARTEMENT => $assignment
                ->setDepartement($this->resolveDepartement($input->departementId))
                ->setService(null),
            default => $assignment
                ->setService(null)
                ->setDepartement(null),
        };

        $assignment->validateScope();
    }

    private function assertViewerCanAccessPersonnel(Personnel $target, string $permission): void
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof Personnel) {
            return;
        }

        if (!$this->permissionChecker->isGranted($viewer, $permission, $target)) {
            throw new AccessDeniedException('Accès refusé : vous n\'avez pas accès à ce personnel dans votre périmètre.');
        }
    }

    private function assertViewerCanAssignService(?Service $service, string $permission): void
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof Personnel) {
            return;
        }

        if (null !== $service && $this->permissionChecker->isGranted($viewer, $permission, $service)) {
            return;
        }

        if (null === $service) {
            $scope = $this->permissionChecker->resolveAccessScope($viewer, $permission);
            if (!$scope->isRestricted()) {
                return;
            }
        }

        throw new AccessDeniedException('Accès refusé : ce service est hors de votre périmètre.');
    }
}
