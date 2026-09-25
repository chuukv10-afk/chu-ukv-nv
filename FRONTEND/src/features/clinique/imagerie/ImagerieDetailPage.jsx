import { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Stack, Tab, TabList, TabPanel, Tabs, Textarea, Typography,
} from '@mui/joy';
import { ArrowLeft, Check, FileText, List, Printer, Stethoscope, Trash2, Upload } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchAuthenticatedAvatarUrl } from '../../../utils/avatar.js';
import { formatDateTime, formatPatientName } from '../../pharmacie/shared/format.js';
import { buildPatientDpiPath } from '../../patient/patients/patientDpiTabs.js';
import {
  IMAGERIE_ACCEPT,
  IMAGERIE_MAX_SIZE_BYTES,
  IMAGERIE_STATUT_COLORS,
  IMAGERIE_STATUT_LABELS,
  isImageriePdf,
  medecinLabel,
} from './imagerieConstants.js';
import {
  annulerEtudeImagerieApi,
  deleteEtudeImageApi,
  deleteEtudeImagerieApi,
  fetchEtudeImagerieApi,
  interpretEtudeImagerieApi,
  openEtudeImagerieBonPdfApi,
  openEtudeImageriePdfApi,
  uploadEtudeImageApi,
  validerEtudeImagerieApi,
} from './imagerieApi.js';

async function openImagerieFile(image) {
  const url = await fetchAuthenticatedAvatarUrl(image.url) || image.viewUrl;
  if (!url) {
    throw new Error('Impossible d\'ouvrir le fichier.');
  }
  const opened = window.open(url, '_blank', 'noopener,noreferrer');
  if (!opened) {
    throw new Error('Autorisez les pop-ups pour ouvrir le fichier.');
  }
}

function MedicalImage({ src, viewUrl, alt, mimeType }) {
  const [url, setUrl] = useState(viewUrl || null);
  const [failedDirect, setFailedDirect] = useState(false);

  useEffect(() => {
    if (viewUrl && !failedDirect) {
      setUrl(viewUrl);
      return undefined;
    }
    let cancelled = false;
    fetchAuthenticatedAvatarUrl(src).then((value) => {
      if (!cancelled) setUrl(value);
    }).catch(() => {
      if (!cancelled) setUrl(null);
    });
    return () => { cancelled = true; };
  }, [src, viewUrl, failedDirect]);

  if (!url) return <Typography level="body-sm">Chargement…</Typography>;
  if (isImageriePdf({ mimeType, originalName: alt })) {
    return (
      <Stack spacing={0.5} alignItems="center" sx={{ py: 3 }}>
        <FileText size={36} />
        <Typography level="body-sm">Document PDF</Typography>
      </Stack>
    );
  }
  return (
    <img
      src={url}
      alt={alt || 'Image médicale'}
      onError={() => {
        if (viewUrl && !failedDirect) setFailedDirect(true);
      }}
      style={{ maxWidth: '100%', maxHeight: 280, borderRadius: 8, objectFit: 'contain', display: 'block' }}
    />
  );
}

