import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Stack, Textarea, Typography,
} from '@mui/joy';
import { ArrowLeft, Check, FileText, Printer, Trash2, Upload } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchAuthenticatedAvatarUrl } from '../../../utils/avatar.js';
import { formatDateTime, formatPatientName } from '../../pharmacie/shared/format.js';
import {
  IMAGERIE_ACCEPT,
  IMAGERIE_MAX_SIZE_BYTES,
  IMAGERIE_STATUT_COLORS,
  IMAGERIE_STATUT_LABELS,
} from './imagerieConstants.js';
import {
  annulerEtudeImagerieApi,
  deleteEtudeImageApi,
  deleteEtudeImagerieApi,
  fetchEtudeImagerieApi,
  interpretEtudeImagerieApi,
  openEtudeImageriePdfApi,
  uploadEtudeImageApi,
  validerEtudeImagerieApi,
} from './imagerieApi.js';

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
  if (String(mimeType || '').includes('pdf') || String(alt || '').toLowerCase().endsWith('.pdf')) {
    return <a href={url} target="_blank" rel="noreferrer">Ouvrir le PDF</a>;
  }
  return (
    <img
      src={url}
      alt={alt || 'Image médicale'}
      onError={() => {
        if (viewUrl && !failedDirect) setFailedDirect(true);
      }}
      style={{ maxWidth: '100%', maxHeight: 280, borderRadius: 8, objectFit: 'contain' }}
    />
  );
}

export default function ImagerieDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canUpload = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_UPLOAD);
  const canInterpret = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_INTERPRET);
  const canValidate = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_VALIDATE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_EXPORT);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_DELETE);
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_UPDATE);

  const [etude, setEtude] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [report, setReport] = useState({ technique: '', constatations: '', conclusion: '' });
  const [confirmAction, setConfirmAction] = useState(null);

  const locked = etude?.statut === 'VALIDE' || etude?.statut === 'ANNULEE';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchEtudeImagerieApi(id);
      setEtude(data);
      setReport({
        technique: data.technique || '',
        constatations: data.constatations || '',
        conclusion: data.conclusion || '',
      });
    } catch (err) {
      showError(err.message || 'Étude introuvable.');
      navigate(ROUTES.CLINIQUE.IMAGERIE);
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => { load(); }, [load]);

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

  const handleInterpret = async () => {
    if (!report.constatations.trim() || !report.conclusion.trim()) {
      showError('Constatations et conclusion sont obligatoires.');
      return;
    }
    setSaving(true);
    try {
      const updated = await interpretEtudeImagerieApi(id, report);
      setEtude(updated);
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
        navigate(ROUTES.CLINIQUE.IMAGERIE);
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
        <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.CLINIQUE.IMAGERIE)}>
          Journal
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
        {etude.indication ? <Typography sx={{ mt: 1 }}><strong>Indication :</strong> {etude.indication}</Typography> : null}
        <Typography level="body-xs" sx={{ mt: 1, color: LOTRU_NEUTRAL[500] }}>
          Le partage de ce dossier vers d'autres services sera disponible prochainement.
        </Typography>
      </Card>

      <Card variant="outlined">
        <Stack direction="row" justifyContent="space-between" alignItems="center">
          <Typography level="title-lg" startDecorator={<Upload size={18} />}>Images médicales</Typography>
          {canUpload && !locked ? (
            <Button component="label" size="sm" loading={saving} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>
              Ajouter
              <input hidden type="file" accept={IMAGERIE_ACCEPT} multiple onChange={handleUpload} />
            </Button>
          ) : null}
        </Stack>
        <Stack direction="row" flexWrap="wrap" gap={1.5} sx={{ mt: 1.5 }}>
          {(etude.images || []).length === 0 ? (
            <Typography level="body-sm">Aucune image. JPG, PNG, WebP ou PDF — 50 Mo max, envoi sécurisé vers S3.</Typography>
          ) : etude.images.map((image) => (
            <Box key={image.id} sx={{ width: 240, p: 1, border: '1px solid', borderColor: 'divider', borderRadius: 'sm' }}>
              {String(image.mimeType || '').startsWith('image/') ? (
                <MedicalImage src={image.url} viewUrl={image.viewUrl} alt={image.originalName} mimeType={image.mimeType} />
              ) : (
                <Typography startDecorator={<FileText size={14} />}>{image.originalName}</Typography>
              )}
              <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mt: 0.5 }}>
                <Typography level="body-xs">{image.originalName}</Typography>
                {canUpload && !locked ? (
                  <IconButton size="sm" color="danger" variant="plain" onClick={async () => {
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
      </Card>

      <Card variant="outlined">
        <Typography level="title-lg">Interprétation médicale</Typography>
        <Stack spacing={1.5} sx={{ mt: 1.5 }}>
          <FormControl>
            <FormLabel>Technique</FormLabel>
            <Textarea minRows={2} value={report.technique} disabled={!canInterpret || locked} onChange={(e) => setReport((current) => ({ ...current, technique: e.target.value }))} />
          </FormControl>
          <FormControl>
            <FormLabel>Constatations</FormLabel>
            <Textarea minRows={4} value={report.constatations} disabled={!canInterpret || locked} onChange={(e) => setReport((current) => ({ ...current, constatations: e.target.value }))} />
          </FormControl>
          <FormControl>
            <FormLabel>Conclusion</FormLabel>
            <Textarea minRows={3} value={report.conclusion} disabled={!canInterpret || locked} onChange={(e) => setReport((current) => ({ ...current, conclusion: e.target.value }))} />
          </FormControl>
        </Stack>
        <Stack direction="row" flexWrap="wrap" gap={1} sx={{ mt: 2 }}>
          {canInterpret && !locked ? (
            <Button loading={saving} onClick={handleInterpret} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>Enregistrer l'interprétation</Button>
          ) : null}
          {canValidate && etude.statut === 'INTERPRETE' ? (
            <Button color="success" startDecorator={<Check size={16} />} onClick={() => setConfirmAction('valider')}>Valider</Button>
          ) : null}
          {canExport && (etude.statut === 'INTERPRETE' || etude.statut === 'VALIDE') ? (
            <Button variant="outlined" startDecorator={<Printer size={16} />} onClick={() => openEtudeImageriePdfApi(id)}>Imprimer</Button>
          ) : null}
          {canUpdate && !locked ? (
            <Button variant="outlined" color="warning" onClick={() => setConfirmAction('annuler')}>Annuler l'étude</Button>
          ) : null}
          {canDelete && !locked ? (
            <Button variant="outlined" color="danger" onClick={() => setConfirmAction('delete')}>Supprimer</Button>
          ) : null}
        </Stack>
      </Card>

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
