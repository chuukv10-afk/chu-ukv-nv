import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Accordion, AccordionDetails, AccordionGroup, AccordionSummary,
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, LinearProgress,
  Option, Select, Stack, Table, Typography,
} from '@mui/joy';
import { ArrowLeft, Check, ChevronDown, ClipboardCheck, Search } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDate, formatDateTime, formatPrix } from '../shared/format.js';
import {
  DEFAULT_INVENTAIRE_DETAIL_PAGE_SIZE,
  INVENTAIRE_DETAIL_PAGE_SIZE_OPTIONS,
  INVENTAIRE_STATUT_COLORS,
  INVENTAIRE_STATUT_LABELS,
  formatPersonnelName,
} from './inventaireConstants.js';
import {
  cloturerInventaireApi,
  compterProduitInventaireApi,
  corrigerProduitInventaireApi,
  exportInventaireApi,
  fetchInventaireApi,
} from './inventairesApi.js';

function parseQty(raw) {
  if (raw === undefined || raw === null || String(raw).trim() === '') return null;
  const value = Number(raw);
  return Number.isFinite(value) ? value : null;
}

function isoDate(value) {
  return value ? String(value).slice(0, 10) : '';
}

function normalizeLotNumero(value) {
  return String(value ?? '').trim().toUpperCase();
}

function uniteLabel(medicament) {
  const unite = medicament?.unite;
  if (!unite) return '';
  return unite.code || unite.libelle || '';
}

function prixDisplay(value) {
  if (value === null || value === undefined || value === '') return '';
  const amount = Number(value);
  return Number.isNaN(amount) ? String(value) : String(amount);
}

