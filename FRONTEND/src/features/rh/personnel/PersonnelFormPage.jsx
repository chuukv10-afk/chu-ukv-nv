import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Avatar,
  Box,
  Button,
  Card,
  FormControl,
  FormHelperText,
  FormLabel,
  Input,
  Option,
  Select,
  Stack,
  Tab,
  TabList,
  TabPanel,
  Tabs,
  Typography,
} from '@mui/joy';
import { ArrowLeft, Camera, Eye, EyeOff, Save, X } from 'lucide-react';
import AuthAvatar from '../../../components/ui/AuthAvatar.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { getInitials } from '../../../utils/profile.js';
import {
  AVATAR_ACCEPT,
  fetchAuthenticatedAvatarUrl,
  readFilePreview,
  validateAvatarFile,
} from '../../../utils/avatar.js';
import { rh } from '../../../api/endpoints.js';
import {
  DEFAULT_PERSONNEL_PASSWORD,
  PERSONNEL_STATUSES,
  PERSONNEL_TYPES,
} from '../../admin/personnel/personnelConstants.js';
import {
  createPersonnelApi,
  fetchPersonnelApi,
  fetchPersonnelLookupsApi,
  fetchRhPersonnelLookupsApi,
  updatePersonnelApi,
  uploadPersonnelAvatarApi,
  deletePersonnelAvatarApi,
} from '../../admin/personnel/personnelApi.js';

const API_OPTIONS = { base: rh.personnels };

const TABS = {
  IDENTITE: 0,
  AFFECTATION: 1,
  AUTH: 2,
};

function emptyForm() {
  return {
    nom: '',
    postNom: '',
    prenom: '',
    sexe: 'M',
    matricule: '',
    adresse: '',
    lieuNaissance: '',
    type: 'ADMINISTRATIF',
    status: 'ACTIF',
    gradeId: null,
    fonctionId: null,
    departementId: null,
    serviceId: null,
    telephone: '',
    password: DEFAULT_PERSONNEL_PASSWORD,
  };
}

function serviceDepartementId(service) {
  return service?.departementId ?? service?.departement?.id ?? null;
}

