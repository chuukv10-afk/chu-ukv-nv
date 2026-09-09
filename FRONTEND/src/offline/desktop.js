export function isDesktopApp() {
  return typeof window !== "undefined" && Boolean(window.electronAPI?.isDesktop);
}

export async function loadDesktopConfig() {
  if (!isDesktopApp()) return null;
  const config = await window.electronAPI.getAppConfig();
  return config || null;
}

export async function isDesktopFirstRun() {
  if (!isDesktopApp()) return false;
  return Boolean(await window.electronAPI.isFirstRun());
}
