import { LandingScopeProvider } from '@/features/landing-pages/scope';
import { LandingPageEditor } from '@/features/landing-pages/pages/LandingPageEditor';

/**
 * The complex-facing landing-page editor. Reuses the shared admin editor inside
 * a `clinic` scope, which swaps the status badge for the approval badge + the
 * "submit for approval" action and hides the page-type/entity controls.
 */
export function ClinicLandingPageEditor() {
  return (
    <LandingScopeProvider scope="clinic">
      <LandingPageEditor />
    </LandingScopeProvider>
  );
}
