<?php

namespace App\Service\Clinique;

/**
 * Normalise la grille d'examen physique tête-pieds (JSON).
 */
final class PhysicalExamNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function createEmpty(): array
    {
        return [
            'generalState' => ['status' => '', 'alteredDetails' => ''],
            'skin' => '',
            'headNeck' => [
                'crane' => '', 'cheveux' => '', 'face' => '', 'paupieres' => '',
                'conjonctives' => '', 'globesOculaires' => '', 'pupilles' => '',
                'levresBuccales' => '', 'muqueuseBuccale' => '', 'gencives' => '',
                'langue' => '', 'haleine' => '', 'gorge' => '', 'nez' => '',
                'oreilles' => '', 'parotides' => '', 'thyroide' => '', 'airesGanglionnaires' => '',
            ],
            'thorax' => [
                'osseux' => '', 'sein' => '',
                'poumon' => [
                    'inspection' => '', 'palpation' => '', 'percussion' => '',
                    'auscultation' => '', 'syndromePulmonaire' => '',
                ],
                'coeur' => ['inspection' => '', 'palpation' => '', 'auscultation' => ''],
                'vaisseaux' => ['arteres' => '', 'veines' => ''],
            ],
            'abdomen' => [
                'perimetreOmbilical' => '', 'inspection' => '', 'palpation' => '',
                'percussion' => '', 'auscultation' => '',
            ],
            'fossesLombaires' => '',
            'genitalOrgans' => [
                'pubis' => '', 'oge' => '', 'toucherVaginal' => '', 'toucherRectal' => '',
            ],
            'locomotor' => [
                'demarche' => '', 'membresSuperieurs' => '', 'membresInferieurs' => '', 'rachis' => '',
            ],
            'neurological' => [
                'etatMental' => '', 'posture' => '', 'demarche' => '', 'nerfsCraniens' => '',
                'motricite' => ['forceMusculaire' => '', 'tonusMusculaire' => ''],
                'coordination' => '', 'reflexes' => '', 'sensibilite' => '', 'signesMeninges' => '',
            ],
        ];
    }

    /**
     * @param mixed $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data): array
    {
        $empty = $this->createEmpty();
        if (!is_array($data)) {
            return $empty;
        }

        return [
            'generalState' => [
                'status' => (string) ($data['generalState']['status'] ?? ''),
                'alteredDetails' => (string) ($data['generalState']['alteredDetails'] ?? ''),
            ],
            'skin' => (string) ($data['skin'] ?? ''),
            'headNeck' => array_merge($empty['headNeck'], is_array($data['headNeck'] ?? null) ? $data['headNeck'] : []),
            'thorax' => [
                'osseux' => (string) ($data['thorax']['osseux'] ?? ''),
                'sein' => (string) ($data['thorax']['sein'] ?? ''),
                'poumon' => array_merge(
                    $empty['thorax']['poumon'],
                    is_array($data['thorax']['poumon'] ?? null) ? $data['thorax']['poumon'] : [],
                ),
                'coeur' => array_merge(
                    $empty['thorax']['coeur'],
                    is_array($data['thorax']['coeur'] ?? null) ? $data['thorax']['coeur'] : [],
                ),
                'vaisseaux' => array_merge(
                    $empty['thorax']['vaisseaux'],
                    is_array($data['thorax']['vaisseaux'] ?? null) ? $data['thorax']['vaisseaux'] : [],
                ),
            ],
            'abdomen' => array_merge($empty['abdomen'], is_array($data['abdomen'] ?? null) ? $data['abdomen'] : []),
            'fossesLombaires' => (string) ($data['fossesLombaires'] ?? ''),
            'genitalOrgans' => array_merge(
                $empty['genitalOrgans'],
                is_array($data['genitalOrgans'] ?? null) ? $data['genitalOrgans'] : [],
            ),
            'locomotor' => array_merge($empty['locomotor'], is_array($data['locomotor'] ?? null) ? $data['locomotor'] : []),
            'neurological' => [
                'etatMental' => (string) ($data['neurological']['etatMental'] ?? ''),
                'posture' => (string) ($data['neurological']['posture'] ?? ''),
                'demarche' => (string) ($data['neurological']['demarche'] ?? ''),
                'nerfsCraniens' => (string) ($data['neurological']['nerfsCraniens'] ?? ''),
                'motricite' => array_merge(
                    $empty['neurological']['motricite'],
                    is_array($data['neurological']['motricite'] ?? null) ? $data['neurological']['motricite'] : [],
                ),
                'coordination' => (string) ($data['neurological']['coordination'] ?? ''),
                'reflexes' => (string) ($data['neurological']['reflexes'] ?? ''),
                'sensibilite' => (string) ($data['neurological']['sensibilite'] ?? ''),
                'signesMeninges' => (string) ($data['neurological']['signesMeninges'] ?? ''),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $exam
     */
    public function hasData(array $exam): bool
    {
        $data = $this->normalize($exam);

        if ('' !== ($data['generalState']['status'] ?? '')) {
            return true;
        }

        return $this->walkFilled($data);
    }

    /**
     * @param mixed $value
     */
    private function walkFilled(mixed $value): bool
    {
        if (is_string($value)) {
            return '' !== trim($value);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->walkFilled($item)) {
                return true;
            }
        }

        return false;
    }
}
