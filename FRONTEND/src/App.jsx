import AppRouter from './routes/AppRouter.jsx';
import AppSnackbar from './components/ui/AppSnackbar.jsx';
import OfflineProvider from './offline/OfflineProvider.jsx';

function App() {
  return (
    <OfflineProvider>
      <AppRouter />
      <AppSnackbar />
    </OfflineProvider>
  );
}

export default App;
