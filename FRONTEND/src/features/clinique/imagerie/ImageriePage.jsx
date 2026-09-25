import { useCallback, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select,
  Sheet, Stack, Tab, TabList, TabPanel, Tabs, Table, Textarea, Typography,
} from '@mui/joy';
import { Plus, ScanLine, Search, Stethoscope } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchExamensApi } from '../examens/examensApi.js';
import { fetchPatientsApi } from '../../patient/patients/patientsApi.js';
import { formatDateTime, formatPatientName } from '../../pharmacie/shared/format.js';
import {
  DEFAULT_IMAGERIE_PAGE_SIZE,
  IMAGERIE_INTERPRET_STATUTS,
  IMAGERIE_PAGE_SIZE_OPTIONS,
  IMAGERIE_STATUT_COLORS,
  IMAGERIE_STATUT_LABELS,
  IMAGERIE_STATUTS,
  imagerieDetailPath,
  medecinLabel,
} from './imagerieConstants.js';
import { createEtudeImagerieApi, fetchEtudesImagerieApi, fetchMedecinsImagerieApi } from './imagerieApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_IMAGERIE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function ImageriePage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { hasPermission, hasAnyPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_CREATE);
  const canSeeInterpretation = hasAnyPermission([
    PERMISSIONS.CLINIQUE.IMAGERIE_INTERPRET,
    PERMISSIONS.CLINIQUE.IMAGERIE_VALIDATE,
    PERMISSIONS.CLINIQUE.IMAGERIE_EXPORT,
  ]);
  const [mainTab, setMainTab] = useState('images');

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_IMAGERIE_PAGE_SIZE);
  const [createOpen, setCreateOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [patientQuery, setPatientQuery] = useState('');
  const [patients, setPatients] = useState([]);
  const [examens, setExamens] = useState([]);
  const [medecins, setMedecins] = useState([]);
  const [form, setForm] = useState({ patientId: '', examenId: '', indication: '', but: '', demandeParId: '' });

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, statut, limit, mainTab]);

  useEffect(() => {
    if (canCreate && String(location.pathname).includes('/nouveau')) {
      setCreateOpen(true);
    }
  }, [canCreate, location.pathname]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchEtudesImagerieApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
        statuts: !statut && mainTab === 'interpretation' ? IMAGERIE_INTERPRET_STATUTS : undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setListError(err.message || 'Impossible de charger le journal d\'imagerie.');
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, limit, page, statut, mainTab]);

  useEffect(() => { load(page); }, [load, page]);

  useEffect(() => {
    if (!createOpen) return;
    fetchExamensApi({ page: 1, limit: 100, imagerie: true })
      .then((result) => setExamens(result.items || []))
      .catch(() => setExamens([]));
    fetchMedecinsImagerieApi()
      .then(setMedecins)
      .catch(() => setMedecins([]));
  }, [createOpen]);

  useEffect(() => {
    if (!createOpen || patientQuery.trim().length < 2) {
      return undefined;
    }
    const timer = window.setTimeout(() => {
      fetchPatientsApi({ page: 1, limit: 8, search: patientQuery.trim() })
        .then((result) => setPatients(result.items || []))
        .catch(() => setPatients([]));
    }, 300);
    return () => window.clearTimeout(timer);
  }, [createOpen, patientQuery]);

  const handleCreate = async () => {
    if (!form.patientId || !form.examenId) {
      showError('Sélectionnez le patient et l\'examen d\'imagerie.');
      return;
    }
    if (!form.demandeParId) {
      showError('Indiquez le médecin ayant demandé l\'examen.');
      return;
    }
    setSaving(true);
    try {
      const created = await createEtudeImagerieApi({
        patientId: form.patientId,
        examenId: Number(form.examenId),
        indication: form.indication || null,
        but: form.but || null,
        demandeParId: form.demandeParId,
      });
      showSuccess('Étude créée. Vous pouvez maintenant charger les images.');
      setCreateOpen(false);
      navigate(imagerieDetailPath(created.id));
    } catch (err) {
      showError(err.message || 'Création impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction="row" justifyContent="space-between" alignItems="flex-start" flexWrap="wrap" gap={1}>
        <Box>
          <Typography level="h3" startDecorator={<ScanLine size={22} />}>Imagerie</Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Journal des images médicales. L'interprétation est un onglet réservé aux médecins autorisés.
          </Typography>
        </Box>
        {canCreate && mainTab === 'images' ? (
          <Button startDecorator={<Plus size={16} />} onClick={() => setCreateOpen(true)} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>
            Nouvelle étude
          </Button>
        ) : null}
      </Stack>

      <Tabs
        value={canSeeInterpretation ? mainTab : 'images'}
        onChange={(_, value) => {
          setMainTab(value || 'images');
          setStatut('');
        }}
      >
        <TabList>
          <Tab value="images"><ScanLine size={16} style={{ marginRight: 6 }} />Images</Tab>
          {canSeeInterpretation ? (
            <Tab value="interpretation"><Stethoscope size={16} style={{ marginRight: 6 }} />Interprétation</Tab>
          ) : null}
        </TabList>
        <TabPanel value={mainTab} sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ mb: 1.5 }}>
              <Input
                placeholder="Rechercher n°, patient, examen…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                startDecorator={<Search size={16} />}
                sx={{ flex: 1 }}
              />
              <Select value={statut} onChange={(_, value) => setStatut(value || '')} placeholder="Statut" sx={{ minWidth: 180 }}>
                <Option value="">{mainTab === 'interpretation' ? 'À interpréter / interprétés' : 'Tous les statuts'}</Option>
                {(mainTab === 'interpretation'
                  ? IMAGERIE_STATUTS.filter((item) => ['IMAGES', 'INTERPRETE', 'VALIDE'].includes(item.value))
                  : IMAGERIE_STATUTS
                ).map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
            </Stack>
            {listError ? <Typography color="danger">{listError}</Typography> : null}
            <Sheet variant="outlined" sx={{ overflow: 'auto', borderRadius: 'sm' }}>
              <Table stickyHeader>
                <thead>
                  <tr>
                    <th>N°</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Examen</th>
                    <th>Demandeur</th>
                    <th>Images</th>
                    <th>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={7}>Chargement…</td></tr>
                  ) : items.length === 0 ? (
                    <tr><td colSpan={7}>{mainTab === 'interpretation' ? 'Aucune étude à interpréter.' : 'Aucune étude d\'imagerie.'}</td></tr>
                  ) : items.map((item) => (
                    <tr
                      key={item.id}
                      onClick={() => navigate(imagerieDetailPath(item.id, mainTab === 'interpretation' ? 'interpretation' : 'images'))}
                      style={{ cursor: 'pointer' }}
                    >
                      <td>{item.numero}</td>
                      <td>{formatDateTime(item.createdAt)}</td>
                      <td>{item.patient?.fullName || formatPatientName(item.patient)}</td>
                      <td>{item.examen?.libelle || '—'}</td>
                      <td>{medecinLabel(item.demandePar)}</td>
                      <td>{item.imagesCount ?? 0}</td>
                      <td>
                        <Chip size="sm" color={IMAGERIE_STATUT_COLORS[item.statut] || 'neutral'} variant="soft">
                          {IMAGERIE_STATUT_LABELS[item.statut] || item.statut}
                        </Chip>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </Sheet>
            <AppPagination
              page={pagination.page}
              limit={limit}
              total={pagination.total}
              totalPages={pagination.totalPages}
              limitOptions={IMAGERIE_PAGE_SIZE_OPTIONS}
              onPageChange={setPage}
              onLimitChange={setLimit}
            />
          </Card>
        </TabPanel>
      </Tabs>

      <Modal open={createOpen} onClose={() => setCreateOpen(false)}>
        <ModalDialog sx={{ width: 520, maxWidth: '95vw', maxHeight: '90vh', overflow: 'auto' }}>
          <Typography level="h4">Nouvelle étude d'imagerie</Typography>
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <FormControl>
              <FormLabel>Patient (déjà enregistré)</FormLabel>
              <Input placeholder="Rechercher dans Gestion des Patients…" value={patientQuery} onChange={(e) => setPatientQuery(e.target.value)} />
              <Select
                value={form.patientId}
                onChange={(_, value) => setForm((current) => ({ ...current, patientId: value || '' }))}
                placeholder="Tapez au moins 2 lettres, puis sélectionnez"
              >
                {patients.map((patient) => (
                  <Option key={patient.id} value={patient.id}>{formatPatientName(patient)}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Examen</FormLabel>
              <Select
                value={form.examenId}
                onChange={(_, value) => setForm((current) => ({ ...current, examenId: value || '' }))}
                placeholder="Radio, echo, scanner…"
              >
                {examens.map((examen) => (
                  <Option key={examen.id} value={String(examen.id)}>{examen.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Médecin demandeur</FormLabel>
              <Select
                value={form.demandeParId}
                onChange={(_, value) => setForm((current) => ({ ...current, demandeParId: value || '' }))}
                placeholder="Médecin ayant demandé l'examen"
              >
                {medecins.map((medecin) => (
                  <Option key={medecin.id} value={medecin.id}>{medecinLabel(medecin)}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>But</FormLabel>
              <Input
                value={form.but}
                onChange={(e) => setForm((current) => ({ ...current, but: e.target.value }))}
                placeholder="But de l'examen"
              />
            </FormControl>
            <FormControl>
              <FormLabel>Renseignements cliniques</FormLabel>
              <Textarea minRows={3} value={form.indication} onChange={(e) => setForm((current) => ({ ...current, indication: e.target.value }))} />
            </FormControl>
            <Stack direction="row" justifyContent="flex-end" spacing={1}>
              <Button variant="plain" color="neutral" onClick={() => setCreateOpen(false)}>Annuler</Button>
              <Button loading={saving} onClick={handleCreate} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>Créer</Button>
            </Stack>
          </Stack>
        </ModalDialog>
      </Modal>
    </Stack>
  );
}
