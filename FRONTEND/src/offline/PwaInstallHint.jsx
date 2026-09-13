import { useEffect, useState } from 'react';
import { Button, Typography } from '@mui/joy';
import { Download } from 'lucide-react';
import { isDesktopApp } from './desktop.js';

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
}

export default function PwaInstallHint() {
  const [promptEvent, setPromptEvent] = useState(null);
  const [installed, setInstalled] = useState(() => (typeof window !== 'undefined' ? isStandalone() : false));

  useEffect(() => {
    if (isDesktopApp()) return undefined;
    const onPrompt = (event) => {
      event.preventDefault();
      setPromptEvent(event);
    };
    const onInstalled = () => {
      setInstalled(true);
      setPromptEvent(null);
    };
    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);
    return () => {
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.removeEventListener('appinstalled', onInstalled);
    };
  }, []);

  if (isDesktopApp() || installed) return null;

  if (promptEvent) {
    return (
      <Button
        variant="outlined"
        color="neutral"
        startDecorator={<Download size={16} />}
        onClick={async () => {
          promptEvent.prompt();
          const choice = await promptEvent.userChoice;
          if (choice?.outcome === 'accepted') setPromptEvent(null);
        }}
      >
        Installer l’application (PWA)
      </Button>
    );
  }

  return (
    <Typography level="body-xs" sx={{ color: 'neutral.500', textAlign: 'center', maxWidth: 360 }}>
      Sur Chrome : menu ⋮ → « Installer CHU UKV » pour l’ouvrir comme une application,
      sans l’exe. Utilisez https://app.chu-ukv.cd
    </Typography>
  );
}
