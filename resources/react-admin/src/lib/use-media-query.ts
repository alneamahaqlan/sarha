import { useEffect, useState } from 'react';

/**
 * Subscribe to a CSS media query and re-render when it flips. Use it
 * sparingly — prefer Tailwind responsive classes for visual changes.
 * Reach for this hook only when the BEHAVIOUR has to change (e.g.,
 * skip an expensive render path on mobile, swap a Sheet for a Dialog).
 *
 * Example:
 *   const isLg = useMediaQuery('(min-width: 1024px)');
 *
 * Server-safe: returns `false` during the first render in non-browser
 * environments (Node SSR), then re-resolves on mount.
 */
export function useMediaQuery(query: string): boolean {
  const get = () =>
    typeof window === 'undefined' ? false : window.matchMedia(query).matches;

  const [matches, setMatches] = useState<boolean>(get);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const mql = window.matchMedia(query);
    const handler = (e: MediaQueryListEvent) => setMatches(e.matches);
    // Sync once on mount in case the SSR fallback diverged from reality.
    setMatches(mql.matches);
    mql.addEventListener('change', handler);
    return () => mql.removeEventListener('change', handler);
  }, [query]);

  return matches;
}