export default function PersonnelFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const isEdit = Boolean(id);
  const canSave = isEdit
    ? hasPermission(PERMISSIONS.RH.PERSONNEL_UPDATE)
    : hasPermission(PERMISSIONS.RH.PERSONNEL_CREATE);

  const [tab, setTab] = useState(TABS.IDENTITE);
  const [form, setForm] = useState(emptyForm);
  const [lookups, setLookups] = useState({
    grades: [],
    fonctions: [],
    services: [],
    departements: [],
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [avatarFile, setAvatarFile] = useState(null);
  const [avatarPreview, setAvatarPreview] = useState(null);
  const [removeAvatar, setRemoveAvatar] = useState(false);
  const [avatarUrl, setAvatarUrl] = useState(null);
  const [showPassword, setShowPassword] = useState(!isEdit);

  const goBack = () => navigate(ROUTES.RH.PERSONNEL);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      setLoading(true);
      setError('');
      try {
        const data = await fetchRhPersonnelLookupsApi();
        if (cancelled) return;
        setLookups({
          grades: data.grades ?? [],
          fonctions: data.fonctions ?? [],
          services: data.services ?? [],
          departements: data.departements ?? [],
        });

        if (!id) {
          setForm(emptyForm());
          return;
        }

        const detail = await fetchPersonnelApi(id, API_OPTIONS);
        if (cancelled) return;

        const serviceId = detail.service?.id ?? null;
        const departementId = detail.service?.departement?.id
          ?? data.services.find((item) => item.id === serviceId)?.departement?.id
          ?? data.services.find((item) => item.id === serviceId)?.departementId
          ?? null;

        setForm({
          nom: detail.nom ?? '',
          postNom: detail.postNom ?? '',
          prenom: detail.prenom ?? '',
          sexe: detail.sexe ?? 'M',
          matricule: detail.matricule ?? '',
          adresse: detail.adresse ?? '',
          lieuNaissance: detail.lieuNaissance ?? '',
          type: detail.type ?? 'ADMINISTRATIF',
          status: detail.status ?? 'ACTIF',
          gradeId: detail.grade?.id ?? null,
          fonctionId: detail.fonction?.id ?? null,
          departementId,
          serviceId,
          telephone: detail.telephone ?? '',
          password: '',
        });
        setAvatarUrl(detail.avatarUrl ?? null);
        if (detail.avatarUrl) {
          const preview = await fetchAuthenticatedAvatarUrl(detail.avatarUrl);
          if (!cancelled && preview) setAvatarPreview(preview);
        }
      } catch (err) {
        if (!cancelled) setError(err.message || 'Impossible de charger le formulaire.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [id]);

  const departements = useMemo(() => {
    if (lookups.departements.length) return lookups.departements;
    const map = new Map();
    lookups.services.forEach((service) => {
      const departement = service.departement;
      if (departement?.id && !map.has(departement.id)) {
        map.set(departement.id, departement);
      }
    });
    return [...map.values()];
  }, [lookups.departements, lookups.services]);

  const servicesByDepartement = useMemo(() => {
    if (!form.departementId) return lookups.services;
    return lookups.services.filter((service) => serviceDepartementId(service) === form.departementId);
  }, [lookups.services, form.departementId]);

  const handleChange = (field, value) => {
    setForm((current) => {
      if (field === 'departementId') {
        const nextServices = lookups.services.filter((service) => serviceDepartementId(service) === (value || null));
        const keepService = nextServices.some((service) => service.id === current.serviceId);
        return {
          ...current,
          departementId: value || null,
          serviceId: keepService ? current.serviceId : null,
        };
      }

      if (field === 'serviceId') {
        const service = lookups.services.find((item) => item.id === value);
        return {
          ...current,
          serviceId: value || null,
          departementId: serviceDepartementId(service) ?? current.departementId,
        };
      }

      if (field === 'fonctionId') {
        const next = { ...current, fonctionId: value || null };
        const fonction = lookups.fonctions.find((item) => item.id === value);
        const usualServiceId = fonction?.service?.id ?? fonction?.serviceId ?? null;
        if (usualServiceId) {
          const service = lookups.services.find((item) => item.id === usualServiceId);
          next.serviceId = usualServiceId;
          next.departementId = serviceDepartementId(service) ?? current.departementId;
        }
        return next;
      }

      return { ...current, [field]: value };
    });
  };

  const handleAvatarChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    const validationError = validateAvatarFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    try {
      setAvatarFile(file);
      setAvatarPreview(await readFilePreview(file));
      setRemoveAvatar(false);
      setError('');
    } catch (err) {
      setError(err.message || 'Impossible de charger la photo.');
    }
  };

  const handleRemoveAvatar = () => {
    setAvatarFile(null);
    setAvatarPreview(null);
    setRemoveAvatar(true);
  };

  const validate = () => {
    if (!form.nom.trim()) {
      setTab(TABS.IDENTITE);
      return 'Le nom est obligatoire.';
    }
    if (!form.telephone.trim()) {
      setTab(TABS.AUTH);
      return 'Le téléphone est obligatoire.';
    }
    if (!isEdit && !form.password.trim()) {
      setTab(TABS.AUTH);
      return 'Le mot de passe est obligatoire.';
    }
    if (!isEdit && form.password.trim().length < 6) {
      setTab(TABS.AUTH);
      return 'Le mot de passe doit contenir au moins 6 caractères.';
    }
    return '';
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!canSave) return;

    const validationError = validate();
    if (validationError) {
      setError(validationError);
      return;
    }

    setSaving(true);
    setError('');
    try {
      const payload = {
        nom: form.nom.trim(),
        postNom: form.postNom.trim(),
        prenom: form.prenom.trim() || null,
        telephone: form.telephone.trim(),
        matricule: form.matricule.trim() || null,
        sexe: form.sexe,
        type: form.type,
        status: form.status,
        adresse: form.adresse.trim() || null,
        lieuNaissance: form.lieuNaissance.trim() || null,
        gradeId: form.gradeId || null,
        fonctionId: form.fonctionId || null,
        serviceId: form.serviceId || null,
      };

      if (isEdit) {
        if (form.password.trim()) payload.password = form.password.trim();
        await updatePersonnelApi(id, payload, API_OPTIONS);
        if (avatarFile) {
          await uploadPersonnelAvatarApi(id, avatarFile, API_OPTIONS);
        } else if (removeAvatar) {
          await deletePersonnelAvatarApi(id, API_OPTIONS);
        }
        showSuccess('Personnel mis à jour avec succès.');
      } else {
        payload.password = form.password.trim() || DEFAULT_PERSONNEL_PASSWORD;
        payload.roleAssignments = [];
        const created = await createPersonnelApi(payload, API_OPTIONS);
        if (avatarFile) {
          await uploadPersonnelAvatarApi(created.id, avatarFile, API_OPTIONS);
        }
        showSuccess('Personnel créé avec succès.');
      }

      navigate(ROUTES.RH.PERSONNEL);
    } catch (err) {
      setError(err.message || 'Enregistrement impossible.');
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const busy = loading || saving;

  return (
    <Stack spacing={2.5} component="form" onSubmit={handleSubmit}>
      <Button
        variant="plain"
        color="neutral"
        startDecorator={<ArrowLeft size={16} />}
        onClick={goBack}
        sx={{ alignSelf: 'flex-start', px: 0 }}
      >
        Retour au personnel
      </Button>

      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>
            {isEdit ? 'Modifier le personnel' : 'Nouveau personnel'}
          </Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>
            Fiche simple : identité, affectation, puis accès au compte.
          </Typography>
        </Box>
        <Stack direction="row" spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
          <Button variant="outlined" color="neutral" onClick={goBack} disabled={saving}>
            Annuler
          </Button>
          {canSave ? (
            <Button type="submit" startDecorator={<Save size={16} />} loading={saving} disabled={busy}>
              {isEdit ? 'Enregistrer' : 'Créer le personnel'}
            </Button>
          ) : null}
        </Stack>
      </Stack>

      {error ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {error}
        </Typography>
      ) : null}

      <Card variant="outlined" sx={{ p: { xs: 1.5, md: 2.5 } }}>
        <Tabs value={tab} onChange={(_, value) => setTab(value ?? TABS.IDENTITE)}>
          <TabList sx={{ flexWrap: 'wrap' }}>
            <Tab value={TABS.IDENTITE}>Informations personnelles</Tab>
            <Tab value={TABS.AFFECTATION}>Affectation</Tab>
            <Tab value={TABS.AUTH}>Authentification</Tab>
          </TabList>

          <TabPanel value={TABS.IDENTITE} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Nom</FormLabel>
                  <Input value={form.nom} onChange={(e) => handleChange('nom', e.target.value)} disabled={busy} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Post-nom</FormLabel>
                  <Input value={form.postNom} onChange={(e) => handleChange('postNom', e.target.value)} disabled={busy} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Prénom</FormLabel>
                  <Input value={form.prenom} onChange={(e) => handleChange('prenom', e.target.value)} disabled={busy} />
                </FormControl>
              </Stack>

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Sexe</FormLabel>
                  <Select value={form.sexe} onChange={(_, value) => handleChange('sexe', value)} disabled={busy}>
                    <Option value="M">Masculin</Option>
                    <Option value="F">Féminin</Option>
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Matricule</FormLabel>
                  <Input
                    value={form.matricule}
                    onChange={(e) => handleChange('matricule', e.target.value)}
                    disabled={busy}
                    placeholder="Optionnel"
                  />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Lieu de naissance</FormLabel>
                  <Input
                    value={form.lieuNaissance}
                    onChange={(e) => handleChange('lieuNaissance', e.target.value)}
                    disabled={busy}
                    placeholder="Optionnel"
                  />
                </FormControl>
              </Stack>

              <FormControl>
                <FormLabel>Adresse</FormLabel>
                <Input
                  value={form.adresse}
                  onChange={(e) => handleChange('adresse', e.target.value)}
                  disabled={busy}
                  placeholder="Optionnel"
                />
              </FormControl>

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Type</FormLabel>
                  <Select value={form.type} onChange={(_, value) => handleChange('type', value)} disabled={busy}>
                    {PERSONNEL_TYPES.map((item) => (
                      <Option key={item.value} value={item.value}>{item.label}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Statut</FormLabel>
                  <Select value={form.status} onChange={(_, value) => handleChange('status', value)} disabled={busy}>
                    {PERSONNEL_STATUSES.filter((item) => item.value !== 'SUPPRIME').map((item) => (
                      <Option key={item.value} value={item.value}>{item.label}</Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>
            </Stack>
          </TabPanel>

          <TabPanel value={TABS.AFFECTATION} sx={{ px: 0, pt: 2.5, overflow: 'visible' }}>
            <Stack spacing={2}>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                Choisissez d’abord le département pour n’afficher que ses services. Une fonction peut préremplir le service habituel.
              </Typography>
              {!loading && lookups.grades.length === 0 && lookups.fonctions.length === 0 && lookups.services.length === 0 ? (
                <Typography level="body-sm" color="danger">
                  Impossible de charger grades, fonctions et services. Réessayez ou contactez l’administrateur.
                </Typography>
              ) : null}
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Grade</FormLabel>
                  <Select
                    value={form.gradeId != null ? String(form.gradeId) : ''}
                    onChange={(_, value) => handleChange('gradeId', value ? Number(value) : null)}
                    placeholder="Aucun"
                    disabled={busy}
                  >
                    <Option value="">Aucun</Option>
                    {lookups.grades.map((grade) => (
                      <Option key={grade.id} value={String(grade.id)}>{grade.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Fonction</FormLabel>
                  <Select
                    value={form.fonctionId != null ? String(form.fonctionId) : ''}
                    onChange={(_, value) => handleChange('fonctionId', value ? Number(value) : null)}
                    placeholder="Aucune"
                    disabled={busy}
                  >
                    <Option value="">Aucune</Option>
                    {lookups.fonctions.map((fonction) => (
                      <Option key={fonction.id} value={String(fonction.id)}>{fonction.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Département</FormLabel>
                  <Select
                    value={form.departementId != null ? String(form.departementId) : ''}
                    onChange={(_, value) => handleChange('departementId', value ? Number(value) : null)}
                    placeholder="Tous les départements"
                    disabled={busy}
                  >
                    <Option value="">Tous les départements</Option>
                    {departements.map((departement) => (
                      <Option key={departement.id} value={String(departement.id)}>{departement.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Service</FormLabel>
                  <Select
                    value={form.serviceId != null ? String(form.serviceId) : ''}
                    onChange={(_, value) => handleChange('serviceId', value ? Number(value) : null)}
                    placeholder={form.departementId ? 'Services du département' : 'Aucun'}
                    disabled={busy}
                  >
                    <Option value="">Aucun</Option>
                    {servicesByDepartement.map((service) => (
                      <Option key={service.id} value={String(service.id)}>{service.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>
            </Stack>
          </TabPanel>

          <TabPanel value={TABS.AUTH} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2.5}>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
                <Box sx={{ position: 'relative', width: 'fit-content' }}>
                  {avatarPreview && !removeAvatar ? (
                    <Avatar src={avatarPreview} sx={{ width: 96, height: 96, fontSize: 'xl' }} />
                  ) : (
                    <AuthAvatar
                      src={!removeAvatar ? avatarUrl : null}
                      fallback={getInitials(form)}
                      sx={{ width: 96, height: 96, fontSize: 'xl', bgcolor: 'primary.50', color: 'primary.700' }}
                    />
                  )}
                </Box>
                <Stack spacing={1}>
                  <Typography level="title-sm" sx={{ fontWeight: 700 }}>Photo de profil</Typography>
                  <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                    <Button
                      component="label"
                      size="sm"
                      variant="outlined"
                      startDecorator={<Camera size={16} />}
                      disabled={busy}
                    >
                      Choisir une photo
                      <input hidden type="file" accept={AVATAR_ACCEPT} onChange={handleAvatarChange} disabled={busy} />
                    </Button>
                    {(avatarPreview || (!removeAvatar && avatarUrl)) ? (
                      <Button
                        size="sm"
                        variant="plain"
                        color="danger"
                        startDecorator={<X size={16} />}
                        onClick={handleRemoveAvatar}
                        disabled={busy}
                      >
                        Supprimer
                      </Button>
                    ) : null}
                  </Stack>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                    JPG, PNG ou WebP — optionnel.
                  </Typography>
                </Stack>
              </Stack>

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Téléphone</FormLabel>
                  <Input
                    value={form.telephone}
                    onChange={(e) => handleChange('telephone', e.target.value)}
                    disabled={busy}
                    placeholder="Identifiant de connexion"
                  />
                  <FormHelperText>Utilisé pour se connecter à l’application.</FormHelperText>
                </FormControl>
                <FormControl required={!isEdit} sx={{ flex: 1 }}>
                  <FormLabel>Mot de passe</FormLabel>
                  <Input
                    type={showPassword ? 'text' : 'password'}
                    value={form.password}
                    onChange={(e) => handleChange('password', e.target.value)}
                    disabled={busy}
                    placeholder={isEdit ? 'Laisser vide pour ne pas changer' : DEFAULT_PERSONNEL_PASSWORD}
                    endDecorator={(
                      <Button
                        size="sm"
                        variant="plain"
                        color="neutral"
                        onClick={() => setShowPassword((current) => !current)}
                        aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                      >
                        {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                      </Button>
                    )}
                  />
                  <FormHelperText>
                    {isEdit
                      ? 'Laisser vide pour conserver le mot de passe actuel.'
                      : `Par défaut : ${DEFAULT_PERSONNEL_PASSWORD} — vous pouvez le changer.`}
                  </FormHelperText>
                </FormControl>
              </Stack>
            </Stack>
          </TabPanel>
        </Tabs>
      </Card>
    </Stack>
  );
}
