<?php

namespace App\Service\Personnel;

use App\DTO\Me\ChangeMePasswordInput;
use App\DTO\Me\UpdateMeProfileInput;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MeProfileService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly PersonnelAvatarService $avatarService,
        private readonly PersonnelSignatureService $signatureService,
    ) {
    }

    /** @return array<string, mixed> */
    public function serialize(Personnel $personnel): array
    {
        $service = $personnel->getService();
        $fonction = $personnel->getFonction();
        $grade = $personnel->getGrade();

        return [
            'id' => $personnel->getId()?->toRfc4122(),
            'matricule' => $personnel->getMatricule(),
            'nom' => $personnel->getNom(),
            'postNom' => $personnel->getPostNom(),
            'prenom' => $personnel->getPrenom(),
            'sexe' => $personnel->getSexe(),
            'telephone' => $personnel->getTelephone(),
            'adresse' => $personnel->getAdresse(),
            'lieuNaissance' => $personnel->getLieuNaissance(),
            'type' => $personnel->getType(),
            'status' => $personnel->getStatus(),
            'roles' => $personnel->getRoles(),
            'permissions' => $personnel->getPermissionCodes(),
            'roleAssignments' => $personnel->getRoleAssignmentSummary(),
            'service' => $service?->getLibelle(),
            'departement' => $service?->getDepartement()?->getLibelle(),
            'fonction' => $fonction?->getLibelle(),
            'grade' => $grade?->getLibelle(),
            'avatarUrl' => $this->avatarService->buildAvatarUrl($personnel),
            'signatureUrl' => $this->signatureService->buildSignatureUrl($personnel),
        ];
    }

    public function updateProfile(Personnel $personnel, UpdateMeProfileInput $input): Personnel
    {
        $this->assertValid($input);

        $personnel
            ->setNom(trim($input->nom))
            ->setPostNom(trim((string) ($input->postNom ?? '')))
            ->setPrenom($this->blankToNull($input->prenom))
            ->setSexe(strtoupper(trim($input->sexe)))
            ->setAdresse($this->blankToNull($input->adresse))
            ->setLieuNaissance($this->blankToNull($input->lieuNaissance));

        $this->entityManager->flush();

        return $personnel;
    }

    public function changePassword(Personnel $personnel, ChangeMePasswordInput $input): void
    {
        $this->assertValid($input);

        if (!$this->passwordHasher->isPasswordValid($personnel, $input->currentPassword)) {
            throw new ConflictException('Le mot de passe actuel est incorrect.');
        }

        $personnel->setPassword($this->passwordHasher->hashPassword($personnel, $input->newPassword));
        $this->entityManager->flush();
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
