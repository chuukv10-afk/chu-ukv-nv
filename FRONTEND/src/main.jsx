import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { CssVarsProvider } from '@mui/joy/styles';
import App from './App.jsx';
import { store } from './store/index.js';
import { joyTheme } from './theme/joyTheme.js';
import './styles/global.css';

if (!window.electronAPI) {
  import('virtual:pwa-register')
    .then(({ registerSW }) => {
      const listeners = new Set();
      let apply = () => window.location.reload();
      let current = { needRefresh: false, apply };
      const notify = (needRefresh) => {
        current = { needRefresh, apply };
        listeners.forEach((listener) => listener(current));
      };
      const updateSW = registerSW({
        immediate: true,
        onNeedRefresh() {
          apply = () => updateSW(true);
          notify(true);
        },
        onRegisteredSW(_url, registration) {
          if (registration) {
            window.setInterval(() => registration.update(), 5 * 60 * 1000);
          }
        },
      });
      window.chuUkvSwUpdate = {
        subscribe(listener) {
          listeners.add(listener);
          listener(current);
          return () => listeners.delete(listener);
        },
      };
    })
    .catch(() => {});
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <Provider store={store}>
      <CssVarsProvider theme={joyTheme} defaultMode="light" modeStorageKey="chu-ukv-theme">
        <App />
      </CssVarsProvider>
    </Provider>
  </StrictMode>,
);
