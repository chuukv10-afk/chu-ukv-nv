import { useState } from 'react';
import {
  Box,
  Button,
  Card,
  Chip,
  ChipDelete,
  FormControl,
  FormLabel,
  Grid,
  Input,
  Stack,
  Textarea,
  Typography,
} from '@mui/joy';
import { Plus, Printer } from 'lucide-react';
import PhysicalExamForm from './PhysicalExamForm.jsx';

export default function ClinicalExamTab({
  motif,
  setMotif,
  histoireMaladie,
  setHistoireMaladie,
  physicalExam,
  setPhysicalExam,
  physicalExamText,
  setPhysicalExamText,
  complementAnamnese,
  setComplementAnamnese,
  conduireATenir,
  setConduireATenir,
  onPrint,
  readOnly = false,
  patientGender = null,
  typeConsultation = 'NORMALE',
  isBedside: isBedsideProp = null,
  header = null,
}) {
  const isBedside = isBedsideProp ?? typeConsultation === 'AU_LIT';
  const [anamnesisInput, setAnamnesisInput] = useState('');

  const handleAddAnamnesis = () => {
    const value = (anamnesisInput || '').trim();
    if (!value || readOnly) return;
    setComplementAnamnese([...(complementAnamnese || []), value]);
    setAnamnesisInput('');
  };

  const handleRemoveAnamnesis = (index) => {
    if (readOnly) return;
    setComplementAnamnese((current) => (
      Array.isArray(current) ? current : []
    ).filter((_, itemIndex) => itemIndex !== index));
  };

  return (
    <Grid container spacing={2}>
      <Grid xs={12}>
        <Stack direction="row" justifyContent="flex-end">
          <Button
            size="sm"
            variant="soft"
            color="neutral"
            startDecorator={<Printer size={16} />}
            onClick={onPrint}
          >
            Imprimer
          </Button>
        </Stack>
      </Grid>

      {header ? (
        <Grid xs={12}>
          {header}
        </Grid>
      ) : null}

      <Grid xs={12}>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <FormControl>
            <FormLabel sx={{ fontWeight: 700 }}>
              {isBedside ? 'Motif du tour de salle' : 'Motif de consultation'}
            </FormLabel>
            <Textarea
              minRows={3}
              placeholder={isBedside
                ? 'Motif de cette visite au chevet…'
                : 'Décrivez le motif principal de la consultation…'}
              value={motif}
              onChange={(event) => setMotif(event.target.value)}
              readOnly={readOnly}
            />
          </FormControl>
        </Card>
      </Grid>

      <Grid xs={12}>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <FormControl>
            <FormLabel sx={{ fontWeight: 700 }}>
              {isBedside ? 'Évolution depuis le dernier passage' : 'Histoire de la maladie'}
            </FormLabel>
            <Textarea
              minRows={4}
              placeholder={isBedside
                ? 'État actuel, réponse au traitement, nouveaux signes, ce qui s’améliore ou s’aggrave…'
                : 'Décrivez l\'anamnèse…'}
              value={histoireMaladie}
              onChange={(event) => setHistoireMaladie(event.target.value)}
              readOnly={readOnly}
            />
          </FormControl>
        </Card>
      </Grid>

      {!isBedside ? (
      <Grid xs={12}>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <FormLabel sx={{ fontWeight: 700, mb: 1 }}>Complément d&apos;anamnèse</FormLabel>
          {!readOnly && (
            <Stack direction="row" spacing={1} sx={{ mb: 1.5 }}>
              <Input
                size="sm"
                placeholder="Ajouter un élément…"
                value={anamnesisInput}
                onChange={(event) => setAnamnesisInput(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault();
                    handleAddAnamnesis();
                  }
                }}
                sx={{ flex: 1 }}
              />
              <Button size="sm" variant="soft" startDecorator={<Plus size={16} />} onClick={handleAddAnamnesis}>
                Ajouter
              </Button>
            </Stack>
          )}
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            {(complementAnamnese || []).length > 0
              ? (complementAnamnese || []).map((item, index) => (
                <Chip
                  key={`${item}-${index}`}
                  size="md"
                  variant="soft"
                  color="primary"
                  endDecorator={
                    readOnly ? null : (
                      <ChipDelete
                        onDelete={(event) => {
                          event?.stopPropagation?.();
                          handleRemoveAnamnesis(index);
                        }}
                      />
                    )
                  }
                  sx={{ fontWeight: 600 }}
                >
                  {item}
                </Chip>
              ))
              : (
                <Typography level="body-sm" sx={{ color: 'neutral.400' }}>
                  Aucun élément ajouté.
                </Typography>
              )}
          </Stack>
        </Card>
      </Grid>
      ) : null}

      <Grid xs={12}>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <Stack spacing={2}>
            <Box>
              <Typography level="title-md" sx={{ fontWeight: 700 }}>Examen physique</Typography>
              <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                {isBedside
                  ? 'Examen au chevet — renseignez l’évolution et les sections concernées.'
                  : 'Grille structurée tête-pieds — renseignez les sections concernées.'}
              </Typography>
            </Box>
            <PhysicalExamForm
              physicalExam={physicalExam}
              setPhysicalExam={setPhysicalExam}
              readOnly={readOnly}
              patientGender={patientGender}
            />
            <FormControl>
              <FormLabel sx={{ fontWeight: 600 }}>Observations complémentaires</FormLabel>
              <Textarea
                minRows={2}
                placeholder="Notes libres, éléments non couverts par la grille…"
                value={physicalExamText}
                onChange={(event) => setPhysicalExamText(event.target.value)}
                readOnly={readOnly}
              />
            </FormControl>
          </Stack>
        </Card>
      </Grid>

      <Grid xs={12}>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <FormControl>
            <FormLabel sx={{ fontWeight: 700 }}>
              {isBedside ? 'Conduite à tenir au lit' : 'Plan de suivi'}
            </FormLabel>
            <Textarea
              minRows={3}
              placeholder={isBedside
                ? 'Suite de l’hospitalisation, surveillance, ajustements…'
                : 'Conduite à tenir, suivi prévu…'}
              value={conduireATenir}
              onChange={(event) => setConduireATenir(event.target.value)}
              readOnly={readOnly}
            />
          </FormControl>
        </Card>
      </Grid>
    </Grid>
  );
}
