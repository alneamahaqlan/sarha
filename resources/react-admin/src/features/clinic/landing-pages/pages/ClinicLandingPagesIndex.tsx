import { LandingScopeProvider } from '@/features/landing-pages/scope';
import { LandingPagesIndex } from '@/features/landing-pages/pages/LandingPagesIndex';

/**
 * The complex-facing landing-pages list. Reuses the shared admin index inside a
 * `clinic` scope so the API root, route base, permission keys, and the
 * approval-status column/filter all switch automatically (see LandingScope).
 */
export function ClinicLandingPagesIndex() {
  return (
    <LandingScopeProvider scope="clinic">
      <LandingPagesIndex />
    </LandingScopeProvider>
  );
}
