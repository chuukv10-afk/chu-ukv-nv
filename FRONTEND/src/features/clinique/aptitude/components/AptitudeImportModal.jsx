import { useEffect, useRef, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Download, GraduationCap, Upload } from 'lucide-react';
import { fetchAptitudeFilieresApi } from '../aptitudeApi.js';

const IMPORT_CATEGORIES = [
  { value: 'A', label: 'A — Tarif standard' },
  { value: 'A0', label: 'A0 — Indigent' },
  { value: 'B', label: 'B — Privé' },
];

export default function AptitudeImportModal({
  open,
  organisations = [],
  services = [],
  yearOptions = [],
  loading = false,
  error = '',
  result = null,
  onClose,
  onDownloadTemplate,
  onImport,
}) {
  const fileRef = useRef(null);
  const [organisationId, setOrganisationId] = useState('');
  const [filiereId, setFiliereId] = useState('');
  const [filieres, setFilieres] = useState([]);
  const [serviceId, setServiceId] = useState('');
  const [annee, setAnnee] = useState(String(yearOptions[0] ?? new Date().getFullYear()));
  const [categorieTarifaire, setCategorieTarifaire] = useState('A');
  const [fileName, setFileName] = useState('');

  const selectedOrg = organisations.find((item) => String(item.id) === String(organisationId));
  const requiresFiliere = Boolean(selectedOrg?.requiresFiliere);

  useEffect(() => {
    if (!open) return;
    setOrganisationId('');
    setFiliereId('');
    setFilieres([]);
    setServiceId('');
    setAnnee(String(yearOptions[0] ?? new Date().getFullYear()));
    setCategorieTarifaire('A');
    setFileName('');
    if (fileRef.current) fileRef.current.value = '';
  }, [open, yearOptions]);

  useEffect(() => {
    if (!organisationId) {
      setFilieres([]);
      setFiliereId('');
      return;
    }
    fetchAptitudeFilieresApi(organisationId)
      .then((items) => {
        setFilieres(items);
        setFiliereId('');
      })
      .catch(() => {
        setFilieres([]);
        setFiliereId('');
      });
  }, [organisationId]);

  const canSubmit = Boolean(fileName && organisationId && serviceId && (!requiresFiliere || filiereId));

  const handleSubmit = (event) => {
    event.preventDefault();
    const file = fileRef.current?.files?.[0];
    if (!file || !canSubmit) return;
    onImport({
      file,
      organisationId: Number(organisationId),
      filiereId: filiereId ? Number(filiereId) : null,
      serviceId: Number(serviceId),
      annee: Number(annee),
      categorieTarifaire,
    });
  };

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog variant="outlined" sx={{ borderRadius: 'xl', maxWidth: 560, p: 0, overflow: 'hidden' }}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{
              width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
              <GraduationCap size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Importer des étudiants</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                Choisissez l’organisation partenaire. Pour une université (UKV), sélectionnez aussi la faculté. Un DPI est créé pour chaque étudiant.
              </Typography>
            </Box>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? (
              <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                {error}
              </Typography>
            ) : null}
            <FormControl required>
              <FormLabel>Organisation partenaire</FormLabel>
              <Select
                placeholder="UKV, autre université, entreprise…"
                value={organisationId}
                onChange={(_, value) => setOrganisationId(value ?? '')}
                disabled={loading}
              >
                {organisations.map((item) => (
                  <Option key={item.id} value={String(item.id)}>
                    {item.code} — {item.libelle} ({item.typeInstitutionLabel || item.typeInstitution})
                  </Option>
                ))}
              </Select>
            </FormControl>
            {requiresFiliere ? (
              <FormControl required>
                <FormLabel>Faculté / filière</FormLabel>
                <Select
                  placeholder="Choisir la faculté de ce fichier"
                  value={filiereId}
                  onChange={(_, value) => setFiliereId(value ?? '')}
                  disabled={loading || !organisationId}
                >
                  {filieres.map((item) => (
                    <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>
                  ))}
                </Select>
                <FormHelperText>
                  Obligatoire pour une université. Créez les filières dans Référentiel → Filières.
                </FormHelperText>
              </FormControl>
            ) : null}
            <FormControl required>
              <FormLabel>Service d’examen</FormLabel>
              <Select
                placeholder="Service qui recevra les brouillons"
                value={serviceId}
                onChange={(_, value) => setServiceId(value ?? '')}
                disabled={loading}
              >
                {services.map((item) => (
                  <Option key={item.id} value={String(item.id)}>{item.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Année</FormLabel>
                <Select value={annee} onChange={(_, value) => setAnnee(value ?? '')} disabled={loading}>
                  {yearOptions.map((year) => (
                    <Option key={year} value={String(year)}>{year}</Option>
                  ))}
                </Select>
              </FormControl>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Catégorie tarifaire</FormLabel>
                <Select
                  value={categorieTarifaire}
                  onChange={(_, value) => setCategorieTarifaire(value ?? 'A')}
                  disabled={loading}
                >
                  {IMPORT_CATEGORIES.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
            <FormControl required>
              <FormLabel>Fichier Excel</FormLabel>
              <input
                ref={fileRef}
                type="file"
                accept=".xlsx,.xls"
                hidden
                onChange={(event) => setFileName(event.target.files?.[0]?.name ?? '')}
              />
              <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                <Button
                  variant="outlined"
                  startDecorator={<Upload size={16} />}
                  onClick={() => fileRef.current?.click()}
                  disabled={loading}
                >
                  Choisir le fichier
                </Button>
                <Button
                  variant="plain"
                  startDecorator={<Download size={16} />}
                  onClick={onDownloadTemplate}
                  disabled={loading}
                >
                  Modèle Excel
                </Button>
              </Stack>
              <FormHelperText>{fileName || 'Colonnes : Code UKV, Nom, Postnom, Prénom, Sexe (M/F), Date de naissance.'}</FormHelperText>
            </FormControl>
            {result ? (
              <Box sx={{ bgcolor: 'neutral.50', p: 1.5, borderRadius: 'md' }}>
                <Typography level="body-sm">
                  {result.createdDpis ?? 0} DPI créé(s) · {result.createdPatients ?? 0} nouvel(le)s étudiant(s) · {result.createdAptitudes ?? 0} brouillon(s) · {result.skippedExisting ?? 0} déjà présent(s)
                </Typography>
                {Array.isArray(result.errors) && result.errors.length > 0 ? (
                  <Stack spacing={0.5} sx={{ mt: 1, maxHeight: 140, overflow: 'auto' }}>
                    {result.errors.slice(0, 20).map((item) => (
                      <Typography key={`${item.row}-${item.message}`} level="body-xs" color="danger">
                        Ligne {item.row} — {item.identite || '—'} : {item.message}
                      </Typography>
                    ))}
                    {result.errors.length > 20 ? (
                      <Typography level="body-xs">… et {result.errors.length - 20} autre(s).</Typography>
                    ) : null}
                  </Stack>
                ) : null}
              </Box>
            ) : null}
            <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ pt: 1 }}>
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Fermer</Button>
              <Button
                type="submit"
                loading={loading}
                disabled={!canSubmit}
                startDecorator={<Upload size={16} />}
              >
                Importer et créer les DPI
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
