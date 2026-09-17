import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Button, Chip, FormControl, FormLabel, Input, Modal, ModalDialog,
  Sheet, Stack, Switch, Table, Typography,
} from '@mui/joy';
import { ArrowLeft, Scale } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import {
  exportPaiePeriodeApi,
  fetchPaiePeriodeApi,
  generatePaiePeriodeApi,
  updatePaieLigneApi,
  validatePaiePeriodeApi,
} from './paieApi.js';
import {
  PAIE_STATUT_COLORS,
  PAIE_STATUT_LABELS,
  formatPaieMontant,
  isPaieProposal,
  ligneEtat,
} from './paieConstants.js';

export default function PaiePeriodePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canUpdate = hasPermission(PERMISSIONS.RH.PAIE_UPDATE);
  const canValidate = hasPermission(PERMISSIONS.RH.PAIE_VALIDATE);

  const [periode, setPeriode] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [exportLoading, setExportLoading] = useState(null);
  const [pendingValidate, setPendingValidate] = useState(false);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const [editing, setEditing] = useState(null);
  const [inclus, setInclus] = useState(true);
  const [montant, setMontant] = useState('');
  const [motif, setMotif] = useState('');
  const [savingLigne, setSavingLigne] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [showTarifs, setShowTarifs] = useState(false);
  const canOpenFiche = hasPermission(PERMISSIONS.RH.PERSONNEL_READ) || hasPermission(PERMISSIONS.RH.PERSONNEL_UPDATE);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setPeriode(await fetchPaiePeriodeApi(id));
    } catch (err) {
      setError(err.message || 'Impossible de charger ce mois.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { load(); }, [load]);

  const brouillon = periode?.statut === 'BROUILLON';
  const canEdit = brouillon && canUpdate;
  const lignes = periode?.lignes ?? [];
  const aCompleter = (periode?.nbLignesATraiter ?? 0);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return lignes;
    return lignes.filter((ligne) => [ligne.nomComplet, ligne.matricule, ligne.gradeLibelle, ligne.fonctionLibelle]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(q)));
  }, [lignes, search]);

  const handleExport = async (format) => {
    if (!periode) return;
    setExportLoading(format);
    try {
      await exportPaiePeriodeApi(periode.id, format);
    } catch (err) {
      showError(err.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleRefresh = async () => {
    if (!periode) return;
    setRefreshing(true);
    try {
      setPeriode(await generatePaiePeriodeApi(periode.id));
      showSuccess('Liste mise à jour à partir du personnel.');
    } catch (err) {
      showError(err.message || 'Mise à jour impossible.');
    } finally {
      setRefreshing(false);
    }
  };

  const handleValidate = async () => {
    if (!periode) return;
    setConfirmLoading(true);
    try {
      const data = await validatePaiePeriodeApi(periode.id);
      setPeriode(data);
      setPendingValidate(false);
      showSuccess('Mois clôturé.');
    } catch (err) {
      showError(err.message || 'Clôture impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const openEdit = (ligne) => {
    if (!canEdit) return;
    setEditing(ligne);
    const proposed = ligne.montantPropose != null ? String(Number(ligne.montantPropose)) : '';
    setInclus(ligne.inclus || Boolean(proposed) || ligne.etat === 'sans_bareme');
    setMontant(ligne.inclus ? String(Number(ligne.montant || 0)) : proposed);
    setMotif(ligne.motif || '');
  };

  const handleSaveLigne = async () => {
    if (!periode || !editing) return;
    if (inclus && (!montant || Number(montant) <= 0)) {
      showError('Indiquez le montant à payer.');
      return;
    }
    setSavingLigne(true);
    try {
      const payload = { inclus };
      if (inclus) payload.montant = String(montant).replace(',', '.');
      payload.motif = motif.trim();
      const data = await updatePaieLigneApi(periode.id, editing.id, payload);
      setPeriode(data);
      setEditing(null);
      showSuccess('Ligne enregistrée.');
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSavingLigne(false);
    }
  };

  if (loading) {
    return <Typography level="body-sm">Chargement…</Typography>;
  }

  if (error || !periode) {
    return (
      <Stack spacing={2}>
        <Button variant="plain" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(`${ROUTES.RH.PAIE}?liste=1`)}>
          Retour
        </Button>
        <Typography level="body-sm" color="danger">{error || 'Mois introuvable.'}</Typography>
      </Stack>
    );
  }

  const ficheManquante = editing && (editing.etat === 'sans_fonction' || editing.etat === 'sans_grade');

  return (
    <Stack spacing={2.5}>
      <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" alignItems={{ md: 'flex-start' }} spacing={1.5}>
        <Stack spacing={0.75}>
          <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} sx={{ alignSelf: 'flex-start', px: 0 }} onClick={() => navigate(`${ROUTES.RH.PAIE}?liste=1`)}>
            Tous les mois
          </Button>
          <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
            <Typography level="h2" sx={{ fontWeight: 700 }}>{periode.libelle}</Typography>
            <Chip size="sm" variant="soft" color={PAIE_STATUT_COLORS[periode.statut] ?? 'neutral'}>
              {PAIE_STATUT_LABELS[periode.statut] ?? periode.statut}
            </Chip>
          </Stack>
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
            {periode.nbLignesIncluses ?? 0} agent{(periode.nbLignesIncluses ?? 0) > 1 ? 's' : ''} · Total {formatPaieMontant(periode.totalNet)}
            {aCompleter > 0 ? ` · ${aCompleter} à compléter` : ''}
          </Typography>
        </Stack>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button variant="outlined" color="neutral" size="sm" startDecorator={<Scale size={16} />} onClick={() => navigate(ROUTES.RH.PAIE_BAREME)}>
            Tarifs
          </Button>
          <ExportButtons onExport={handleExport} loading={exportLoading} size="sm" />
          {brouillon && canValidate ? (
            <Button size="sm" color="success" onClick={() => setPendingValidate(true)}>
              Clôturer
            </Button>
          ) : null}
        </Stack>
      </Stack>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }}>
        <Input placeholder="Rechercher un agent…" value={search} onChange={(event) => setSearch(event.target.value)} sx={{ flex: 1 }} />
        {canEdit ? (
          <Button variant="plain" size="sm" loading={refreshing} onClick={handleRefresh}>
            Actualiser depuis le personnel
          </Button>
        ) : null}
        {(periode.baremeApplique?.length ?? 0) > 0 ? (
          <Button variant="plain" size="sm" onClick={() => setShowTarifs((open) => !open)}>
            {showTarifs ? 'Masquer les tarifs' : 'Tarifs de ce mois'}
          </Button>
        ) : null}
      </Stack>

      <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
        <Table stickyHeader hoverRow sx={{ minWidth: 720 }}>
          <thead>
            <tr>
              <th style={{ width: 48 }}>N°</th>
              <th>Nom</th>
              <th>Grade</th>
              <th>Fonction</th>
              <th>Net</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr>
                <td colSpan={6}>
                  <Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun agent.</Typography>
                </td>
              </tr>
            ) : filtered.map((ligne, index) => {
              const etat = ligneEtat(ligne);
              return (
                <tr
                  key={ligne.id}
                  style={{ cursor: canEdit ? 'pointer' : 'default' }}
                  onClick={() => openEdit(ligne)}
                >
                  <td>{index + 1}</td>
                  <td>
                    <Typography level="body-sm" sx={{ fontWeight: 600 }}>{ligne.nomComplet}</Typography>
                  </td>
                  <td>{ligne.gradeLibelle || '—'}</td>
                  <td>{ligne.fonctionLibelle || '—'}</td>
                  <td>
                    <Typography level="body-sm" sx={{ fontWeight: 600, color: ligne.inclus ? LOTRU_PRIMARY[700] : 'neutral.500' }}>
                      {ligne.inclus ? formatPaieMontant(ligne.montant) : '—'}
                    </Typography>
                  </td>
                  <td>
                    <Chip size="sm" variant="soft" color={etat.color}>{etat.label}</Chip>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </Table>
      </Sheet>

      <Modal open={Boolean(editing)} onClose={savingLigne ? undefined : () => setEditing(null)}>
        <ModalDialog sx={{ borderRadius: 'lg', width: 440 }}>
          <Typography level="title-lg">{editing?.nomComplet}</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
            {[editing?.gradeLibelle, editing?.fonctionLibelle].filter(Boolean).join(' · ') || 'Fiche incomplète'}
          </Typography>

          {ficheManquante ? (
            <Stack spacing={1.5} sx={{ mt: 1.5 }}>
              <Typography level="body-sm">
                {editing?.etat === 'sans_fonction'
                  ? 'Complétez la fonction sur la fiche : le montant se calculera ensuite tout seul.'
                  : 'Complétez le grade sur la fiche : le montant se calculera ensuite tout seul.'}
              </Typography>
              {canOpenFiche && editing?.personnelId ? (
                <Button onClick={() => navigate(ROUTES.RH.PERSONNEL_EDIT.replace(':id', editing.personnelId))}>
                  Ouvrir la fiche
                </Button>
              ) : null}
              <Button variant="plain" color="neutral" onClick={() => setEditing(null)}>Fermer</Button>
            </Stack>
          ) : (
            <Stack spacing={1.5} sx={{ mt: 1.5 }}>
              {editing?.etat === 'inactif' ? (
                <Typography level="body-sm" sx={{ color: 'warning.700' }}>
                  Cet agent n’est pas actif. Vous pouvez quand même le payer ce mois-ci si besoin.
                </Typography>
              ) : null}
              {editing?.montantPropose != null ? (
                <Typography level="body-sm">
                  Tarif : <strong>{formatPaieMontant(editing.montantPropose)}</strong>
                </Typography>
              ) : (
                <Typography level="body-sm" sx={{ color: 'warning.700' }}>
                  Pas encore de tarif pour ce grade et cette fonction. Saisissez le montant : il sera retenu pour la suite.
                </Typography>
              )}
              <FormControl orientation="horizontal" sx={{ justifyContent: 'space-between' }}>
                <FormLabel>Payer cet agent</FormLabel>
                <Switch checked={inclus} onChange={(event) => setInclus(event.target.checked)} />
              </FormControl>
              {inclus ? (
                <FormControl>
                  <FormLabel>Montant</FormLabel>
                  <Input
                    value={montant}
                    onChange={(event) => setMontant(event.target.value)}
                    endDecorator={
                      editing?.montantPropose != null && !isPaieProposal(editing, montant) ? (
                        <Button size="sm" variant="plain" onClick={() => setMontant(String(Number(editing.montantPropose)))}>
                          Tarif
                        </Button>
                      ) : null
                    }
                  />
                </FormControl>
              ) : null}
              {inclus && editing && !isPaieProposal(editing, montant) && editing.montantPropose != null ? (
                <FormControl>
                  <FormLabel>Motif (facultatif)</FormLabel>
                  <Input value={motif} onChange={(event) => setMotif(event.target.value)} placeholder="Ex. prime exceptionnelle" />
                </FormControl>
              ) : null}
              <Stack direction="row" spacing={1} justifyContent="flex-end">
                <Button variant="plain" color="neutral" disabled={savingLigne} onClick={() => setEditing(null)}>Annuler</Button>
                <Button loading={savingLigne} onClick={handleSaveLigne}>Enregistrer</Button>
              </Stack>
            </Stack>
          )}
        </ModalDialog>
      </Modal>

      {showTarifs && (periode.baremeApplique?.length ?? 0) > 0 ? (
        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Typography level="title-sm" sx={{ p: 1.5, pb: 0 }}>Tarifs utilisés pour {periode.libelle}</Typography>
          <Typography level="body-xs" sx={{ px: 1.5, pt: 0.5, color: 'neutral.500' }}>
            Conservés tels quels, même si vous changez les tarifs plus tard.
          </Typography>
          <Table sx={{ minWidth: 480 }}>
            <thead>
              <tr>
                <th>Grade</th>
                <th>Fonction</th>
                <th>Montant</th>
              </tr>
            </thead>
            <tbody>
              {periode.baremeApplique.map((row) => (
                <tr key={row.id}>
                  <td>{row.gradeLibelle || '—'}</td>
                  <td>{row.fonctionLibelle || '—'}</td>
                  <td>{formatPaieMontant(row.montant)}</td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>
      ) : null}

      <ConfirmModal
        open={pendingValidate}
        title="Clôturer ce mois ?"
        message="Ensuite, plus de modification. Vous pourrez toujours exporter Excel et PDF."
        confirmLabel="Clôturer"
        color="success"
        loading={confirmLoading}
        onClose={() => setPendingValidate(false)}
        onConfirm={handleValidate}
      />
    </Stack>
  );
}
