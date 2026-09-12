import { useEffect, useState } from 'react';
import { BrowserRouter, HashRouter, Navigate, Route, Routes } from 'react-router-dom';
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
import DatabaseAdminPage from '../features/admin/database/DatabaseAdminPage.jsx';
import GradesPage from '../features/referentiel/grades/GradesPage.jsx';
import FilieresPage from '../features/referentiel/filieres/FilieresPage.jsx';
import SpecialitesPage from '../features/referentiel/specialites/SpecialitesPage.jsx';
import TypesExamenPage from '../features/referentiel/types-examen/TypesExamenPage.jsx';
import TypesAntecedentPage from '../features/referentiel/types-antecedent/TypesAntecedentPage.jsx';
import SignesVitauxPage from '../features/referentiel/signes-vitaux/SignesVitauxPage.jsx';
import PlaintesPage from '../features/referentiel/plaintes/PlaintesPage.jsx';
import UnitesPage from '../features/pharmacie/unites/UnitesPage.jsx';
import FamillesPage from '../features/pharmacie/familles/FamillesPage.jsx';
import MedicamentsPage from '../features/pharmacie/medicaments/MedicamentsPage.jsx';
import FournisseursPage from '../features/pharmacie/fournisseurs/FournisseursPage.jsx';
import ReceptionsPage from '../features/pharmacie/receptions/ReceptionsPage.jsx';
import ReceptionFormPage from '../features/pharmacie/receptions/ReceptionFormPage.jsx';
import LotsPage from '../features/pharmacie/lots/LotsPage.jsx';
import MouvementsPage from '../features/pharmacie/mouvements/MouvementsPage.jsx';
import VentesPage from '../features/pharmacie/ventes/VentesPage.jsx';
import VenteFormPage from '../features/pharmacie/ventes/VenteFormPage.jsx';
import DemandesServicePage from '../features/pharmacie/demandes-service/DemandesServicePage.jsx';
import DemandeServiceFormPage from '../features/pharmacie/demandes-service/DemandeServiceFormPage.jsx';
import CreancesPage from '../features/pharmacie/demandes-service/CreancesPage.jsx';
import AjustementsPage from '../features/pharmacie/ajustements/AjustementsPage.jsx';
import AlertesPage from '../features/pharmacie/alertes/AlertesPage.jsx';
import RecettesPage from '../features/pharmacie/recettes/RecettesPage.jsx';
import StatistiquesPage from '../features/pharmacie/statistiques/StatistiquesPage.jsx';
import IntendanceBiensPage from '../features/intendance/biens/BiensPage.jsx';
import IntendanceFamillesPage from '../features/intendance/familles/FamillesPage.jsx';
import IntendanceTypesPage from '../features/intendance/types/TypesPage.jsx';
import IntendanceLocauxPage from '../features/intendance/locaux/LocauxPage.jsx';
import IntendanceIdentifierPage from '../features/intendance/identifier/IdentifierPage.jsx';
import ConsultationDetailPage from '../features/clinique/consultations/ConsultationDetailPage.jsx';
import TourDeSalleHubPage from '../features/clinique/tour-de-salle/TourDeSalleHubPage.jsx';
import TourDeSalleFichePage from '../features/clinique/tour-de-salle/TourDeSalleFichePage.jsx';
import DemandesExamenPage from '../features/clinique/demandes-examen/DemandesExamenPage.jsx';
import AptitudesPage from '../features/clinique/aptitude/AptitudesPage.jsx';
import AptitudeFormPage from '../features/clinique/aptitude/AptitudeFormPage.jsx';
import AptitudeStatsPage from '../features/clinique/aptitude/AptitudeStatsPage.jsx';
import ProfilePage from '../features/profile/ProfilePage.jsx';
import { PermissionGuard } from '../components/auth/PermissionGuard.jsx';
import { PERMISSIONS } from '../constants/permissions.js';
import { fetchMe } from '../features/auth/authService.js';
import { isDesktopApp } from '../offline/desktop.js';

