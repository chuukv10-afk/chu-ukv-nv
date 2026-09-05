import { Box, Typography } from '@mui/joy';
import { Construction } from 'lucide-react';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';

export default function ConsultationPlaceholderTab({ title = 'Module à venir' }) {
  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        py: 6,
        px: 2,
        textAlign: 'center',
      }}
    >
      <Box
        sx={{
          width: 56,
          height: 56,
          borderRadius: 'md',
          bgcolor: LOTRU_PRIMARY[50],
          color: LOTRU_PRIMARY[600],
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          mb: 2,
        }}
      >
        <Construction size={28} />
      </Box>
      <Typography level="title-md" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900], mb: 0.5 }}>
        {title}
      </Typography>
      <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], maxWidth: 420 }}>
        Ce module sera disponible prochainement dans l&apos;espace de consultation.
      </Typography>
    </Box>
  );
}
