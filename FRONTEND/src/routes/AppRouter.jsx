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
import ServicesPage from '../features/organisation/services/ServicesPage.jsx';
import LitsPage from '../features/organisation/lits/LitsPage.jsx';
import ChambresPage from '../features/organisation/chambres/ChambresPage.jsx';
import BlocsPage from '../features/organisation/blocs/BlocsPage.jsx';
import ExamensPage from '../features/clinique/examens/ExamensPage.jsx';
import MaladiesPage from '../features/clinique/maladies/MaladiesPage.jsx';
import VisitesPage from '../features/clinique/visites/VisitesPage.jsx';
import PatientsPage from '../features/patient/patients/PatientsPage.jsx';
import PatientDpiPage from '../features/patient/patients/PatientDpiPage.jsx';
import RolesPage from '../features/admin/roles/RolesPage.jsx';
import PermissionsPage from '../features/admin/permissions/PermissionsPage.jsx';
import RolePermissionsPage from '../features/admin/role-permissions/RolePermissionsPage.jsx';
import PersonnelPage from '../features/admin/personnel/PersonnelPage.jsx';
import GradesPage from '../features/referentiel/grades/GradesPage.jsx';
import SpecialitesPage from '../features/referentiel/specialites/SpecialitesPage.jsx';
import TypesExamenPage from '../features/referentiel/types-examen/TypesExamenPage.jsx';
import TypesAntecedentPage from '../features/referentiel/types-antecedent/TypesAntecedentPage.jsx';
import SignesVitauxPage from '../features/referentiel/signes-vitaux/SignesVitauxPage.jsx';
import ConsultationsPage from '../features/clinique/consultations/ConsultationsPage.jsx';
import ConsultationDetailPage from '../features/clinique/consultations/ConsultationDetailPage.jsx';
import TourDeSalleHubPage from '../features/clinique/tour-de-salle/TourDeSalleHubPage.jsx';
import TourDeSalleFichePage from '../features/clinique/tour-de-salle/TourDeSalleFichePage.jsx';
import DemandesExamenPage from '../features/clinique/demandes-examen/DemandesExamenPage.jsx';
import { PermissionGuard } from '../components/auth/PermissionGuard.jsx';
import { PERMISSIONS } from '../constants/permissions.js';
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
            <Route
              path={ROUTES.ORGANISATION.DEPARTEMENTS}
              element={(
                <PermissionGuard permission={PERMISSIONS.ORGANISATION.DEPARTEMENT_READ}>
                  <DepartementsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ORGANISATION.SERVICES}
              element={(
                <PermissionGuard permission={PERMISSIONS.ORGANISATION.SERVICE_READ}>
                  <ServicesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ORGANISATION.LITS}
              element={(
                <PermissionGuard permission={PERMISSIONS.ORGANISATION.LIT_READ}>
                  <LitsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ORGANISATION.CHAMBRES}
              element={(
                <PermissionGuard permission={PERMISSIONS.ORGANISATION.CHAMBRE_READ}>
                  <ChambresPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ORGANISATION.BLOCS}
              element={(
                <PermissionGuard permission={PERMISSIONS.ORGANISATION.BLOC_READ}>
                  <BlocsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.EXAMENS}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.EXAMEN_READ}>
                  <ExamensPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.DEMANDES_EXAMEN}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_READ}>
                  <DemandesExamenPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.MALADIES}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.MALADIE_READ}>
                  <MaladiesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.VISITES}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.VISITE_READ}>
                  <VisitesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.CONSULTATIONS}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.CONSULTATION_READ}>
                  <ConsultationsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.TOUR_DE_SALLE_FICHE}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.CONSULTATION_READ}>
                  <TourDeSalleFichePage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.TOUR_DE_SALLE}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.CONSULTATION_READ}>
                  <TourDeSalleHubPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.CONSULTATIONS_DETAIL}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.CONSULTATION_READ}>
                  <ConsultationDetailPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PATIENT.LIST}
              element={(
                <PermissionGuard permission={PERMISSIONS.PATIENT.PATIENT_READ}>
                  <PatientsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PATIENT.DPI}
              element={(
                <PermissionGuard permission={PERMISSIONS.PATIENT.DPI_READ}>
                  <PatientDpiPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.REFERENTIEL.GRADES}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.GRADE_READ}>
                  <GradesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.REFERENTIEL.SPECIALITES}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.SPECIALITE_READ}>
                  <SpecialitesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.REFERENTIEL.TYPES_EXAMEN}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.TYPE_EXAMEN_READ}>
                  <TypesExamenPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.REFERENTIEL.TYPES_ANTECEDENT}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.TYPE_ANTECEDENT_READ}>
                  <TypesAntecedentPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.REFERENTIEL.SIGNES_VITAUX}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.SIGNE_VITAL_READ}>
                  <SignesVitauxPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ADMIN.ROLES}
              element={(
                <PermissionGuard permission={PERMISSIONS.ADMIN.ROLE_READ}>
                  <RolesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ADMIN.PERMISSIONS}
              element={(
                <PermissionGuard permission={PERMISSIONS.ADMIN.PERMISSION_READ}>
                  <PermissionsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ADMIN.ROLE_PERMISSIONS}
              element={(
                <PermissionGuard permission={PERMISSIONS.ADMIN.ROLE_PERMISSION_READ}>
                  <RolePermissionsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.ADMIN.PERSONNEL}
              element={(
                <PermissionGuard permission={PERMISSIONS.ADMIN.PERSONNEL_READ}>
                  <PersonnelPage />
                </PermissionGuard>
              )}
            />
          </Route>
        </Route>

        <Route path={ROUTES.HOME} element={<Navigate to={ROUTES.DASHBOARD} replace />} />
        <Route path={ROUTES.ACCESS_DENIED} element={<AccessDeniedPage />} />
        <Route path={ROUTES.NOT_FOUND} element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  );
}