export default function ImagerieDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();
  const { hasPermission, hasAnyPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canUpload = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_UPLOAD);
  const canInterpret = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_INTERPRET);
  const canValidate = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_VALIDATE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_EXPORT);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_DELETE);
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_UPDATE);
  const canSeeInterpretationPerm = hasAnyPermission([
    PERMISSIONS.CLINIQUE.IMAGERIE_INTERPRET,
    PERMISSIONS.CLINIQUE.IMAGERIE_VALIDATE,
    PERMISSIONS.CLINIQUE.IMAGERIE_EXPORT,
  ]);

  const [etude, setEtude] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [report, setReport] = useState('');
  const reportRef = useRef(null);
  const [confirmAction, setConfirmAction] = useState(null);
  const canSeeInterpretation = canSeeInterpretationPerm && etude?.canSeeInterpretation !== false;
  const requestedTab = searchParams.get('tab') === 'interpretation' ? 'interpretation' : 'images';
  const activeTab = canSeeInterpretation ? requestedTab : 'images';

  const goBack = () => {
    if (location.state?.fromDpi) {
      navigate(buildPatientDpiPath(location.state.fromDpi, 'imagerie'));
      return;
    }
    navigate(ROUTES.CLINIQUE.IMAGERIE);
  };

  const locked = etude?.statut === 'VALIDE' || etude?.statut === 'ANNULEE';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchEtudeImagerieApi(id);
      setEtude(data);
      setReport(data.resultat || [data.technique, data.constatations, data.conclusion].filter(Boolean).join('\n\n'));
    } catch (err) {
      showError(err.message || 'Étude introuvable.');
      navigate(ROUTES.CLINIQUE.IMAGERIE);
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!canSeeInterpretation && requestedTab === 'interpretation') {
      setSearchParams({}, { replace: true });
    }
  }, [canSeeInterpretation, requestedTab, setSearchParams]);

  const handleTabChange = (_, value) => {
    if (value === 'interpretation') {
      setSearchParams({ tab: 'interpretation' }, { replace: true });
      return;
    }
    setSearchParams({}, { replace: true });
  };

  const handleUpload = async (event) => {
    const files = Array.from(event.target.files || []);
    event.target.value = '';
    for (const file of files) {
      if (file.size > IMAGERIE_MAX_SIZE_BYTES) {
        showError(`${file.name} dépasse 50 Mo.`);
        continue;
      }
      setSaving(true);
      try {
        const updated = await uploadEtudeImageApi(id, file);
        setEtude(updated);
        showSuccess(`${file.name} enregistré.`);
      } catch (err) {
        showError(err.message || `Échec de l'envoi de ${file.name}.`);
      } finally {
        setSaving(false);
      }
    }
  };

  const handlePrintBon = async () => {
    try {
      await openEtudeImagerieBonPdfApi(id);
    } catch (err) {
      showError(err.message || 'Impossible de générer le bon de demande.');
    }
  };

  const handlePrintCompteRendu = async () => {
    try {
      await openEtudeImageriePdfApi(id);
    } catch (err) {
      showError(err.message || 'Impossible de générer le compte-rendu.');
    }
  };

  const insertDash = () => {
    const el = reportRef.current;
    const value = report;
    const start = el?.selectionStart ?? value.length;
    const end = el?.selectionEnd ?? value.length;
    const before = value.slice(0, start);
    const prefix = before.length === 0 || before.endsWith('\n') ? '- ' : '\n- ';
    const next = before + prefix + value.slice(end);
    setReport(next);
    window.requestAnimationFrame(() => {
      if (!el) return;
      const pos = before.length + prefix.length;
      el.focus();
      el.setSelectionRange(pos, pos);
    });
  };

  const handleReportKeyDown = (event) => {
    if (event.key !== 'Enter' || event.shiftKey) return;
    const el = event.target;
    const start = el.selectionStart ?? 0;
    const lineStart = report.lastIndexOf('\n', start - 1) + 1;
    const line = report.slice(lineStart, start);
    if (/^\s*[-–—•]\s*$/.test(line)) {
      event.preventDefault();
      const next = `${report.slice(0, lineStart)}\n${report.slice(el.selectionEnd ?? start)}`;
      setReport(next);
      window.requestAnimationFrame(() => {
        el.setSelectionRange(lineStart + 1, lineStart + 1);
      });
      return;
    }
    if (/^\s*[-–—•]\s+/.test(line)) {
      event.preventDefault();
      const before = report.slice(0, start);
      const next = `${before}\n- ${report.slice(el.selectionEnd ?? start)}`;
      setReport(next);
      window.requestAnimationFrame(() => {
        el.setSelectionRange(start + 3, start + 3);
      });
    }
  };

  const handleInterpret = async () => {
    if (!report.trim()) {
      showError('Saisissez le compte-rendu.');
      return;
    }
    setSaving(true);
    try {
      const updated = await interpretEtudeImagerieApi(id, { resultat: report.trim() });
      setEtude(updated);
      setReport(updated.resultat || report.trim());
      showSuccess('Interprétation enregistrée.');
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const runConfirmed = async () => {
    const action = confirmAction;
    setConfirmAction(null);
    setSaving(true);
    try {
      if (action === 'valider') {
        setEtude(await validerEtudeImagerieApi(id));
        showSuccess('Compte-rendu validé.');
      } else if (action === 'annuler') {
        setEtude(await annulerEtudeImagerieApi(id));
        showSuccess('Étude annulée.');
      } else if (action === 'delete') {
        await deleteEtudeImagerieApi(id);
        showSuccess('Étude supprimée.');
        goBack();
      }
    } catch (err) {
      showError(err.message || 'Action impossible.');
    } finally {
      setSaving(false);
    }
  };

  if (loading || !etude) {
    return <Typography>Chargement…</Typography>;
  }

  return (
    <Stack spacing={2}>
      <Stack direction="row" justifyContent="space-between" alignItems="center" flexWrap="wrap" gap={1}>
        <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} onClick={goBack}>
          {location.state?.fromDpi ? 'Dossier patient' : 'Journal'}
        </Button>
        <Chip color={IMAGERIE_STATUT_COLORS[etude.statut] || 'neutral'} variant="soft">
          {IMAGERIE_STATUT_LABELS[etude.statut] || etude.statut}
        </Chip>
      </Stack>

      <Card variant="outlined">
        <Typography level="h3">{etude.numero}</Typography>
        <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
          {etude.patient?.fullName || formatPatientName(etude.patient)} — {etude.examen?.libelle} — {formatDateTime(etude.createdAt)}
        </Typography>
        {etude.but ? <Typography sx={{ mt: 1 }}><strong>But :</strong> {etude.but}</Typography> : null}
        {etude.indication ? <Typography sx={{ mt: 1 }}><strong>Renseignements cliniques :</strong> {etude.indication}</Typography> : null}
        <Typography sx={{ mt: 1 }}><strong>Médecin demandeur :</strong> {medecinLabel(etude.demandePar)}</Typography>
        {etude.validePar?.nom ? (
          <Typography sx={{ mt: 0.5 }}><strong>Validé par :</strong> {medecinLabel(etude.validePar)}{etude.valideAt ? ` — ${formatDateTime(etude.valideAt)}` : ''}</Typography>
        ) : null}
        {canExport ? (
          <Stack direction="row" flexWrap="wrap" gap={1} sx={{ mt: 1.5 }}>
            {etude.statut !== 'ANNULEE' ? (
              <Button variant="outlined" startDecorator={<FileText size={16} />} onClick={handlePrintBon}>
                Générer le bon de demande
              </Button>
            ) : null}
            {etude.statut === 'INTERPRETE' || etude.statut === 'VALIDE' ? (
              <Button variant="outlined" startDecorator={<Printer size={16} />} onClick={handlePrintCompteRendu}>
                Générer le compte-rendu
              </Button>
            ) : null}
          </Stack>
        ) : null}
      </Card>

      <Tabs value={activeTab} onChange={handleTabChange}>
        <TabList>
          <Tab value="images"><Upload size={16} style={{ marginRight: 6 }} />Images</Tab>
          {canSeeInterpretation ? (
            <Tab value="interpretation"><Stethoscope size={16} style={{ marginRight: 6 }} />Interprétation</Tab>
          ) : null}
        </TabList>

        <TabPanel value="images" sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack direction="row" justifyContent="space-between" alignItems="center">
              <Typography level="title-lg">Images médicales</Typography>
              {canUpload && !locked ? (
                <Button component="label" size="sm" loading={saving} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>
                  Ajouter image ou PDF
                  <input hidden type="file" accept={IMAGERIE_ACCEPT} multiple onChange={handleUpload} />
                </Button>
              ) : null}
            </Stack>
            <Stack direction="row" flexWrap="wrap" gap={1.5} sx={{ mt: 1.5 }}>
              {(etude.images || []).length === 0 ? (
                <Typography level="body-sm">Aucun fichier. JPG, PNG, WebP ou PDF — 50 Mo max.</Typography>
              ) : etude.images.map((image) => (
                <Box
                  key={image.id}
                  sx={{
                    width: 240,
                    p: 1,
                    border: '1px solid',
                    borderColor: 'divider',
                    borderRadius: 'sm',
                    cursor: 'pointer',
                    '&:hover': { borderColor: 'primary.outlinedBorder', bgcolor: 'background.level1' },
                  }}
                  role="button"
                  tabIndex={0}
                  title="Cliquer pour ouvrir"
                  onClick={async () => {
                    try {
                      await openImagerieFile(image);
                    } catch (err) {
                      showError(err.message || 'Ouverture impossible.');
                    }
                  }}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                      event.preventDefault();
                      event.currentTarget.click();
                    }
                  }}
                >
                  <MedicalImage src={image.url} viewUrl={image.viewUrl} alt={image.originalName} mimeType={image.mimeType} />
                  <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mt: 0.5 }}>
                    <Typography level="body-xs" sx={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {image.originalName}
                    </Typography>
                    {canUpload && !locked ? (
                      <IconButton
                        size="sm"
                        color="danger"
                        variant="plain"
                        onClick={async (event) => {
                          event.stopPropagation();
                          try {
                            setEtude(await deleteEtudeImageApi(id, image.id));
                          } catch (err) {
                            showError(err.message || 'Suppression impossible.');
                          }
                        }}
                      >
                        <Trash2 size={14} />
                      </IconButton>
                    ) : null}
                  </Stack>
                </Box>
              ))}
            </Stack>
            <Stack direction="row" flexWrap="wrap" gap={1} sx={{ mt: 2 }}>
              {canUpdate && !locked ? (
                <Button variant="outlined" color="warning" onClick={() => setConfirmAction('annuler')}>Annuler l'étude</Button>
              ) : null}
              {canDelete && !locked ? (
                <Button variant="outlined" color="danger" onClick={() => setConfirmAction('delete')}>Supprimer</Button>
              ) : null}
            </Stack>
          </Card>
        </TabPanel>

        {canSeeInterpretation ? (
          <TabPanel value="interpretation" sx={{ p: 0, pt: 2 }}>
            <Card variant="outlined">
              <Typography level="title-lg">Interprétation médicale</Typography>
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], mt: 0.5 }}>
                Réservé aux médecins autorisés. Le manipulateur radio ne voit pas ce compte-rendu.
              </Typography>
              <Stack spacing={1} sx={{ mt: 1.5 }}>
                <FormControl>
                  <FormLabel>Compte-rendu</FormLabel>
                  <Textarea
                    minRows={10}
                    value={report}
                    disabled={!canInterpret || locked}
                    placeholder="Rédigez ici le résultat de l'examen…"
                    onChange={(e) => setReport(e.target.value)}
                    onKeyDown={handleReportKeyDown}
                    slotProps={{ textarea: { ref: reportRef } }}
                  />
                </FormControl>
                {canInterpret && !locked ? (
                  <Button
                    size="sm"
                    variant="outlined"
                    startDecorator={<List size={14} />}
                    onClick={insertDash}
                    sx={{ alignSelf: 'flex-start' }}
                  >
                    Ajouter un tiret
                  </Button>
                ) : null}
              </Stack>
              {etude.interpretePar?.nom ? (
                <Typography level="body-xs" sx={{ mt: 1.5, color: LOTRU_NEUTRAL[500] }}>
                  Interprété par {etude.interpretePar.nom}{etude.interpreteAt ? ` — ${formatDateTime(etude.interpreteAt)}` : ''}
                </Typography>
              ) : null}
              <Stack direction="row" flexWrap="wrap" gap={1} sx={{ mt: 2 }}>
                {canInterpret && !locked ? (
                  <Button loading={saving} onClick={handleInterpret} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>Enregistrer l'interprétation</Button>
                ) : null}
                {canValidate && etude.statut === 'INTERPRETE' ? (
                  <Button color="success" startDecorator={<Check size={16} />} onClick={() => setConfirmAction('valider')}>Valider</Button>
                ) : null}
                {canExport && (etude.statut === 'INTERPRETE' || etude.statut === 'VALIDE') ? (
                  <Button variant="outlined" startDecorator={<Printer size={16} />} onClick={handlePrintCompteRendu}>
                    Générer le compte-rendu
                  </Button>
                ) : null}
              </Stack>
            </Card>
          </TabPanel>
        ) : null}
      </Tabs>

      <ConfirmModal
        open={Boolean(confirmAction)}
        title={confirmAction === 'valider' ? 'Valider le compte-rendu ?' : confirmAction === 'delete' ? 'Supprimer cette étude ?' : 'Annuler cette étude ?'}
        message={confirmAction === 'valider'
          ? 'Le compte-rendu sera figé. Les images et l\'interprétation ne pourront plus être modifiées.'
          : confirmAction === 'delete'
            ? 'Les images associées seront retirées du stockage. Cette action est irréversible.'
            : 'L\'étude passera au statut Annulée.'}
        confirmLabel="Confirmer"
        color={confirmAction === 'valider' ? 'success' : 'danger'}
        loading={saving}
        onClose={() => setConfirmAction(null)}
        onConfirm={runConfirmed}
      />
    </Stack>
  );
}
