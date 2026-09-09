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
    .then(({ registerSW }) => registerSW({ immediate: true }))
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
