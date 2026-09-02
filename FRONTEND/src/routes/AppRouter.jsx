import { useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { ROUTES } from '../constants/routes.js';
import { AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import AppLayout from '../components/layout/AppLayout.jsx';
import { RequireAuth } from '../components/auth/RequireAuth.jsx';
import { GuestRoute } from '../components/auth/GuestRoute.jsx';
import LoadingSpinner from '../components/ui/LoadingSpinner.jsx';
import LoginPage from '../pages/auth/LoginPage.jsx';
import DashboardPage from '../pages/dashboard/DashboardPage.jsx';
import AccessDeniedPage from '../pages/errors/AccessDeniedPage.jsx';
import NotFoundPage from '../pages/errors/NotFoundPage.jsx';
import DepartementsPage from '../features/organisation/departements/DepartementsPage.jsx';
import { fetchMe } from '../features/auth/authService.js';

export default function AppRouter() {
  const dispatch = useDispatch();
  const [initializing, setInitializing] = useState(true);

  useEffect(() => {
    const initializeAuth = async () => {
      const token = localStorage.getItem(AUTH_TOKEN_KEY);

      if (token) {
        await fetchMe(dispatch);
      }

      setInitializing(false);
    };

    initializeAuth();
  }, [dispatch]);

  if (initializing) {
    return <LoadingSpinner fullScreen message="Initialisation de votre espace..." />;
  }

  return (
    <BrowserRouter>
      <Routes>
        <Route element={<GuestRoute />}>
          <Route path={ROUTES.LOGIN} element={<LoginPage />} />
        </Route>

        <Route element={<RequireAuth />}>
          <Route element={<AppLayout />}>
            <Route path={ROUTES.DASHBOARD} element={<DashboardPage />} />
            <Route path={ROUTES.ORGANISATION.DEPARTEMENTS} element={<DepartementsPage />} />
          </Route>
        </Route>

        <Route path={ROUTES.HOME} element={<Navigate to={ROUTES.DASHBOARD} replace />} />
        <Route path={ROUTES.ACCESS_DENIED} element={<AccessDeniedPage />} />
        <Route path={ROUTES.NOT_FOUND} element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  );
}
