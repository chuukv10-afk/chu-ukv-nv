<?php

namespace App\Service\Clinique;

use App\Entity\CertificatAptitude;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Service\Export\ChuPdfLayoutProvider;
use App\Service\Export\PdfExportService;
use App\Service\Personnel\PersonnelSignatureService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\HttpFoundation\Response;

final class AptitudePdfService
{
    public function __construct(
        private readonly ChuPdfLayoutProvider $layoutProvider,
        private readonly PdfExportService $pdfExportService,
        private readonly PersonnelSignatureService $signatureService,
    ) {
    }

    public function createResponse(CertificatAptitude $certificat): Response
    {
        if ($certificat->isBrouillon()) {
            throw new ConflictException('Le PDF officiel n\'est disponible qu\'après signature.');
        }

        $html = $this->layoutProvider->buildDocument(
            'Certificat d\'aptitude physique',
            $this->renderBody($certificat),
            $this->doctorName($certificat),
            $certificat->getSigneAt(),
            'portrait',
            null,
            null,
            $this->renderHeaderQr($certificat),
        );

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($certificat->getNumero() ?? $certificat->getId())) ?: 'cap';

        return $this->pdfExportService->createDownloadResponse(
            $html,
            'certificat-aptitude-' . $slug . '.pdf',
            'portrait',
            inline: true,
        );
    }

    private function renderBody(CertificatAptitude $certificat): string
    {
        $numero = $this->e($certificat->getNumero() ?? '—');
        $service = $this->e($certificat->getService()?->getLibelle() ?? '—');
        $annule = CertificatAptitude::STATUT_ANNULE === $certificat->getStatut();
        $watermark = $annule ? '<div class="cap-watermark">ANNULÉ</div>' : '';
        $signeAt = $certificat->getSigneAt();
        $dateLine = $signeAt instanceof \DateTimeImmutable
            ? sprintf('Fait à Boma, le %s', $signeAt->format('d / m / Y'))
            : 'Fait à Boma, le —';
        $doctor = $this->e($this->doctorName($certificat));
        $naissance = $certificat->getDateNaissance()?->format('d/m/Y') ?? '—';
        $imc = $this->fmt($certificat->getImc());
        $pignet = $this->fmt($certificat->getPignet());
        $ruffier = $this->fmt($certificat->getRuffier());
        $dickson = $this->fmt($certificat->getDickson());
        $tailleM = $this->fmt($certificat->getTailleM());
        $tailleCm = null !== $certificat->getTailleM()
            ? $this->fmt(number_format(((float) $certificat->getTailleM()) * 100, 2, '.', ''))
            : '—';

        return <<<HTML
{$watermark}
<div class="cap-meta">
    <span>Service : <strong>{$service}</strong></span>
    <span>N° {$numero}</span>
</div>

<h2 class="cap-section">I. Identification du candidat / de la candidate</h2>
<p><strong>Nom, Postnom &amp; Prénom :</strong> {$this->e($certificat->getFullName())}</p>
<table class="cap-grid">
    <tr>
        <td>Sexe : {$this->box($certificat->getSexe() === CertificatAptitude::SEXE_MASCULIN)} M
            {$this->box($certificat->getSexe() === CertificatAptitude::SEXE_FEMININ)} F</td>
        <td>État civil : {$this->e($certificat->getEtatCivil() ?? '—')}</td>
    </tr>
</table>
<p><strong>Né(e) le :</strong> {$naissance} &nbsp; <strong>à</strong> {$this->e($certificat->getLieuNaissance() ?? '—')}</p>
<p><strong>Adresse de résidence :</strong> {$this->e($certificat->getAdresse() ?? '—')}</p>
<p><strong>Motif de l'examen :</strong>
    {$this->box($certificat->getMotif() === CertificatAptitude::MOTIF_ADMISSION_UKV)} Admission Universitaire UKV
    &nbsp; {$this->box($certificat->getMotif() === CertificatAptitude::MOTIF_EMPLOI)} Emploi
    &nbsp; {$this->box($certificat->getMotif() === CertificatAptitude::MOTIF_AUTRE)} Autre
    {$this->e($certificat->getMotifAutre() ? ' (' . $certificat->getMotifAutre() . ')' : '')}
</p>
{$this->filiereLine($certificat)}

<h2 class="cap-section">II. Indice de masse corporelle (IMC)</h2>
<table class="cap-grid">
    <tr>
        <td>Poids (P) : {$this->fmt($certificat->getPoidsKg())} kg</td>
        <td>Résultat IMC : {$imc} kg/m²</td>
    </tr>
    <tr>
        <td>Taille (T) : {$tailleM} m</td>
        <td>Formule : IMC = P / T²</td>
    </tr>
</table>
<p><strong>Interprétation (OMS) :</strong></p>
<p>
    {$this->box($certificat->getImcClasse() === CertificatAptitude::IMC_MAIGREUR)} Insuffisance pondérale
    &nbsp; {$this->box($certificat->getImcClasse() === CertificatAptitude::IMC_NORMAL)} Corpulence normale
    &nbsp; {$this->box($certificat->getImcClasse() === CertificatAptitude::IMC_SURPOIDS)} Surpoids
</p>
<p>
    {$this->box($certificat->getImcClasse() === CertificatAptitude::IMC_OBESITE_I)} Obésité modérée (I)
    &nbsp; {$this->box($certificat->getImcClasse() === CertificatAptitude::IMC_OBESITE_II)} Obésité sévère (II)
    &nbsp; {$this->box($certificat->getImcClasse() === CertificatAptitude::IMC_OBESITE_III)} Obésité morbide (III)
</p>

<h2 class="cap-section">III. Indice de Pignet (Constitution physique)</h2>
<table class="cap-grid">
    <tr>
        <td>Périmètre thoracique (C) : {$this->fmt($certificat->getPerimetreThoraciqueCm())} cm</td>
        <td>Formule : I = T_cm − (P + C)</td>
    </tr>
    <tr>
        <td>Taille (T) : {$tailleM} m soit {$tailleCm} cm</td>
        <td>Indice de Pignet : {$pignet}</td>
    </tr>
    <tr>
        <td>Poids (P) : {$this->fmt($certificat->getPoidsKg())} kg</td>
        <td>Robustesse : {$this->e($this->pignetLabel($certificat->getPignetRobustesse()))}</td>
    </tr>
</table>
<p>
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_TRES_FORTE)} Très forte
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_FORTE)} Forte
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_BONNE)} Bonne
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_MOYENNE)} Moyenne
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_FAIBLE)} Faible
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_TRES_FAIBLE)} Très faible
    {$this->box($certificat->getPignetRobustesse() === CertificatAptitude::PIGNET_EXTREME)} Extrêmement faible
