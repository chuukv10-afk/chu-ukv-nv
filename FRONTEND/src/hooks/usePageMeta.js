import { useMemo } from 'react';
import { useLocation } from 'react-router-dom';
import { NAV_SECTIONS } from '../constants/navigation.js';
import { ROUTES } from '../constants/routes.js';

const ROUTE_META = NAV_SECTIONS.flatMap((section) =>
  (section.items ?? []).map((item) => ({
    path: item.to,
    title: item.label,
    section: section.title,
  })),
);

export function usePageMeta() {
  const { pathname } = useLocation();

  return useMemo(() => {
    const match = ROUTE_META.find(
      (entry) => entry.path === pathname || pathname.startsWith(`${entry.path}/`),
    );

    if (match) {
      return match;
    }

    if (pathname === ROUTES.HOME || pathname === ROUTES.DASHBOARD) {
      return { path: ROUTES.DASHBOARD, title: 'Tableau de bord', section: 'Général' };
    }

    return { path: pathname, title: 'CHU UKV', section: 'Application' };
  }, [pathname]);
}
