import { createContext, useContext, useMemo, type ReactNode } from 'react';

import { createLandingPagesApi, landingPagesApi, type LandingPagesApi } from './api/landing-pages.api';

/**
 * The landing-page feature is rendered in two panels — the super-admin and the
 * per-complex dashboard — over the same components and hooks. This scope carries
 * the per-panel differences (API root, query-cache namespace, route base, and
 * the permission keys / UI affordances) so the shared code stays branch-free
 * except where a panel genuinely diverges (the approval gate). Default = admin,
 * so existing admin pages need no provider.
 */
export type LandingScopeName = 'admin' | 'clinic';

export interface LandingScopeValue {
  scope: LandingScopeName;
  api: LandingPagesApi;
  /** Query-key namespace so admin + clinic caches never collide. */
  keyBase: readonly string[];
  /** Router base for navigation (index ↔ editor). */
  basePath: string;
  /** Permission keys for the create + delete affordances in this panel. */
  canCreate: string;
  canDelete: string;
}

const ADMIN_SCOPE: LandingScopeValue = {
  scope: 'admin',
  api: landingPagesApi,
  keyBase: ['admin', 'landing-pages'],
  basePath: '/admin/landing-pages',
  canCreate: 'landing_pages.create',
  canDelete: 'landing_pages.delete',
};

const CLINIC_SCOPE: LandingScopeValue = {
  scope: 'clinic',
  api: createLandingPagesApi('/clinic/landing-pages'),
  keyBase: ['clinic', 'landing-pages'],
  basePath: '/clinic/landing-pages',
  canCreate: 'landing_pages.manage',
  canDelete: 'landing_pages.manage',
};

const LandingScopeContext = createContext<LandingScopeValue>(ADMIN_SCOPE);

export function LandingScopeProvider({ scope, children }: { scope: LandingScopeName; children: ReactNode }) {
  const value = useMemo(() => (scope === 'clinic' ? CLINIC_SCOPE : ADMIN_SCOPE), [scope]);
  return <LandingScopeContext.Provider value={value}>{children}</LandingScopeContext.Provider>;
}

export function useLandingScope() {
  return useContext(LandingScopeContext);
}
