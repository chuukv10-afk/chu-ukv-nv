import { useState } from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  FormControl,
  FormLabel,
  IconButton,
  Input,
  Sheet,
  Stack,
  Typography,
} from '@mui/joy';
import {
  ArrowLeft,
  Eye,
  EyeOff,
  Phone,
} from 'lucide-react';
import { useDispatch } from 'react-redux';
import { useNavigate, useSearchParams } from 'react-router-dom';
import logo from '../../assets/img/logo.jpg';
import { ROUTES } from '../../constants/routes.js';
import { loginUser } from '../../features/auth/authService.js';

export default function LoginPage() {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [searchParams] = useSearchParams();
  const sessionExpired = searchParams.get('session') === 'expired';

  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [credentials, setCredentials] = useState({
    telephone: '',
    password: '',
  });
  const [error, setError] = useState('');

  const handleLogin = async (event) => {
    event?.preventDefault();
    setError('');

    if (!credentials.telephone || !credentials.password) {
      setError('Veuillez remplir tous les champs.');
      return;
    }

    try {
      setLoading(true);
      await loginUser(dispatch, {
        telephone: credentials.telephone,
        password: credentials.password,
      });
      navigate(ROUTES.DASHBOARD, { replace: true });
    } catch (err) {
      setError(err.message || 'Impossible de se connecter.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Sheet
      sx={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
        background: 'background.level2',
        p: 2,
        position: 'relative',
      }}
    >
      <Button
        variant="plain"
        sx={{
          position: 'absolute',
          top: 20,
          left: 20,
          color: 'text.secondary',
          '&:hover': { bgcolor: 'primary.50' },
        }}
        startDecorator={<ArrowLeft size={18} />}
        onClick={() => navigate(ROUTES.HOME)}
      >
        Retour
      </Button>

      <Card
        variant="plain"
        sx={{
          width: '100%',
          maxWidth: 400,
          borderRadius: 'xl',
          bgcolor: 'background.surface',
          boxShadow: 'md',
          p: 1,
          my: 5,
        }}
      >
        <Box sx={{ height: 4, width: '100%', borderRadius: '24px 24px 0 0', overflow: 'hidden' }}>
          {loading ? (
            <Box
              sx={{
                height: '100%',
                width: '50%',
                bgcolor: 'primary.400',
                animation: 'slide 1s infinite linear',
                '@keyframes slide': {
                  '0%': { transform: 'translateX(-100%)' },
                  '100%': { transform: 'translateX(250%)' },
                },
              }}
            />
          ) : null}
        </Box>

        <CardContent sx={{ p: 3 }}>
          <Stack alignItems="center" spacing={1} sx={{ mb: 4 }}>
            <Box
              component="img"
              src={logo}
              alt="CHU UKV"
              sx={{ width: 72, height: 72, objectFit: 'contain', borderRadius: 'sm' }}
            />
            <Typography level="h3" fontWeight="xl" sx={{ color: 'text.primary' }}>
              Connexion
            </Typography>
            <Typography level="body-sm" textColor="neutral.500">
              Veillez vous identifier pour continuer
            </Typography>
          </Stack>

          {sessionExpired && !error ? (
            <Typography
              color="warning"
              level="body-xs"
              sx={{ mb: 2, textAlign: 'center', bgcolor: 'warning.50', p: 1, borderRadius: 'sm' }}
            >
              Votre session a expiré. Veuillez vous reconnecter.
            </Typography>
          ) : null}

          {error ? (
            <Typography
              color="danger"
              level="body-xs"
              sx={{ mb: 2, textAlign: 'center', bgcolor: 'danger.50', p: 1, borderRadius: 'sm' }}
            >
              {error}
            </Typography>
          ) : null}

          <Stack
            component="form"
            spacing={2.5}
            onSubmit={handleLogin}
          >
            <FormControl>
              <FormLabel sx={{ fontWeight: 'lg', color: 'text.secondary' }}>Téléphone</FormLabel>
              <Input
                type="tel"
                placeholder="0000000000"
                startDecorator={<Phone size={18} />}
                value={credentials.telephone}
                onChange={(event) =>
                  setCredentials({ ...credentials, telephone: event.target.value })
                }
                autoComplete="username"
                sx={{
                  bgcolor: 'background.level1',
                }}
              />
            </FormControl>

            <FormControl>
              <FormLabel sx={{ fontWeight: 'lg', color: 'text.secondary' }}>Mot de passe</FormLabel>
              <Input
                type={showPassword ? 'text' : 'password'}
                placeholder="••••••••"
                value={credentials.password}
                onChange={(event) =>
                  setCredentials({ ...credentials, password: event.target.value })
                }
                autoComplete="current-password"
                endDecorator={
                  <IconButton onClick={() => setShowPassword((value) => !value)}>
                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                  </IconButton>
                }
                sx={{
                  bgcolor: 'background.level1',
                }}
              />
            </FormControl>

            <Button
              type="submit"
              size="lg"
              color="primary"
              variant="solid"
              loading={loading}
              sx={{
                mt: 2,
                borderRadius: 'lg',
                fontWeight: 'xl',
                transition: '0.3s',
                '&:hover': {
                  transform: 'scale(1.02)',
                },
              }}
            >
              Se connecter
            </Button>
          </Stack>
        </CardContent>
      </Card>

      <Typography level="body-xs" sx={{ mt: 4, color: 'neutral.500', textAlign: 'center' }}>
        © {new Date().getFullYear()} CHU-Soft UKV. Développé par le Service Informatique Interne du CHU.
      </Typography>
    </Sheet>
  );
}
