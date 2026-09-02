import { setToast } from '../store/ui/uiSlice.js';
import { useAppDispatch } from './useAppStore.js';

export function useToast() {
  const dispatch = useAppDispatch();

  const showToast = (message, severity = 'neutral', duration = 4000) => {
    if (!message) {
      return;
    }

    dispatch(setToast({ message, severity, duration }));
  };

  return {
    showToast,
    showSuccess: (message, duration = 4000) => showToast(message, 'success', duration),
    showError: (message, duration = 5000) => showToast(message, 'danger', duration),
    showWarning: (message, duration = 4500) => showToast(message, 'warning', duration),
    showInfo: (message, duration = 4000) => showToast(message, 'neutral', duration),
  };
}
