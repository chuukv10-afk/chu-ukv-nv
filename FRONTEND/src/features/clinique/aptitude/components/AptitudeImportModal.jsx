import { useEffect, useRef, useState } from 'react';
import {
  Box, Button, Chip, FormControl, FormLabel, Modal, ModalDialog, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Download, GraduationCap, Upload } from 'lucide-react';
import { fetchAptitudeFilieresApi, previewAptitudeImportApi } from '../aptitudeApi.js';

const IMPORT_CATEGORIES = [
  { value: 'A', label: 'A' },
  { value: 'A0', label: 'A0' },
  { value: 'B', label: 'B' },
];

export default function AptitudeImportModal({
  open,
  organisations = [],
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
  const [annee, setAnnee] = useState(String(yearOptions[0] ?? new Date().getFullYear()));
  const [categorieTarifaire, setCategorieTarifaire] = useState('A');
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState('');

  const selectedOrg = organisations.find((item) => String(item.id) === String(organisationId));
  const requiresFiliere = Boolean(selectedOrg?.requiresFiliere);

  useEffect(() => {
    if (!open) return;
    setOrganisationId('');
    setFiliereId('');
    setFilieres([]);
    setAnnee(String(yearOptions[0] ?? new Date().getFullYear()));
    setCategorieTarifaire('A');
    setFile(null);
    setPreview(null);
    setPreviewError('');
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

  const loadPreview = async (nextFile) => {
    if (!nextFile) {
      setPreview(null);
      setPreviewError('');
      return;
    }
    setPreviewLoading(true);
    setPreviewError('');
    try {
      const data = await previewAptitudeImportApi(nextFile);
      setPreview(data);
    } catch (err) {
      setPreview(null);
      setPreviewError(err.message || 'Prévisualisation impossible.');
    } finally {
      setPreviewLoading(false);
    }
  };

  const handleFileChange = (event) => {
    const nextFile = event.target.files?.[0] ?? null;
    setFile(nextFile);
    loadPreview(nextFile);
  };

  const canSubmit = Boolean(file && organisationId && (!requiresFiliere || filiereId) && preview?.valid > 0);

  const handleSubmit = (event) => {
    event.preventDefault();
    if (!file || !canSubmit) return;
    onImport({
      file,
      organisationId: Number(organisationId),
      filiereId: filiereId ? Number(filiereId) : null,
      annee: Number(annee),
      categorieTarifaire,
    });
  };

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog
        variant="outlined"
        sx={{
          borderRadius: 'xl',
          p: 0,
          width: { xs: 'calc(100vw - 16px)', sm: '100%' },
          maxWidth: 720,
          maxHeight: { xs: '92vh', sm: '90vh' },
          mx: { xs: 1, sm: 'auto' },
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <Box sx={{ px: 3, py: 2, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{
              width: 36, height: 36, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
              <GraduationCap size={18} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>Importer des étudiants</Typography>
          </Stack>
        </Box>

        <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', flexDirection: 'column', minHeight: 0, flex: 1 }}>
          <Box sx={{ px: 3, py: 2, overflow: 'auto', flex: 1, minHeight: 0 }}>
            <Stack spacing={1.5}>
              {error ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error}
                </Typography>
              ) : null}
              <FormControl required>
                <FormLabel>Organisation</FormLabel>
                <Select
                  placeholder="Choisir…"
                  value={organisationId}
                  onChange={(_, value) => setOrganisationId(value ?? '')}
                  disabled={loading}
                >
                  {organisations.map((item) => (
                    <Option key={item.id} value={String(item.id)}>
                      {item.code} — {item.libelle}
                    </Option>
                  ))}
                </Select>
              </FormControl>
              {requiresFiliere ? (
                <FormControl required>
                  <FormLabel>Faculté</FormLabel>
                  <Select
                    placeholder="Choisir…"
                    value={filiereId}
                    onChange={(_, value) => setFiliereId(value ?? '')}
                    disabled={loading || !organisationId}
                  >
                    {filieres.map((item) => (
                      <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
              ) : null}
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Année</FormLabel>
                  <Select value={annee} onChange={(_, value) => setAnnee(value ?? '')} disabled={loading}>
                    {yearOptions.map((year) => (
                      <Option key={year} value={String(year)}>{year}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Catégorie</FormLabel>
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
                  onChange={handleFileChange}
                />
                <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                  <Button
                    variant="outlined"
                    startDecorator={<Upload size={16} />}
                    onClick={() => fileRef.current?.click()}
                    disabled={loading || previewLoading}
                  >
                    Choisir
                  </Button>
                  <Button
                    variant="plain"
                    startDecorator={<Download size={16} />}
                    onClick={onDownloadTemplate}
                    disabled={loading}
                  >
                    Modèle
                  </Button>
                  {file ? (
                    <Typography level="body-sm" sx={{ color: 'neutral.600' }}>{file.name}</Typography>
                  ) : null}
                </Stack>
              </FormControl>

              {previewError ? (
                <Typography level="body-sm" color="danger">{previewError}</Typography>
              ) : null}

              {previewLoading ? (
                <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Lecture du fichier…</Typography>
              ) : null}

              {preview ? (
                <Box>
                  <Stack direction="row" spacing={1} sx={{ mb: 1 }} flexWrap="wrap" useFlexGap>
                    <Chip size="sm" variant="soft">{preview.total} ligne(s)</Chip>
                    <Chip size="sm" color="success" variant="soft">{preview.valid} valide(s)</Chip>
                    {preview.invalid > 0 ? (
                      <Chip size="sm" color="danger" variant="soft">{preview.invalid} erreur(s)</Chip>
                    ) : null}
                  </Stack>
                  <Sheet variant="outlined" sx={{ borderRadius: 'md', maxHeight: 240, overflow: 'auto' }}>
                    <Table size="sm" stickyHeader hoverRow>
                      <thead>
                        <tr>
                          <th style={{ width: 48 }}>Ligne</th>
                          <th>Code</th>
                          <th>Nom</th>
                          <th>Postnom</th>
                          <th>Prénom</th>
                          <th>Sexe</th>
                          <th>Naissance</th>
                          <th>État</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(preview.rows ?? []).map((item) => (
                          <tr key={item.row}>
                            <td>{item.row}</td>
                            <td>{item.codeUkv || '—'}</td>
                            <td>{item.nom || '—'}</td>
                            <td>{item.postNom || '—'}</td>
                            <td>{item.prenom || '—'}</td>
                            <td>{item.sexe || '—'}</td>
                            <td>{item.dateNaissance || '—'}</td>
                            <td>
                              {item.ok ? (
                                <Chip size="sm" color="success" variant="soft">OK</Chip>
                              ) : (
                                <Typography level="body-xs" color="danger">{item.message}</Typography>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  </Sheet>
                </Box>
              ) : null}

              {result ? (
                <Typography level="body-sm">
                  {result.createdDpis ?? 0} DPI · {result.createdPatients ?? 0} nouveau(x) · {result.updatedPatients ?? 0} mis à jour
                  {Array.isArray(result.errors) && result.errors.length ? ` · ${result.errors.length} erreur(s)` : ''}
                </Typography>
              ) : null}
            </Stack>
          </Box>

          <Stack
            direction="row"
            spacing={1.5}
            justifyContent="flex-end"
            sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0 }}
          >
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Fermer</Button>
            <Button type="submit" loading={loading} disabled={!canSubmit}>
              Importer
            </Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