const Router = isDesktopApp() ? HashRouter : BrowserRouter;

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
    <Router>
      <Routes>
        <Route element={<GuestRoute />}>
          <Route path={ROUTES.LOGIN} element={<LoginPage />} />
        </Route>

        <Route element={<RequireAuth />}>
          <Route element={<AppLayout />}>
            <Route path={ROUTES.DASHBOARD} element={<DashboardPage />} />
            <Route
              path={ROUTES.PROFILE}
              element={(
                <PermissionGuard permission={PERMISSIONS.ADMIN.SIGNATURE_READ}>
                  <ProfilePage />
                </PermissionGuard>
              )}
            />
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
              path={ROUTES.CLINIQUE.APTITUDE_STATS}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.APTITUDE_READ}>
                  <AptitudeStatsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.APTITUDE_NEW}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.APTITUDE_CREATE}>
                  <AptitudeFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.APTITUDE_DETAIL}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.APTITUDE_READ}>
                  <AptitudeFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.APTITUDES}
              element={(
                <PermissionGuard permission={PERMISSIONS.CLINIQUE.APTITUDE_READ}>
                  <AptitudesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.CLINIQUE.CONSULTATIONS}
              element={<Navigate to={ROUTES.PATIENT.LIST} replace />}
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
              path={ROUTES.REFERENTIEL.FILIERES}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.FILIERE_READ}>
                  <FilieresPage />
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
              path={ROUTES.REFERENTIEL.PLAINTES}
              element={(
                <PermissionGuard permission={PERMISSIONS.REFERENTIEL.PLAINTE_READ}>
                  <PlaintesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.UNITES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.UNITE_READ}>
                  <UnitesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.FAMILLES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.FAMILLE_READ}>
                  <FamillesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.MEDICAMENTS}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.MEDICAMENT_READ}>
                  <MedicamentsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.FOURNISSEURS}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.FOURNISSEUR_READ}>
                  <FournisseursPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.RECEPTION_NEW}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.RECEPTION_CREATE}>
                  <ReceptionFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.RECEPTION_DETAIL}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.RECEPTION_READ}>
                  <ReceptionFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.RECEPTIONS}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.RECEPTION_READ}>
                  <ReceptionsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.LOTS}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.LOT_READ}>
                  <LotsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.MOUVEMENTS}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.MOUVEMENT_READ}>
                  <MouvementsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.VENTE_ANTERIEURE_NEW}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.VENTE_SAISIE_ANTERIEURE}>
                  <VenteFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.VENTE_NEW}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.VENTE_CREATE}>
                  <VenteFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.VENTE_DETAIL}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.VENTE_READ}>
                  <VenteFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.VENTES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.VENTE_READ}>
                  <VentesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.DEMANDE_SERVICE_NEW}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_CREATE}>
                  <DemandeServiceFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.DEMANDE_SERVICE_DETAIL}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_READ}>
                  <DemandeServiceFormPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.DEMANDES_SERVICE}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_READ}>
                  <DemandesServicePage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.CREANCES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_READ}>
                  <CreancesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.RECETTES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.RECETTE_READ}>
                  <RecettesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.AJUSTEMENTS}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.AJUSTEMENT_CREATE}>
                  <AjustementsPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.ALERTES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.LOT_READ}>
                  <AlertesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.PHARMACIE.STATISTIQUES}
              element={(
                <PermissionGuard permission={PERMISSIONS.PHARMACIE.STATISTIQUE_READ}>
                  <StatistiquesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.INTENDANCE.BIENS}
              element={(
                <PermissionGuard permission={PERMISSIONS.INTENDANCE.BIEN_READ}>
                  <IntendanceBiensPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.INTENDANCE.IDENTIFIER}
              element={(
                <PermissionGuard permission={PERMISSIONS.INTENDANCE.BIEN_READ}>
                  <IntendanceIdentifierPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.INTENDANCE.FAMILLES}
              element={(
                <PermissionGuard permission={PERMISSIONS.INTENDANCE.FAMILLE_READ}>
                  <IntendanceFamillesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.INTENDANCE.TYPES}
              element={(
                <PermissionGuard permission={PERMISSIONS.INTENDANCE.TYPE_READ}>
                  <IntendanceTypesPage />
                </PermissionGuard>
              )}
            />
            <Route
              path={ROUTES.INTENDANCE.LOCAUX}
              element={(
                <PermissionGuard permission={PERMISSIONS.INTENDANCE.LOCAL_READ}>
                  <IntendanceLocauxPage />
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
            <Route
              path={ROUTES.ADMIN.DATABASE}
              element={(
                <PermissionGuard permission={PERMISSIONS.ADMIN.DATABASE_MANAGE}>
                  <DatabaseAdminPage />
                </PermissionGuard>
              )}
            />
          </Route>
        </Route>

        <Route path={ROUTES.HOME} element={<Navigate to={ROUTES.DASHBOARD} replace />} />
        <Route path={ROUTES.ACCESS_DENIED} element={<AccessDeniedPage />} />
        <Route path={ROUTES.NOT_FOUND} element={<NotFoundPage />} />
      </Routes>
    </Router>
  );
}
