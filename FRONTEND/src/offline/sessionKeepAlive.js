import { AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { decodeJwtExp } from './jwtExp.js';
import { refreshAccessToken } from './tokenRefresh.js';

const ACTIVITY_WINDOW_MS = 15 * 60 * 1000;
const REFRESH_BEFORE_EXP_MS = 10 * 60 * 1000;
const TICK_MS = 60 * 1000;

export function startSessionKeepAlive() {
  if (typeof window === 'undefined') {
    return () => {};
  }

  let lastActivity = Date.now();
  const markActivity = () => {
    lastActivity = Date.now();
  };

  const events = ['pointerdown', 'keydown', 'click', 'touchstart'];
  events.forEach((name) => window.addEventListener(name, markActivity, { passive: true }));

  const tick = async () => {
    const token = localStorage.getItem(AUTH_TOKEN_KEY);
    if (!token) return;
    const recentlyActive = Date.now() - lastActivity < ACTIVITY_WINDOW_MS;
    if (!recentlyActive) return;
    const expMs = decodeJwtExp(token);
    if (expMs != null && expMs - Date.now() > REFRESH_BEFORE_EXP_MS) {
      return;
    }
    await refreshAccessToken();
  };

  const timer = window.setInterval(tick, TICK_MS);
  tick();

  return () => {
    window.clearInterval(timer);
    events.forEach((name) => window.removeEventListener(name, markActivity));
  };
}
