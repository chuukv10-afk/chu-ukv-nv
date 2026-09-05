import { useMemo, useState } from 'react';
import {
  Box,
  Checkbox,
  Chip,
  Divider,
  Input,
  Stack,
  Typography,
} from '@mui/joy';
import { Search } from 'lucide-react';
import { PERMISSION_MODULE_LABELS } from '../../admin/permissions/permissionConstants.js';

function groupByModule(permissions) {
  return permissions.reduce((groups, permission) => {
    const module = permission.module ?? 'AUTRE';
    if (!groups[module]) {
      groups[module] = [];
    }
    groups[module].push(permission);
    return groups;
  }, {});
}

export default function PermissionPickerPanel({
  permissions = [],
  selectedIds,
  onSelectedIdsChange,
  loading = false,
  maxHeight = 360,
  fillHeight = false,
}) {
  const [search, setSearch] = useState('');

  const filteredPermissions = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) {
      return permissions;
    }

    return permissions.filter(
      (permission) =>
        permission.code?.toLowerCase().includes(query) ||
        permission.libelle?.toLowerCase().includes(query) ||
        PERMISSION_MODULE_LABELS[permission.module]?.toLowerCase().includes(query),
    );
  }, [permissions, search]);

  const groupedPermissions = useMemo(
    () => groupByModule(filteredPermissions),
    [filteredPermissions],
  );

  const togglePermission = (permissionId) => {
    onSelectedIdsChange((current) => {
      const next = new Set(current);
      if (next.has(permissionId)) {
        next.delete(permissionId);
      } else {
        next.add(permissionId);
      }
      return next;
    });
  };

  const toggleModule = (modulePermissions, checked) => {
    onSelectedIdsChange((current) => {
      const next = new Set(current);
      modulePermissions.forEach((permission) => {
        if (checked) {
          next.add(permission.id);
        } else {
          next.delete(permission.id);
        }
      });
      return next;
    });
  };

  return (
    <Stack
      spacing={2}
      sx={fillHeight ? { flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column' } : undefined}
    >
      <Input
        size="sm"
        placeholder="Filtrer les permissions..."
        startDecorator={<Search size={16} />}
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        disabled={loading}
        sx={{ bgcolor: 'background.level1', border: 'none', flexShrink: 0 }}
      />

      <Box
        sx={{
          ...(fillHeight ? { flex: 1, minHeight: 160 } : { maxHeight }),
          overflow: 'auto',
          border: '1px solid',
          borderColor: 'divider',
          borderRadius: 'lg',
          p: 1.5,
        }}
      >
        {loading ? (
          <Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>
            Chargement des permissions...
          </Typography>
        ) : null}

        {!loading && Object.keys(groupedPermissions).length === 0 ? (
          <Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>
            Aucune permission disponible.
          </Typography>
        ) : null}

        {!loading
          ? Object.entries(groupedPermissions).map(([module, modulePermissions]) => {
              const allSelected = modulePermissions.every((permission) => selectedIds.has(permission.id));
              const someSelected = modulePermissions.some((permission) => selectedIds.has(permission.id));

              return (
                <Box key={module} sx={{ mb: 2 }}>
                  <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 1 }}>
                    <Checkbox
                      size="sm"
                      checked={allSelected}
                      indeterminate={!allSelected && someSelected}
                      onChange={(event) => toggleModule(modulePermissions, event.target.checked)}
                      disabled={loading}
                      label={(
                        <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                          {PERMISSION_MODULE_LABELS[module] ?? module}
                        </Typography>
                      )}
                    />
                    <Chip size="sm" variant="outlined" color="neutral">
                      {modulePermissions.filter((p) => selectedIds.has(p.id)).length}/{modulePermissions.length}
                    </Chip>
                  </Stack>

                  <Stack spacing={0.5} sx={{ pl: 3 }}>
                    {modulePermissions.map((permission) => (
                      <Checkbox
                        key={permission.id}
                        size="sm"
                        checked={selectedIds.has(permission.id)}
                        onChange={() => togglePermission(permission.id)}
                        disabled={loading}
                        label={(
                          <Typography level="body-sm" sx={{ fontWeight: 500 }}>
                            {permission.libelle}
                          </Typography>
                        )}
                      />
                    ))}
                  </Stack>

                  <Divider sx={{ mt: 1.5 }} />
                </Box>
              );
            })
          : null}
      </Box>
    </Stack>
  );
}
