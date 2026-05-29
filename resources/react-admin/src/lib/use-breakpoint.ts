import { useMediaQuery } from './use-media-query';

/**
 * Tailwind-aligned breakpoint flags — keep the JS world in sync with
 * the classes we use in JSX. Three buckets that match the layout
 * decisions in this app:
 *
 *   isMobile  : < md  (< 768px)   — Sheet drawer, single-column forms
 *   isTablet  : md..lg (768–1023) — icon sidebar + collapsed UI
 *   isDesktop : ≥ lg  (≥ 1024px)  — full sidebar, multi-column grids
 *
 * Returned booleans are mutually exclusive: exactly one is true.
 */
export function useBreakpoint(): {
  isMobile: boolean;
  isTablet: boolean;
  isDesktop: boolean;
} {
  // Match Tailwind defaults verbatim — do NOT diverge.
  const isMdUp = useMediaQuery('(min-width: 768px)');
  const isLgUp = useMediaQuery('(min-width: 1024px)');

  return {
    isMobile: !isMdUp,
    isTablet: isMdUp && !isLgUp,
    isDesktop: isLgUp,
  };
}
