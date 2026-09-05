import {
  Accordion,
  AccordionDetails,
  AccordionGroup,
  AccordionSummary,
  Box,
  FormControl,
  FormLabel,
  Grid,
  Input,
  Radio,
  RadioGroup,
  Textarea,
  Typography,
} from '@mui/joy';
import { ChevronDown } from 'lucide-react';
import { LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';
import { normalizePhysicalExam, setPhysicalExamField } from '../utils/physicalExamSchema.js';

function ExamField({
  label, path, value, onChange, readOnly, multiline = false, size = 'sm',
}) {
  const Component = multiline ? Textarea : Input;
  return (
    <FormControl size={size}>
      <FormLabel sx={{ fontWeight: 600, fontSize: '0.8rem' }}>{label}</FormLabel>
      <Component
        size={size}
        minRows={multiline ? 2 : undefined}
        value={value || ''}
        onChange={(event) => onChange(path, event.target.value)}
        readOnly={readOnly}
        placeholder={readOnly ? '—' : 'Saisir…'}
        sx={{ borderRadius: 'md' }}
      />
    </FormControl>
  );
}

function SectionHeader({ title, subtitle }) {
  return (
    <Box>
      <Typography level="title-sm" sx={{ fontWeight: 700 }}>{title}</Typography>
      {subtitle ? (
        <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{subtitle}</Typography>
      ) : null}
    </Box>
  );
}

function getNestedValue(obj, path) {
  return path.split('.').reduce((current, key) => current?.[key], obj);
}

export default function PhysicalExamForm({
  physicalExam,
  setPhysicalExam,
  readOnly = false,
  patientGender = null,
}) {
  const exam = normalizePhysicalExam(physicalExam);

  const update = (path, value) => {
    if (readOnly || !setPhysicalExam) return;
    setPhysicalExam(setPhysicalExamField(exam, path, value));
  };

  const isFemale = patientGender === 'F';
  const isMale = patientGender === 'M';

  return (
    <AccordionGroup
      sx={{
        borderRadius: 'md',
        overflow: 'hidden',
        '& .MuiAccordion-root': { bgcolor: 'background.surface' },
      }}
    >
      <Accordion defaultExpanded>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="1. État général" />
        </AccordionSummary>
        <AccordionDetails>
          <StackLike spacing={1.5}>
            <RadioGroup
              orientation="horizontal"
              value={exam.generalState.status}
              onChange={(event) => update('generalState.status', event.target.value)}
            >
              <Radio value="conserved" label="Conservé" disabled={readOnly} />
              <Radio value="altered" label="Altéré" disabled={readOnly} />
            </RadioGroup>
            {exam.generalState.status === 'altered' && (
              <ExamField
                label="Précisions (état altéré)"
                path="generalState.alteredDetails"
                value={exam.generalState.alteredDetails}
                onChange={update}
                readOnly={readOnly}
                multiline
              />
            )}
          </StackLike>
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="2. Peau" />
        </AccordionSummary>
        <AccordionDetails>
          <ExamField label="Peau" path="skin" value={exam.skin} onChange={update} readOnly={readOnly} multiline />
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="3. Tête et cou" />
        </AccordionSummary>
        <AccordionDetails>
          <Grid container spacing={1.5}>
            {[
              ['Crâne', 'headNeck.crane'],
              ['Cheveux', 'headNeck.cheveux'],
              ['Face', 'headNeck.face'],
              ['Paupières', 'headNeck.paupieres'],
              ['Conjonctives', 'headNeck.conjonctives'],
              ['Globes oculaires', 'headNeck.globesOculaires'],
              ['Pupilles', 'headNeck.pupilles'],
              ['Lèvres buccales', 'headNeck.levresBuccales'],
              ['Muqueuse buccale', 'headNeck.muqueuseBuccale'],
              ['Gencives', 'headNeck.gencives'],
              ['Langue', 'headNeck.langue'],
              ['Haleine', 'headNeck.haleine'],
              ['Gorge', 'headNeck.gorge'],
              ['Nez', 'headNeck.nez'],
              ['Oreilles', 'headNeck.oreilles'],
              ['Parotides', 'headNeck.parotides'],
              ['Thyroïde', 'headNeck.thyroide'],
            ].map(([label, path]) => (
              <Grid key={path} xs={12} sm={6}>
                <ExamField
                  label={label}
                  path={path}
                  value={getNestedValue(exam, path)}
                  onChange={update}
                  readOnly={readOnly}
                />
              </Grid>
            ))}
            <Grid xs={12}>
              <ExamField
                label="Aires ganglionnaires superficielles"
                path="headNeck.airesGanglionnaires"
                value={exam.headNeck.airesGanglionnaires}
                onChange={update}
                readOnly={readOnly}
                multiline
              />
            </Grid>
          </Grid>
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="4. Thorax" subtitle="Osseux, sein, appareil respiratoire, cœur, vaisseaux" />
        </AccordionSummary>
        <AccordionDetails>
          <StackLike spacing={2}>
            <Grid container spacing={1.5}>
              <Grid xs={12} sm={6}>
                <ExamField label="A. Thorax osseux" path="thorax.osseux" value={exam.thorax.osseux} onChange={update} readOnly={readOnly} />
              </Grid>
              <Grid xs={12} sm={6}>
                <ExamField label="B. Sein" path="thorax.sein" value={exam.thorax.sein} onChange={update} readOnly={readOnly} />
              </Grid>
            </Grid>

            <Typography level="body-sm" sx={{ fontWeight: 700, color: LOTRU_PRIMARY[700] }}>C. Poumon (appareil respiratoire)</Typography>
            <Grid container spacing={1.5}>
              {[
                ['Inspection', 'thorax.poumon.inspection'],
                ['Palpation', 'thorax.poumon.palpation'],
                ['Percussion', 'thorax.poumon.percussion'],
                ['Auscultation', 'thorax.poumon.auscultation'],
                ['Syndrome pulmonaire', 'thorax.poumon.syndromePulmonaire'],
              ].map(([label, path]) => (
                <Grid key={path} xs={12} sm={6}>
                  <ExamField label={label} path={path} value={getNestedValue(exam, path)} onChange={update} readOnly={readOnly} />
                </Grid>
              ))}
            </Grid>

            <Typography level="body-sm" sx={{ fontWeight: 700, color: LOTRU_PRIMARY[700] }}>D. Cœur</Typography>
            <Grid container spacing={1.5}>
              {[
                ['Inspection', 'thorax.coeur.inspection'],
                ['Palpation', 'thorax.coeur.palpation'],
                ['Auscultation', 'thorax.coeur.auscultation'],
              ].map(([label, path]) => (
                <Grid key={path} xs={12} sm={4}>
                  <ExamField label={label} path={path} value={getNestedValue(exam, path)} onChange={update} readOnly={readOnly} />
                </Grid>
              ))}
            </Grid>

            <Typography level="body-sm" sx={{ fontWeight: 700, color: LOTRU_PRIMARY[700] }}>E. Vaisseaux</Typography>
            <Grid container spacing={1.5}>
              <Grid xs={12} sm={6}>
                <ExamField label="Artères" path="thorax.vaisseaux.arteres" value={exam.thorax.vaisseaux.arteres} onChange={update} readOnly={readOnly} />
              </Grid>
              <Grid xs={12} sm={6}>
                <ExamField label="Veines" path="thorax.vaisseaux.veines" value={exam.thorax.vaisseaux.veines} onChange={update} readOnly={readOnly} />
              </Grid>
            </Grid>
          </StackLike>
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="5. Abdomen" />
        </AccordionSummary>
        <AccordionDetails>
          <Grid container spacing={1.5}>
            <Grid xs={12} sm={6}>
              <ExamField label="Périmètre ombilical" path="abdomen.perimetreOmbilical" value={exam.abdomen.perimetreOmbilical} onChange={update} readOnly={readOnly} />
            </Grid>
            {[
              ['Inspection', 'abdomen.inspection'],
              ['Palpation', 'abdomen.palpation'],
              ['Percussion', 'abdomen.percussion'],
              ['Auscultation', 'abdomen.auscultation'],
            ].map(([label, path]) => (
              <Grid key={path} xs={12} sm={6}>
                <ExamField label={label} path={path} value={getNestedValue(exam, path)} onChange={update} readOnly={readOnly} multiline />
              </Grid>
            ))}
          </Grid>
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="6. Fosses lombaires" />
        </AccordionSummary>
        <AccordionDetails>
          <ExamField label="Fosses lombaires" path="fossesLombaires" value={exam.fossesLombaires} onChange={update} readOnly={readOnly} multiline />
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="7. Organes génitaux externes et touchers pelviens" />
        </AccordionSummary>
        <AccordionDetails>
          <Grid container spacing={1.5}>
            <Grid xs={12} sm={6}>
              <ExamField label="Pubis" path="genitalOrgans.pubis" value={exam.genitalOrgans.pubis} onChange={update} readOnly={readOnly} />
            </Grid>
            <Grid xs={12} sm={6}>
              <ExamField label="OGE" path="genitalOrgans.oge" value={exam.genitalOrgans.oge} onChange={update} readOnly={readOnly} />
            </Grid>
            {(!isMale || !patientGender) && (
              <Grid xs={12} sm={6}>
                <ExamField label="Toucher vaginal" path="genitalOrgans.toucherVaginal" value={exam.genitalOrgans.toucherVaginal} onChange={update} readOnly={readOnly} multiline />
              </Grid>
            )}
            {(!isFemale || !patientGender) && (
              <Grid xs={12} sm={6}>
                <ExamField label="Toucher rectal" path="genitalOrgans.toucherRectal" value={exam.genitalOrgans.toucherRectal} onChange={update} readOnly={readOnly} multiline />
              </Grid>
            )}
          </Grid>
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="8. Appareil locomoteur" />
        </AccordionSummary>
        <AccordionDetails>
          <Grid container spacing={1.5}>
            {[
              ['Démarche', 'locomotor.demarche'],
              ['Membres supérieurs', 'locomotor.membresSuperieurs'],
              ['Membres inférieurs', 'locomotor.membresInferieurs'],
              ['Rachis', 'locomotor.rachis'],
            ].map(([label, path]) => (
              <Grid key={path} xs={12} sm={6}>
                <ExamField label={label} path={path} value={getNestedValue(exam, path)} onChange={update} readOnly={readOnly} multiline />
              </Grid>
            ))}
          </Grid>
        </AccordionDetails>
      </Accordion>

      <Accordion>
        <AccordionSummary indicator={<ChevronDown size={18} />}>
          <SectionHeader title="9. Examen neurologique" />
        </AccordionSummary>
        <AccordionDetails>
          <Grid container spacing={1.5}>
            {[
              ['État mental (fonctions supérieures)', 'neurological.etatMental'],
              ['Posture', 'neurological.posture'],
              ['Démarche', 'neurological.demarche'],
              ['Nerfs crâniens', 'neurological.nerfsCraniens'],
              ['Force musculaire', 'neurological.motricite.forceMusculaire'],
              ['Tonus musculaire', 'neurological.motricite.tonusMusculaire'],
              ['Coordination des mouvements', 'neurological.coordination'],
              ['Examen des réflexes', 'neurological.reflexes'],
              ['Examen de la sensibilité', 'neurological.sensibilite'],
              ['Signes méningés', 'neurological.signesMeninges'],
            ].map(([label, path]) => (
              <Grid key={path} xs={12} sm={6}>
                <ExamField label={label} path={path} value={getNestedValue(exam, path)} onChange={update} readOnly={readOnly} multiline />
              </Grid>
            ))}
          </Grid>
        </AccordionDetails>
      </Accordion>
    </AccordionGroup>
  );
}

function StackLike({ children, spacing = 1.5 }) {
  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: spacing }}>
      {children}
    </Box>
  );
}