</p>

<h2 class="cap-section">IV. Indice de Ruffier-Dickson (Adaptation cardiaque à l'effort)</h2>
<table class="cap-grid">
    <tr>
        <td>P1 (Repos) : {$this->e((string) ($certificat->getP1() ?? '—'))} /min</td>
        <td>Indice Ruffier : ((P1+P2+P3)−200)/10 = {$ruffier}</td>
    </tr>
    <tr>
        <td>P2 (Post-effort) : {$this->e((string) ($certificat->getP2() ?? '—'))} /min</td>
        <td>Indice Dickson : ((P2−70)+2×(P3−P1))/10 = {$dickson}</td>
    </tr>
    <tr>
        <td>P3 (Récupération 1') : {$this->e((string) ($certificat->getP3() ?? '—'))} /min</td>
        <td>Ruffier : {$this->e($this->ruffierLabel($certificat->getRuffierClasse()))}<br>
            Dickson : {$this->e($this->dicksonLabel($certificat->getDicksonClasse()))}</td>
    </tr>
</table>
<p><strong>Ruffier :</strong>
    {$this->box($certificat->getRuffierClasse() === CertificatAptitude::RUFFIER_EXCELLENTE)} Excellente
    {$this->box($certificat->getRuffierClasse() === CertificatAptitude::RUFFIER_BONNE)} Bonne
    {$this->box($certificat->getRuffierClasse() === CertificatAptitude::RUFFIER_MOYENNE)} Moyenne
    {$this->box($certificat->getRuffierClasse() === CertificatAptitude::RUFFIER_INSUFFISANTE)} Insuffisante
    {$this->box($certificat->getRuffierClasse() === CertificatAptitude::RUFFIER_MAUVAISE)} Mauvaise
</p>
<p><strong>Dickson :</strong>
    {$this->box($certificat->getDicksonClasse() === CertificatAptitude::DICKSON_EXCELLENT)} Excellent
    {$this->box($certificat->getDicksonClasse() === CertificatAptitude::DICKSON_TRES_BON)} Très bon
    {$this->box($certificat->getDicksonClasse() === CertificatAptitude::DICKSON_BON)} Bon
    {$this->box($certificat->getDicksonClasse() === CertificatAptitude::DICKSON_MOYEN)} Moyen
    {$this->box($certificat->getDicksonClasse() === CertificatAptitude::DICKSON_FAIBLE)} Faible
    {$this->box($certificat->getDicksonClasse() === CertificatAptitude::DICKSON_MAUVAIS)} Mauvais
</p>

<h2 class="cap-section">V. Conclusion et verdict médical</h2>
<p>
    Après examen clinique complet et analyse des différents indices anthropométriques et d'adaptation à l'effort
    susmentionnés, je soussigné, Docteur en Médecine au CHU UKV Boma, déclare le/la candidat(e) :
</p>
<p class="cap-verdict">
    {$this->box($certificat->getVerdict() === CertificatAptitude::VERDICT_APTE)} <strong>APTE</strong>
    &nbsp;&nbsp;&nbsp;
    {$this->box($certificat->getVerdict() === CertificatAptitude::VERDICT_INAPTE)} <strong>INAPTE</strong>
    &nbsp; à exercer les activités liées au motif de la consultation mentionné en Section I.
</p>
<p class="cap-date">{$dateLine}</p>
<p class="cap-sign">Le Médecin Examinateur<br>(Nom, Signature et Cachet)<br>{$this->signatureImage($certificat)}<strong>{$doctor}</strong></p>
<p class="cap-note">Valable trois (03) mois à compter de la date de signature. Nul et de nul effet sans le cachet officiel, la signature du médecin et le code QR d'authentification.</p>
<style>
h1.report-title { margin: 28px 0 10px; font-size: 16px; color: #111; }
p { margin: 2px 0; }
.cap-meta { display: table; width: 100%; margin-bottom: 4px; font-size: 10px; }
.cap-meta span { display: table-cell; }
.cap-meta span:last-child { text-align: right; }
.cap-section { font-size: 10.5px; margin: 6px 0 3px; text-transform: uppercase; color: #1E5AA8; }
.cap-grid { width: 100%; border-collapse: collapse; margin: 2px 0 4px; }
.cap-grid td { border: 1px solid #d0d7de; padding: 3px 6px; width: 50%; vertical-align: top; }
.cap-verdict { font-size: 12px; margin: 6px 0; }
.cap-date { text-align: right; margin-top: 24px; }
.cap-sign { text-align: right; margin-top: 10px; }
.cap-signature-img { display: block; max-height: 58px; max-width: 170px; margin: 6px 0 4px auto; }
.cap-note { font-size: 8px; font-style: italic; color: #444; margin-top: 8px; }
.cap-watermark {
    position: fixed; top: 42%; left: 8%; font-size: 64px; color: #c00; opacity: 0.18;
    transform: rotate(-22deg); font-weight: bold; letter-spacing: 8px;
}
</style>
HTML;
    }

    private function renderHeaderQr(CertificatAptitude $certificat): string
    {
        return '<img src="' . $this->qrDataUri($certificat) . '" alt="QR certificat" /><div class="cap-qr-caption">Authentification</div>';
    }

    private function qrDataUri(CertificatAptitude $certificat): string
    {
        $valide = $certificat->getValideJusqua()?->format('d/m/Y') ?? '—';
        $payload = implode("\n", [
            'CHU UKV CAP',
            (string) ($certificat->getNumero() ?? '—'),
            $certificat->getFullName(),
            'Verdict: ' . ($certificat->getVerdict() ?? '—'),
            'Valable: ' . $valide,
            'Statut: ' . $certificat->getStatut(),
        ]);

        return (new Builder(
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 2,
        ))->build()->getDataUri();
    }

    private function pignetLabel(?string $value): string
    {
        return match ($value) {
            CertificatAptitude::PIGNET_TRES_FORTE => 'Très forte',
            CertificatAptitude::PIGNET_FORTE => 'Forte',
            CertificatAptitude::PIGNET_BONNE => 'Bonne',
            CertificatAptitude::PIGNET_MOYENNE => 'Moyenne',
            CertificatAptitude::PIGNET_FAIBLE => 'Faible',
            CertificatAptitude::PIGNET_TRES_FAIBLE => 'Très faible',
            CertificatAptitude::PIGNET_EXTREME => 'Extrêmement faible',
            default => '—',
        };
    }

    private function ruffierLabel(?string $value): string
    {
        return match ($value) {
            CertificatAptitude::RUFFIER_EXCELLENTE => 'Excellente',
            CertificatAptitude::RUFFIER_BONNE => 'Bonne',
            CertificatAptitude::RUFFIER_MOYENNE => 'Moyenne',
            CertificatAptitude::RUFFIER_INSUFFISANTE => 'Insuffisante',
            CertificatAptitude::RUFFIER_MAUVAISE => 'Mauvaise',
            default => '—',
        };
    }

    private function dicksonLabel(?string $value): string
    {
        return match ($value) {
            CertificatAptitude::DICKSON_EXCELLENT => 'Excellent',
            CertificatAptitude::DICKSON_TRES_BON => 'Très bon',
            CertificatAptitude::DICKSON_BON => 'Bon',
            CertificatAptitude::DICKSON_MOYEN => 'Moyen',
            CertificatAptitude::DICKSON_FAIBLE => 'Faible',
            CertificatAptitude::DICKSON_MAUVAIS => 'Mauvais',
            default => '—',
        };
    }

    private function signatureImage(CertificatAptitude $certificat): string
    {
        $doctor = $certificat->getSignePar();
        if (!$doctor instanceof Personnel) {
            return '';
        }

        $dataUri = $this->signatureService->toDataUri($doctor);
        if (null === $dataUri) {
            return '';
        }

        return '<img class="cap-signature-img" src="' . $dataUri . '" alt="Signature" /><br>';
    }

    private function doctorName(CertificatAptitude $certificat): string
    {
        $doctor = $certificat->getSignePar();
        if (null === $doctor) {
            return '';
        }

        return trim(sprintf(
            '%s %s %s',
            $doctor->getPrenom() ?? '',
            $doctor->getNom() ?? '',
            $doctor->getPostNom() ?? '',
        ));
    }

    private function filiereLine(CertificatAptitude $certificat): string
    {
        if (CertificatAptitude::MOTIF_ADMISSION_UKV !== $certificat->getMotif()) {
            return '';
        }

        $filiere = $certificat->getFiliere();
        $label = null !== $filiere
            ? trim(sprintf('%s — %s', $filiere->getCode() ?? '', $filiere->getLibelle() ?? ''))
            : '—';

        return '<p><strong>Filière :</strong> ' . $this->e($label) . '</p>';
    }

    private function box(bool $on): string
    {
        return $on ? '☑' : '☐';
    }

    private function fmt(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '—';
        }

        return str_replace('.', ',', $value);
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
