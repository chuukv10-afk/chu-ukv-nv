import { Box, Stack, Typography } from '@mui/joy';
import logo from '../../assets/img/logo.jpg';
import { useAuth } from '../../hooks/useAuth.js';
import { getDisplayName } from '../../utils/profile.js';

function greeting() {
  return new Date().getHours() >= 18 ? 'Bonsoir' : 'Bonjour';
}

export default function WelcomeHome() {
  const { profile } = useAuth();
  const firstName = profile?.prenom || getDisplayName(profile).split(' ')[0];

  return (
    <Box
      sx={{
        minHeight: { xs: '55vh', md: '62vh' },
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        px: 2,
      }}
    >
      <Stack spacing={2} alignItems="center" textAlign="center" sx={{ maxWidth: 420 }}>
        <Box
          component="img"
          src={logo}
          alt="CHU UKV"
          sx={{
            width: 88,
            height: 88,
            objectFit: 'contain',
            borderRadius: 'md',
            boxShadow: 'sm',
          }}
        />
        <Typography level="h2" sx={{ fontWeight: 700 }}>
          {greeting()}, {firstName}
        </Typography>
        <Typography level="body-md" sx={{ color: 'neutral.500' }}>
          Utilisez le menu pour commencer.
        </Typography>
      </Stack>
    </Box>
  );
}
