import { useState } from 'react';

import {

  Avatar,

  Box,

  Breadcrumbs,

  Chip,

  Divider,

  Dropdown,

  IconButton,

  Input,

  ListItemDecorator,

  Menu,

  MenuButton,

  MenuItem,

  Stack,

  Typography,

} from '@mui/joy';

import { Bell, ChevronDown, LogOut, Menu as MenuIcon, Search, User } from 'lucide-react';

import LogoutConfirmModal from '../auth/LogoutConfirmModal.jsx';

import { LAYOUT } from '../../constants/layout.js';

import { useAuth } from '../../hooks/useAuth.js';

import { useLogoutConfirm } from '../../hooks/useLogoutConfirm.js';

import { usePageMeta } from '../../hooks/usePageMeta.js';

import { useAppDispatch } from '../../hooks/useAppStore.js';

import { setSidebarOpen } from '../../store/ui/uiSlice.js';

import { LOTRU_PRIMARY } from '../../theme/lotruPalette.js';

import {

  formatRoleAssignment,

  getDisplayName,

  getInitials,

  getPersonnelTypeLabel,

} from '../../utils/profile.js';



export default function Header() {

  const dispatch = useAppDispatch();

  const { profile } = useAuth();

  const { title, section } = usePageMeta();

  const { logoutOpen, logoutLoading, requestLogout, cancelLogout, confirmLogout } = useLogoutConfirm();



  const displayName = getDisplayName(profile);

  const roleAssignments = profile?.roleAssignments ?? [];

  const personnelLabel = getPersonnelTypeLabel(profile?.type);



  return (

    <>

      <Box

        component="header"

        sx={{

          height: LAYOUT.headerHeight,

          px: { xs: 2, md: 3 },

          bgcolor: 'background.surface',

          borderBottom: '1px solid',

          borderColor: 'divider',

          display: 'flex',

          alignItems: 'center',

          position: 'sticky',

          top: 0,

          zIndex: 1000,

        }}

      >

        <Stack

          direction="row"

          alignItems="center"

          justifyContent="space-between"

          spacing={2}

          sx={{ width: '100%' }}

        >

          <Stack direction="row" alignItems="center" spacing={1.5} sx={{ minWidth: 0 }}>

            <IconButton

              variant="plain"

              color="neutral"

              onClick={() => dispatch(setSidebarOpen(true))}

              sx={{ display: { md: 'none' } }}

            >

              <MenuIcon size={20} />

            </IconButton>



            <Box sx={{ minWidth: 0 }}>

              <Breadcrumbs size="sm" sx={{ '--Breadcrumbs-gap': '6px', mb: 0.25 }}>

                <Typography level="body-xs" sx={{ color: 'neutral.500' }}>

                  {section}

                </Typography>

              </Breadcrumbs>

              <Typography level="title-lg" sx={{ fontWeight: 700, lineHeight: 1.2 }} noWrap>

                {title}

              </Typography>

            </Box>

          </Stack>



          <Stack direction="row" alignItems="center" spacing={1}>

            <Input

              size="sm"

              placeholder="Rechercher..."

              startDecorator={<Search size={16} />}

              sx={{

                width: { xs: 0, sm: 220, md: 280 },

                display: { xs: 'none', sm: 'flex' },

                bgcolor: 'background.level2',

                border: 'none',

                boxShadow: 'none',

                transition: 'width 0.2s ease, box-shadow 0.2s ease',

                '&:focus-within': {

                  boxShadow: '0 0 0 2px var(--joy-palette-primary-200)',

                },

              }}

            />



            <IconButton

              variant="plain"

              color="neutral"

              sx={{

                display: { xs: 'none', sm: 'inline-flex' },

                transition: 'background-color 0.2s ease',

              }}

            >

              <Bell size={20} />

            </IconButton>



            {profile?.type ? (

              <Chip

                size="sm"

                variant="soft"

                color="primary"

                sx={{ display: { xs: 'none', lg: 'inline-flex' } }}

              >

                {personnelLabel}

              </Chip>

            ) : null}



            <Divider orientation="vertical" sx={{ height: 24, display: { xs: 'none', sm: 'block' } }} />



            <Dropdown>

              <MenuButton

                variant="plain"

                sx={{

                  borderRadius: 'lg',

                  px: { xs: 0.5, md: 1 },

                  py: 0.5,

                  minHeight: 'unset',

                  gap: 1,

                  transition: 'background-color 0.2s ease',

                  '&:hover': { bgcolor: 'background.level1' },

                  '&[aria-expanded="true"]': { bgcolor: 'background.level1' },

                }}

              >

                <Stack direction="row" alignItems="center" spacing={1}>

                  <Avatar size="sm" variant="soft" sx={{ bgcolor: LOTRU_PRIMARY[100], color: LOTRU_PRIMARY[700] }}>

                    {getInitials(profile)}

                  </Avatar>

                  <Box sx={{ display: { xs: 'none', md: 'block' }, textAlign: 'left', minWidth: 0 }}>

                    <Typography level="title-sm" sx={{ fontWeight: 600, lineHeight: 1.2 }} noWrap>

                      {displayName}

                    </Typography>

                    <Typography level="body-xs" sx={{ color: 'neutral.500' }} noWrap>

                      {personnelLabel}

                    </Typography>

                  </Box>

                  <ChevronDown size={16} style={{ opacity: 0.6, flexShrink: 0 }} />

                </Stack>

              </MenuButton>



              <Menu

                placement="bottom-end"

                sx={{

                  minWidth: 300,

                  borderRadius: 'xl',

                  p: 0,

                  overflow: 'hidden',

                  boxShadow: 'lg',

                  border: '1px solid',

                  borderColor: 'divider',

                  '--ListItem-radius': '10px',

                }}

              >

                <Box

                  sx={{

                    px: 2.5,

                    py: 2,

                    bgcolor: 'primary.50',

                    borderBottom: '1px solid',

                    borderColor: 'divider',

                  }}

                >

                  <Stack direction="row" spacing={1.5} alignItems="center">

                    <Avatar

                      size="md"

                      variant="soft"

                      sx={{ bgcolor: LOTRU_PRIMARY[500], color: '#fff', fontWeight: 700 }}

                    >

                      {getInitials(profile)}

                    </Avatar>

                    <Box sx={{ minWidth: 0 }}>

                      <Typography level="title-md" sx={{ fontWeight: 700 }} noWrap>

                        {displayName}

                      </Typography>

                      <Typography level="body-xs" sx={{ color: 'neutral.600' }} noWrap>

                        {profile?.matricule} · {profile?.grade || 'Personnel'}

                      </Typography>

                      {profile?.service ? (

                        <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 0.25 }} noWrap>

                          {profile.service}

                        </Typography>

                      ) : null}

                    </Box>

                  </Stack>

                </Box>



                {roleAssignments.length > 0 ? (

                  <Box sx={{ px: 1.5, py: 1.5 }}>

                    <Typography

                      level="body-xs"

                      sx={{ px: 0.75, mb: 0.75, color: 'neutral.500', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.04em' }}

                    >

                      Affectations

                    </Typography>

                    <Stack spacing={0.5}>

                      {roleAssignments.map((assignment, index) => (

                        <Box

                          key={`${assignment.role}-${index}`}

                          sx={{

                            px: 1,

                            py: 0.75,

                            borderRadius: 'md',

                            bgcolor: 'background.level1',

                          }}

                        >

                          <Typography level="body-xs" sx={{ color: 'neutral.700', whiteSpace: 'normal' }}>

                            {formatRoleAssignment(assignment)}

                          </Typography>

                        </Box>

                      ))}

                    </Stack>

                  </Box>

                ) : null}



                <Divider />



                <Box sx={{ p: 1 }}>

                  <MenuItem sx={{ borderRadius: 'md', transition: 'background-color 0.15s ease' }}>

                    <ListItemDecorator>

                      <User size={18} />

                    </ListItemDecorator>

                    Mon profil

                  </MenuItem>

                  <MenuItem

                    color="danger"

                    onClick={requestLogout}

                    sx={{

                      borderRadius: 'md',

                      transition: 'background-color 0.15s ease',

                      mt: 0.5,

                    }}

                  >

                    <ListItemDecorator>

                      <LogOut size={18} />

                    </ListItemDecorator>

                    Déconnexion

                  </MenuItem>

                </Box>

              </Menu>

            </Dropdown>

          </Stack>

        </Stack>

      </Box>



      <LogoutConfirmModal

        open={logoutOpen}

        onClose={cancelLogout}

        onConfirm={confirmLogout}

        loading={logoutLoading}

      />

    </>

  );

}


