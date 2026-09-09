import { useEffect, useRef, useState } from 'react';
import { Alert, Button, Stack, Typography } from '@mui/joy';
import { Camera, CameraOff } from 'lucide-react';

function extractCode(raw) {
  const value = String(raw ?? '').trim();
  if (!value) return '';
  try {
    const url = new URL(value);
    return url.searchParams.get('code') || url.pathname.split('/').filter(Boolean).pop() || value;
  } catch {
    return value;
  }
}

export default function QrScannerPanel({ onDetected, disabled = false }) {
  const videoRef = useRef(null);
  const streamRef = useRef(null);
  const timerRef = useRef(null);
  const [active, setActive] = useState(false);
  const [error, setError] = useState('');
  const supported = typeof window !== 'undefined' && 'BarcodeDetector' in window;

  useEffect(() => () => stop(), []);

  const stop = () => {
    if (timerRef.current) {
      window.clearInterval(timerRef.current);
      timerRef.current = null;
    }
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
    if (videoRef.current) {
      videoRef.current.srcObject = null;
    }
    setActive(false);
  };

  const start = async () => {
    setError('');
    if (!supported) {
      setError('Le scan caméra n’est pas disponible sur ce navigateur. Saisissez le code inventaire.');
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }
      setActive(true);
      const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
      timerRef.current = window.setInterval(async () => {
        if (!videoRef.current || videoRef.current.readyState < 2) return;
        try {
          const codes = await detector.detect(videoRef.current);
          const first = codes[0]?.rawValue;
          const code = extractCode(first);
          if (code) {
            stop();
            onDetected(code);
          }
        } catch {
          // keep scanning
        }
      }, 350);
    } catch {
      setError('Caméra inaccessible. Autorisez l’accès ou saisissez le code.');
      stop();
    }
  };

  return (
    <Stack spacing={1.25}>
      <video
        ref={videoRef}
        muted
        playsInline
        style={{
          width: '100%',
          maxHeight: 280,
          borderRadius: 12,
          background: '#111',
          display: active ? 'block' : 'none',
          objectFit: 'cover',
        }}
      />
      {error ? <Alert color="warning" variant="soft">{error}</Alert> : null}
      {!supported && !error ? (
        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
          Scan caméra non disponible ici. Utilisez la recherche par code.
        </Typography>
      ) : null}
      <Button
        variant={active ? 'outlined' : 'solid'}
        color={active ? 'neutral' : 'primary'}
        startDecorator={active ? <CameraOff size={16} /> : <Camera size={16} />}
        onClick={active ? stop : start}
        disabled={disabled}
      >
        {active ? 'Arrêter le scan' : 'Scanner un QR code'}
      </Button>
    </Stack>
  );
}
