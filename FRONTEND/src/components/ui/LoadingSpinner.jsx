import { Box, CircularProgress, Typography } from '@mui/joy';

export default function LoadingSpinner({ fullScreen = false, message }) {
  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 2,
        minHeight: fullScreen ? '100vh' : 240,
      }}
    >
      <CircularProgress />
      {message ? (
        <Typography level="body-sm" color="neutral">
          {message}
        </Typography>
      ) : null}
    </Box>
  );
}