export default function InventaireDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canSaisir = hasPermission(PERMISSIONS.PHARMACIE.INVENTAIRE_SAISIR);
  const canCloturer = hasPermission(PERMISSIONS.PHARMACIE.INVENTAIRE_CLOTURER);

  const [inventaire, setInventaire] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [filtre, setFiltre] = useState('a_compter');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_INVENTAIRE_DETAIL_PAGE_SIZE);
  const [drafts, setDrafts] = useState({});
  const [prixDrafts, setPrixDrafts] = useState({});
  const [dateDrafts, setDateDrafts] = useState({});
  const [lotDrafts, setLotDrafts] = useState({});
  const [savingKey, setSavingKey] = useState('');
  const [exportLoading, setExportLoading] = useState(null);
  const [pendingProduit, setPendingProduit] = useState(null);
  const [pendingCloture, setPendingCloture] = useState(false);
  const [confirmLoading, setConfirmLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await fetchInventaireApi(id);
      setInventaire(data);
    } catch (err) {
      setError(err.message || 'Impossible de charger la campagne.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { load(); }, [load]);

  const enCours = inventaire?.statut === 'EN_COURS';
  const canEdit = enCours && canSaisir;
  const produits = inventaire?.produits ?? [];
  const progress = inventaire && inventaire.produitsCount > 0
    ? Math.round((inventaire.produitsComptes / inventaire.produitsCount) * 100)
    : 0;

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return produits.filter((produit) => {
      if (filtre === 'a_compter' && produit.compte) return false;
      if (filtre === 'comptes' && !produit.compte) return false;
      if (!q) return true;
      const med = produit.medicament ?? {};
      return [med.code, med.libelle, med.dci, med.forme, med.dosage, med.unite?.code, med.unite?.libelle]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(q));
    });
  }, [produits, search, filtre]);

  useEffect(() => { setPage(1); }, [search, filtre, limit]);

  const totalPages = filtered.length > 0 ? Math.ceil(filtered.length / limit) : 0;

  useEffect(() => {
    if (totalPages > 0 && page > totalPages) {
      setPage(totalPages);
    }
  }, [page, totalPages]);

  const pageItems = filtered.slice((page - 1) * limit, page * limit);

  const buildLignesPayload = (produit, { includeQty = false } = {}) => (
    (produit.lignes ?? [])
      .filter((ligne) => (includeQty ? !ligne.compte : true))
      .map((ligne) => {
        const payload = { ligneId: ligne.id };
        const dateValue = dateDrafts[ligne.id];
        const lotValue = lotDrafts[ligne.id];
        if (dateValue && isoDate(dateValue) !== isoDate(ligne.datePeremption)) {
          payload.datePeremption = isoDate(dateValue);
        }
        if (lotValue !== undefined && normalizeLotNumero(lotValue) !== normalizeLotNumero(ligne.numeroLot)) {
          payload.numeroLot = normalizeLotNumero(lotValue);
        }
        if (includeQty) {
          const qty = parseQty(drafts[ligne.id]);
          if (qty !== null) payload.quantiteComptee = qty;
        }
        return payload;
      })
      .filter((ligne) => includeQty || Boolean(ligne.datePeremption) || Boolean(ligne.numeroLot))
  );

  const applyInventaire = (data, medicamentId) => {
    setInventaire(data);
    if (medicamentId) {
      setPrixDrafts((prev) => {
        const next = { ...prev };
        delete next[medicamentId];
        return next;
      });
    }
  };

  const handleConfirmProduit = async () => {
    if (!pendingProduit || !inventaire) return;
    const medicamentId = pendingProduit.medicament?.id;
    if (!medicamentId) return;
    setConfirmLoading(true);
    setSavingKey(`p-${medicamentId}`);
    try {
      const prixValue = prixDrafts[medicamentId];
      const payload = {
        lignes: buildLignesPayload(pendingProduit, { includeQty: true }),
      };
      if (prixValue !== undefined && prixDisplay(prixValue) !== prixDisplay(pendingProduit.medicament?.prixVente)) {
        payload.prixVente = String(prixValue).trim();
      }
      const data = await compterProduitInventaireApi(inventaire.id, medicamentId, payload);
      applyInventaire(data, medicamentId);
      setPendingProduit(null);
      showSuccess(`${pendingProduit.medicament?.libelle ?? 'Produit'} marqué comme compté.`);
    } catch (err) {
      showError(err.message || 'Marquage impossible.');
    } finally {
      setConfirmLoading(false);
      setSavingKey('');
    }
  };

  const handleCorrigerPrix = async (produit) => {
    if (!canEdit || !inventaire) return;
    const medicamentId = produit.medicament?.id;
    if (!medicamentId) return;
    const draft = prixDrafts[medicamentId];
    if (draft === undefined || prixDisplay(draft) === prixDisplay(produit.medicament?.prixVente)) return;
    setSavingKey(`prix-${medicamentId}`);
    try {
      const data = await corrigerProduitInventaireApi(inventaire.id, medicamentId, {
        prixVente: String(draft).trim(),
      });
      applyInventaire(data, medicamentId);
    } catch (err) {
      showError(err.message || 'Prix de vente non enregistré.');
    } finally {
      setSavingKey('');
    }
  };

  const handleCorrigerLigne = async (produit, ligne) => {
    if (!canEdit || !inventaire) return;
    const medicamentId = produit.medicament?.id;
    if (!medicamentId) return;
    const payload = { ligneId: ligne.id };
    const lotValue = lotDrafts[ligne.id];
    const dateValue = dateDrafts[ligne.id];
    if (lotValue !== undefined && normalizeLotNumero(lotValue) !== normalizeLotNumero(ligne.numeroLot)) {
      const numeroLot = normalizeLotNumero(lotValue);
      if (!numeroLot) {
        showError('Le numéro de lot est obligatoire.');
        return;
      }
      payload.numeroLot = numeroLot;
    }
    if (dateValue !== undefined && isoDate(dateValue) !== isoDate(ligne.datePeremption)) {
      payload.datePeremption = isoDate(dateValue);
    }
    if (!payload.numeroLot && !payload.datePeremption) return;
    setSavingKey(`ligne-${ligne.id}`);
    try {
      const data = await corrigerProduitInventaireApi(inventaire.id, medicamentId, {
        lignes: [payload],
      });
      applyInventaire(data, medicamentId);
      setLotDrafts((prev) => {
        const next = { ...prev };
        delete next[ligne.id];
        return next;
      });
      setDateDrafts((prev) => {
        const next = { ...prev };
        delete next[ligne.id];
        return next;
      });
    } catch (err) {
      showError(err.message || 'Lot non enregistré.');
    } finally {
      setSavingKey('');
    }
  };

  const handleExport = async (format) => {
    if (!inventaire) return;
    setExportLoading(format);
    try {
      await exportInventaireApi(inventaire.id, format);
      showSuccess(format === 'pdf' ? 'Fiche PDF ouverte dans le navigateur.' : 'Fiche Excel téléchargée.');
    } catch (err) {
      showError(err.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleCloturer = async () => {
    if (!inventaire) return;
    setConfirmLoading(true);
    try {
      const data = await cloturerInventaireApi(inventaire.id);
      setInventaire(data);
      setPendingCloture(false);
      showSuccess('Campagne clôturée.');
    } catch (err) {
      showError(err.message || 'Clôture impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const pendingEcarts = pendingProduit
    ? (pendingProduit.lignes ?? []).filter((ligne) => {
      if (ligne.compte) return false;
      const qty = parseQty(drafts[ligne.id]);
      const cible = qty === null ? ligne.quantiteActuelle : qty;
      return cible !== ligne.quantiteActuelle;
    }).length
    : 0;

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="flex-start">
            <IconButton variant="plain" onClick={() => navigate(ROUTES.PHARMACIE.INVENTAIRES)}>
              <ArrowLeft size={18} />
            </IconButton>
            <ClipboardCheck size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                <Typography level="h2" sx={{ fontWeight: 700 }}>
                  {inventaire?.numero ?? 'Inventaire'}
                </Typography>
                {inventaire ? (
                  <Chip size="sm" variant="soft" color={INVENTAIRE_STATUT_COLORS[inventaire.statut] ?? 'neutral'}>
                    {INVENTAIRE_STATUT_LABELS[inventaire.statut] ?? inventaire.statut}
                  </Chip>
                ) : null}
              </Stack>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                {inventaire?.libelle ?? 'Chargement…'}
              </Typography>
            </Box>
          </Stack>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }}>
            {inventaire ? (
              <ExportButtons onExport={handleExport} loading={exportLoading} size="sm" />
            ) : null}
            {enCours && canCloturer ? (
              <Button
                color="success"
                startDecorator={<Check size={16} />}
                onClick={() => setPendingCloture(true)}
                disabled={(inventaire?.produitsComptes ?? 0) < (inventaire?.produitsCount ?? 0)}
              >
                Clôturer
              </Button>
            ) : null}
          </Stack>
        </Stack>

        {enCours ? (
          <OfflineHint>
            L’inventaire nécessite le serveur : le marquage « compté » écrit le stock tout de suite.
          </OfflineHint>
        ) : null}

        {error ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {error}
          </Typography>
        ) : null}

        {inventaire ? (
          <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
            <Stack spacing={1.25}>
              <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={1}>
                <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
                  Ouvert le {formatDate(inventaire.dateDebut)}
                  {inventaire.dateCloture ? ` · clôturé le ${formatDate(inventaire.dateCloture)}` : ''}
                  {inventaire.cloturePar ? ` par ${formatPersonnelName(inventaire.cloturePar)}` : ''}
                </Typography>
                <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                  {inventaire.produitsComptes}/{inventaire.produitsCount} produits · {inventaire.lignesComptees}/{inventaire.lignesCount} lots
                </Typography>
              </Stack>
              <LinearProgress determinate value={progress} color={progress === 100 ? 'success' : 'primary'} />
              {enCours ? (
                <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                  Saisissez la quantité, le n° de lot, le prix de vente ou la péremption, puis marquez le produit comme compté.
                  Prix, n° de lot et dates sont enregistrés dès que vous quittez le champ.
                </Typography>
              ) : null}
            </Stack>
          </Card>
        ) : null}

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="Code, libellé, DCI…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select value={filtre} onChange={(_, value) => setFiltre(value ?? 'a_compter')} sx={{ minWidth: 200 }}>
              <Option value="a_compter">À compter</Option>
              <Option value="comptes">Déjà comptés</Option>
              <Option value="tous">Tous les produits</Option>
            </Select>
          </Stack>
        </Card>

        {loading ? (
          <Typography level="body-sm">Chargement…</Typography>
        ) : filtered.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            {produits.length === 0 ? 'Aucun lot dans cette campagne.' : 'Aucun produit pour ce filtre.'}
          </Typography>
        ) : (
          <>
            <AccordionGroup sx={{ gap: 1, '& .MuiAccordion-root': { borderRadius: 'lg', border: '1px solid', borderColor: 'neutral.outlinedBorder' } }}>
              {pageItems.map((produit) => {
                const med = produit.medicament ?? {};
                const medicamentId = med.id;
                const unite = uniteLabel(med);
                const prixValue = prixDrafts[medicamentId] ?? prixDisplay(med.prixVente);
                return (
                  <Accordion key={medicamentId}>
                    <AccordionSummary indicator={<ChevronDown size={18} />}>
                      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }} justifyContent="space-between" sx={{ width: '100%', pr: 1 }}>
                        <Box>
                          <Typography level="title-sm" sx={{ fontWeight: 700 }}>{med.libelle}</Typography>
                          <Typography level="body-xs" sx={{ color: 'neutral.500', fontFamily: 'monospace' }}>
                            {med.code}{med.dosage ? ` · ${med.dosage}` : ''}{med.forme ? ` · ${med.forme}` : ''}{unite ? ` · ${unite}` : ''} · {formatPrix(med.prixVente)}
                          </Typography>
                        </Box>
                        <Stack direction="row" spacing={1} alignItems="center">
                          <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                            {produit.lignesComptees}/{produit.lignesCount} lots · SI {produit.quantiteSysteme}{unite ? ` ${unite}` : ''} · actuel {produit.quantiteActuelle}{unite ? ` ${unite}` : ''}
                          </Typography>
                          <Chip size="sm" variant="soft" color={produit.compte ? 'success' : 'warning'}>
                            {produit.compte ? 'Compté' : 'À compter'}
                          </Chip>
                        </Stack>
                      </Stack>
                    </AccordionSummary>
                    <AccordionDetails>
                      <Stack spacing={1.5}>
                        <FormControl sx={{ maxWidth: 220 }} onClick={(event) => event.stopPropagation()}>
                          <FormLabel>Prix de vente</FormLabel>
                          <Input
                            type="number"
                            size="sm"
                            value={prixValue}
                            disabled={!canEdit}
                            onChange={(event) => setPrixDrafts((prev) => ({ ...prev, [medicamentId]: event.target.value }))}
                            onBlur={() => handleCorrigerPrix(produit)}
                            slotProps={{ input: { min: 0, step: '0.01' } }}
                          />
                        </FormControl>
                        <Table sx={{ minWidth: 860 }}>
                          <thead>
                            <tr>
                              <th>Lot</th>
                              <th>Péremption</th>
                              <th>Qté à l’ouverture{unite ? ` (${unite})` : ''}</th>
                              <th>Qté actuelle{unite ? ` (${unite})` : ''}</th>
                              <th>Qté comptée{unite ? ` (${unite})` : ''}</th>
                              <th>Écart</th>
                            </tr>
                          </thead>
                          <tbody>
                            {(produit.lignes ?? []).map((ligne) => {
                              const draft = drafts[ligne.id];
                              const cible = ligne.compte
                                ? ligne.quantiteComptee
                                : (parseQty(draft) ?? ligne.quantiteActuelle);
                              const ecart = ligne.compte
                                ? ligne.ecart
                                : (cible - ligne.quantiteSysteme);
                              const dateValue = dateDrafts[ligne.id] ?? isoDate(ligne.datePeremption);
                              const lotValue = lotDrafts[ligne.id] ?? (ligne.numeroLot ?? '');
                              return (
                                <tr key={ligne.id}>
                                  <td>
                                    {canEdit ? (
                                      <Stack spacing={0.5}>
                                        <Input
                                          size="sm"
                                          value={lotValue}
                                          disabled={savingKey === `ligne-${ligne.id}`}
                                          onChange={(event) => setLotDrafts((prev) => ({ ...prev, [ligne.id]: event.target.value }))}
                                          onBlur={() => handleCorrigerLigne(produit, ligne)}
                                          slotProps={{ input: { maxLength: 40, style: { textTransform: 'uppercase', fontFamily: 'monospace' } } }}
                                          sx={{ maxWidth: 160 }}
                                        />
                                        {ligne.statutLot === 'PERIME' ? (
                                          <Chip size="sm" variant="soft" color="danger">Périmé</Chip>
                                        ) : null}
                                      </Stack>
                                    ) : (
                                      <Stack spacing={0.25}>
                                        <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                                          {ligne.numeroLot}
                                        </Typography>
                                        {ligne.statutLot === 'PERIME' ? (
                                          <Chip size="sm" variant="soft" color="danger">Périmé</Chip>
                                        ) : null}
                                      </Stack>
                                    )}
                                  </td>
                                  <td>
                                    {canEdit ? (
                                      <Input
                                        type="date"
                                        size="sm"
                                        value={dateValue}
                                        disabled={savingKey === `ligne-${ligne.id}`}
                                        onChange={(event) => setDateDrafts((prev) => ({ ...prev, [ligne.id]: event.target.value }))}
                                        onBlur={() => handleCorrigerLigne(produit, ligne)}
                                        sx={{ maxWidth: 170 }}
                                      />
                                    ) : formatDate(ligne.datePeremption)}
                                  </td>
                                  <td>{ligne.quantiteSysteme}</td>
                                  <td>{ligne.quantiteActuelle}</td>
                                  <td>
                                    {ligne.compte ? (
                                      <Stack spacing={0.25}>
                                        <Typography level="body-sm" sx={{ fontWeight: 600 }}>{ligne.quantiteComptee}</Typography>
                                        <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                                          {formatDateTime(ligne.compteAt)} · {formatPersonnelName(ligne.comptePar)}
                                        </Typography>
                                      </Stack>
                                    ) : (
                                      <Input
                                        type="number"
                                        size="sm"
                                        placeholder={String(ligne.quantiteActuelle)}
                                        value={draft ?? ''}
                                        disabled={!canEdit}
                                        onChange={(event) => setDrafts((prev) => ({ ...prev, [ligne.id]: event.target.value }))}
                                        slotProps={{ input: { min: 0 } }}
                                        sx={{ maxWidth: 120 }}
                                      />
                                    )}
                                  </td>
                                  <td>
                                    <Typography
                                      level="body-sm"
                                      sx={{ fontWeight: 600, color: ecart === 0 ? 'neutral.600' : (ecart > 0 ? 'success.600' : 'danger.600') }}
                                    >
                                      {ecart > 0 ? `+${ecart}` : ecart}
                                    </Typography>
                                  </td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </Table>
                        {canEdit && !produit.compte ? (
                          <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <Button
                              startDecorator={<Check size={16} />}
                              loading={savingKey === `p-${medicamentId}`}
                              onClick={() => setPendingProduit(produit)}
                            >
                              Marquer comme compté
                            </Button>
                          </Box>
                        ) : null}
                      </Stack>
                    </AccordionDetails>
                  </Accordion>
                );
              })}
            </AccordionGroup>
            <AppPagination
              page={page}
              totalPages={totalPages}
              total={filtered.length}
              limit={limit}
              limitOptions={INVENTAIRE_DETAIL_PAGE_SIZE_OPTIONS}
              onPageChange={setPage}
              onLimitChange={(value) => { if (value) setLimit(value); }}
            />
          </>
        )}
      </Stack>

      <ConfirmModal
        open={Boolean(pendingProduit)}
        title="Marquer comme compté"
        message={pendingProduit
          ? (pendingEcarts > 0
            ? `Confirmer le comptage de « ${pendingProduit.medicament?.libelle ?? ''} » ? ${pendingEcarts} lot(s) ont un écart : le stock sera ajusté immédiatement.`
            : `Marquer « ${pendingProduit.medicament?.libelle ?? ''} » comme déjà compté ? Les lots sans quantité saisie conservent le stock actuel, sans mouvement.`)
          : ''}
        confirmLabel="Marquer comme compté"
        color="primary"
        loading={confirmLoading && Boolean(pendingProduit)}
        onClose={() => setPendingProduit(null)}
        onConfirm={handleConfirmProduit}
      />
      <ConfirmModal
        open={pendingCloture}
        title="Clôturer la campagne"
        message="Tous les produits doivent être marqués comme comptés. La campagne ne pourra plus être modifiée."
        confirmLabel="Clôturer"
        color="success"
        loading={confirmLoading && pendingCloture}
        onClose={() => setPendingCloture(false)}
        onConfirm={handleCloturer}
      />
    </Box>
  );
}
