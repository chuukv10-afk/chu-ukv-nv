import { useEffect, useMemo, useRef, useState } from 'react';
import { Navigate } from 'react-router-dom';
import {
  Box, Button, Card, Checkbox, Chip, Input, Modal, ModalDialog, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { AlertTriangle, Database, Download, Eraser, RefreshCw, Upload } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { isDesktopApp } from '../../../offline/desktop.js';
import { LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import {
  exportDatabaseTablesApi,
  fetchDatabaseOverviewApi,
  importDatabaseSqlApi,
  truncateDatabaseTablesApi,
} from './databaseApi.js';

export default function DatabaseAdminPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canManage = hasPermission(PERMISSIONS.ADMIN.DATABASE_MANAGE);
  const canExport = hasPermission(PERMISSIONS.ADMIN.DATABASE_EXPORT);
  const canTruncate = hasPermission(PERMISSIONS.ADMIN.DATABASE_TRUNCATE);
  const canImport = hasPermission(PERMISSIONS.ADMIN.DATABASE_IMPORT);
  const fileRef = useRef(null);

  const [overview, setOverview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState([]);
  const [format, setFormat] = useState('sql');
  const [busy, setBusy] = useState('');
  const [truncateOpen, setTruncateOpen] = useState(false);
  const [confirmWord, setConfirmWord] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const data = await fetchDatabaseOverviewApi();
      setOverview(data);
      setSelected((current) => current.filter((name) => (data.tables || []).some((table) => table.name === name)));
    } catch (error) {
      showError(error.message || 'Impossible de lire la base.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (canManage) load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canManage]);

  const tables = overview?.tables || [];
  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) return tables;
    return tables.filter((table) => table.name.toLowerCase().includes(needle));
  }, [tables, search]);

  const selectable = filtered.filter((table) => !table.protected);
  const allFilteredSelected = selectable.length > 0 && selectable.every((table) => selected.includes(table.name));

  const toggleAll = () => {
    if (allFilteredSelected) {
      setSelected((current) => current.filter((name) => !selectable.some((table) => table.name === name)));
      return;
    }
    setSelected((current) => [...new Set([...current, ...selectable.map((table) => table.name)])]);
  };

  const toggleOne = (name, protectedTable) => {
    if (protectedTable) return;
    setSelected((current) => (
      current.includes(name) ? current.filter((item) => item !== name) : [...current, name]
    ));
  };

  const handleExport = async (all = false) => {
    const tablesToExport = all ? [] : selected;
    if (!all && tablesToExport.length === 0) {
      showError('Sélectionnez au moins une table, ou exportez toute la base.');
      return;
    }
    setBusy('export');
    try {
      await exportDatabaseTablesApi(tablesToExport, format);
      showSuccess(all ? 'Export de toute la base lancé.' : `Export de ${tablesToExport.length} table(s) lancé.`);
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setBusy('');
    }
  };

  const handleTruncate = async () => {
    if (confirmWord.trim().toUpperCase() !== 'VIDER') return;
    setBusy('truncate');
    try {
      const result = await truncateDatabaseTablesApi(selected);
      showSuccess(`${result.truncated?.length || selected.length} table(s) vidée(s).`);
      setTruncateOpen(false);
      setConfirmWord('');
      setSelected([]);
      await load();
    } catch (error) {
      showError(error.message || 'Impossible de vider les tables.');
    } finally {
      setBusy('');
    }
  };

  const handleImport = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    setBusy('import');
    try {
      const result = await importDatabaseSqlApi(file);
      showSuccess(`Import terminé (${result.executed} instruction(s)${result.skipped ? `, ${result.skipped} ignorée(s)` : ''}).`);
      await load();
    } catch (error) {
      showError(error.message || 'Import impossible.');
    } finally {
      setBusy('');
    }
  };

  if (isDesktopApp()) {
    return <Navigate to={ROUTES.DASHBOARD} replace />;
  }

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Database size={28} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Base de données</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                Administration MySQL du serveur — hors-ligne / Electron indisponible. Faites une sauvegarde SQL avant de vider ou d’importer.
              </Typography>
            </Box>
          </Stack>
          <Button variant="outlined" startDecorator={<RefreshCw size={16} />} loading={loading} onClick={load}>
            Actualiser
          </Button>
        </Stack>

        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
          <Card variant="outlined" sx={{ flex: 1, p: 2 }}>
            <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Base</Typography>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{overview?.database || '—'}</Typography>
          </Card>
          <Card variant="outlined" sx={{ flex: 1, p: 2 }}>
            <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Tables</Typography>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{overview?.tableCount ?? '—'}</Typography>
          </Card>
          <Card variant="outlined" sx={{ flex: 1, p: 2 }}>
            <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Taille</Typography>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{overview?.sizeLabel || '—'}</Typography>
          </Card>
          <Card variant="outlined" sx={{ flex: 1, p: 2 }}>
            <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Sélection</Typography>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{selected.length}</Typography>
          </Card>
        </Stack>

        <Card variant="outlined" sx={{ p: 2 }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} alignItems={{ md: 'center' }} flexWrap="wrap">
            <Input
              placeholder="Filtrer une table…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ minWidth: 220, flex: 1 }}
            />
            {canExport ? (
              <>
                <Select value={format} onChange={(_, value) => setFormat(value ?? 'sql')} sx={{ minWidth: 140 }}>
                  <Option value="sql">Export .SQL</Option>
                  <Option value="xlsx">Export Excel</Option>
                </Select>
                <Button
                  variant="outlined"
                  startDecorator={<Download size={16} />}
                  loading={busy === 'export'}
                  disabled={selected.length === 0}
                  onClick={() => handleExport(false)}
                >
                  Exporter la sélection
                </Button>
                <Button
                  startDecorator={<Download size={16} />}
                  loading={busy === 'export'}
                  onClick={() => handleExport(true)}
                >
                  Toute la base
                </Button>
              </>
            ) : null}
            {canTruncate ? (
              <Button
                color="danger"
                variant="outlined"
                startDecorator={<Eraser size={16} />}
                disabled={selected.length === 0}
                onClick={() => { setConfirmWord(''); setTruncateOpen(true); }}
              >
                Vider la sélection
              </Button>
            ) : null}
            {canImport ? (
              <>
                <input ref={fileRef} type="file" accept=".sql" hidden onChange={handleImport} />
                <Button
                  variant="outlined"
                  color="warning"
                  startDecorator={<Upload size={16} />}
                  loading={busy === 'import'}
                  onClick={() => fileRef.current?.click()}
                >
                  Importer un .SQL
                </Button>
              </>
            ) : null}
          </Stack>
          <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 1 }}>
            Excel : max 10 000 lignes par table. SQL : structure + données. L’import n’accepte pas DROP DATABASE / GRANT.
            La table des migrations est protégée.
          </Typography>
        </Card>

        <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto', maxHeight: '62vh' }}>
          <Table stickyHeader size="sm">
            <thead>
              <tr>
                <th style={{ width: 44 }}>
                  <Checkbox
                    checked={allFilteredSelected}
                    indeterminate={selected.length > 0 && !allFilteredSelected}
                    onChange={toggleAll}
                    disabled={selectable.length === 0}
                  />
                </th>
                <th>Table</th>
                <th>Lignes (approx.)</th>
                <th>Taille</th>
                <th>Moteur</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((table) => (
                <tr key={table.name}>
                  <td>
                    <Checkbox
                      checked={selected.includes(table.name)}
                      disabled={table.protected}
                      onChange={() => toggleOne(table.name, table.protected)}
                    />
                  </td>
                  <td>
                    <Stack direction="row" spacing={1} alignItems="center">
                      <Typography level="body-sm" sx={{ fontFamily: 'ui-monospace, monospace' }}>{table.name}</Typography>
                      {table.protected ? <Chip size="sm" color="warning" variant="soft">protégée</Chip> : null}
                    </Stack>
                  </td>
                  <td>{table.rowEstimate?.toLocaleString('fr-FR')}</td>
                  <td>{table.sizeLabel}</td>
                  <td>{table.engine || '—'}</td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>
      </Stack>

      <Modal open={truncateOpen} onClose={busy ? undefined : () => setTruncateOpen(false)}>
        <ModalDialog sx={{ borderRadius: 'xl', maxWidth: 460, width: '100%' }}>
          <Stack spacing={2}>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <Box sx={{ width: 44, height: 44, borderRadius: 'md', bgcolor: 'danger.50', color: 'danger.500', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <AlertTriangle size={20} />
              </Box>
              <Box>
                <Typography level="title-lg" sx={{ fontWeight: 700 }}>Vider les tables sélectionnées ?</Typography>
                <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
                  Irréversible. {selected.length} table(s) seront vidées. Tapez VIDER pour confirmer.
                </Typography>
              </Box>
            </Stack>
            <Input
              placeholder="Tapez VIDER"
              value={confirmWord}
              onChange={(event) => setConfirmWord(event.target.value)}
            />
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button variant="plain" color="neutral" disabled={Boolean(busy)} onClick={() => setTruncateOpen(false)}>Annuler</Button>
              <Button
                color="danger"
                loading={busy === 'truncate'}
                disabled={confirmWord.trim().toUpperCase() !== 'VIDER'}
                onClick={handleTruncate}
              >
                Vider
              </Button>
            </Stack>
          </Stack>
        </ModalDialog>
      </Modal>
    </Box>
  );
}
