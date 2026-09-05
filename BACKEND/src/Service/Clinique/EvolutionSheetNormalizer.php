<?php

namespace App\Service\Clinique;

final class EvolutionSheetNormalizer
{
    public const VERSION = 1;

    public const SYMPTOM_NONE = 'NONE';
    public const SYMPTOM_COMPLAINTS = 'COMPLAINTS';

    public const EVAL_GOOD = 'GOOD';
    public const EVAL_STABLE = 'STABLE';
    public const EVAL_WORSENING = 'WORSENING';

    public const DX_RETAINED = 'RETAINED';
    public const DX_EVOLVED = 'EVOLVED';

    /** @return array<string, mixed> */
    public function empty(): array
    {
        return [
            'version' => self::VERSION,
            'symptoms' => [
                'mode' => self::SYMPTOM_NONE,
                'selectedComplaints' => [],
                'freeText' => '',
            ],
            'clinicalEvaluation' => [
                'type' => '',
                'worseningDetails' => '',
            ],
            'diagnosisEvolutionMode' => self::DX_RETAINED,
            'continueCurrentTreatment' => true,
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    public function normalize(?array $payload): array
    {
        $base = $this->empty();
        if (!is_array($payload)) {
            return $base;
        }

        $mode = strtoupper(trim((string) ($payload['symptoms']['mode'] ?? $base['symptoms']['mode'])));
        $base['symptoms']['mode'] = in_array($mode, [self::SYMPTOM_NONE, self::SYMPTOM_COMPLAINTS], true)
            ? $mode
            : self::SYMPTOM_NONE;

        $complaints = $payload['symptoms']['selectedComplaints'] ?? [];
        $base['symptoms']['selectedComplaints'] = [];
        if (is_array($complaints)) {
            foreach ($complaints as $complaint) {
                if (!is_string($complaint)) {
                    continue;
                }
                $label = trim($complaint);
                if ('' === $label) {
                    continue;
                }
                $base['symptoms']['selectedComplaints'][] = mb_substr($label, 0, 80);
            }
        }

        $freeText = trim((string) ($payload['symptoms']['freeText'] ?? ''));
        $base['symptoms']['freeText'] = '' === $freeText ? '' : mb_substr($freeText, 0, 2000);

        $evalType = strtoupper(trim((string) ($payload['clinicalEvaluation']['type'] ?? '')));
        $base['clinicalEvaluation']['type'] = in_array($evalType, [self::EVAL_GOOD, self::EVAL_STABLE, self::EVAL_WORSENING], true)
            ? $evalType
            : '';

        $worsening = trim((string) ($payload['clinicalEvaluation']['worseningDetails'] ?? ''));
        $base['clinicalEvaluation']['worseningDetails'] = '' === $worsening ? '' : mb_substr($worsening, 0, 2000);

        $dxMode = strtoupper(trim((string) ($payload['diagnosisEvolutionMode'] ?? $base['diagnosisEvolutionMode'])));
        $base['diagnosisEvolutionMode'] = in_array($dxMode, [self::DX_RETAINED, self::DX_EVOLVED], true)
            ? $dxMode
            : self::DX_RETAINED;

        $base['continueCurrentTreatment'] = (bool) ($payload['continueCurrentTreatment'] ?? true);

        return $base;
    }
}
