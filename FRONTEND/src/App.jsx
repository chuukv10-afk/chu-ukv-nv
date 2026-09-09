import { useEffect, useState } from 'react';
import AppRouter from './routes/AppRouter.jsx';
import AppSnackbar from './components/ui/AppSnackbar.jsx';
import LoadingSpinner from './components/ui/LoadingSpinner.jsx';
import OfflineProvider from './offline/OfflineProvider.jsx';
import SetupPage from './pages/desktop/SetupPage.jsx';
import { setApiBaseUrl } from './constants/apiConfig.js';
import { isDesktopApp, isDesktopFirstRun, loadDesktopConfig } from './offline/desktop.js';

function App() {
  const [boot, setBoot] = useState(isDesktopApp() ? 'loading' : 'ready');

  useEffect(() => {
    if (!isDesktopApp()) return undefined;
    let cancelled = false;
    (async () => {
      if (await isDesktopFirstRun()) {
        if (!cancelled) setBoot('setup');
        return;
      }
      const config = await loadDesktopConfig();
      if (config?.apiBaseUrl) {
        setApiBaseUrl(config.apiBaseUrl);
      }
      if (!cancelled) setBoot('ready');
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  if (boot === 'loading') {
    return <LoadingSpinner fullScreen message="Ouverture du poste pharmacie..." />;
  }

  if (boot === 'setup') {
    return (
      <SetupPage
        onDone={() => {
          window.location.reload();
        }}
      />
    );
  }

  return (
    <OfflineProvider>
      <AppRouter />
      <AppSnackbar />
    </OfflineProvider>
  );
}

export default App;
