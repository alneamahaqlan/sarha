/**
 * React mirror of app/Support/CategoryIcons.php — the built-in specialization
 * icon library. Keep the keys + SVG path data identical to the PHP source so
 * the admin picker preview matches the public rendering exactly.
 *
 * A category's `icon` value is EITHER one of these keys OR an uploaded image
 * path (e.g. "category-icons/abc.png").
 */
import { assetUrl } from '@/lib/assets';

export const CATEGORY_ICON_PATHS: Record<string, string> = {
  // Core medical specialties (original set)
  med: '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
  dental: '<path d="M7.5 3C5.5 3 4 4.6 4 6.8c0 1.2.3 2.3.6 3.8.3 1.6.4 3.4.9 5 .3 1 .6 2.4 1.4 2.4.9 0 1-1.5 1.3-2.6.2-.8.5-1.6 1.3-1.6s1.1.8 1.3 1.6c.3 1.1.4 2.6 1.3 2.6.8 0 1.1-1.4 1.4-2.4.5-1.6.6-3.4.9-5 .3-1.5.6-2.6.6-3.8C20 4.6 18.5 3 16.5 3c-1.6 0-2.7 1-3.5 1-.8 0-1.9-1-3.5-1z"/>',
  derma: '<path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>',
  eye: '<path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
  women: '<circle cx="12" cy="8" r="5"/><path d="M12 13v8M8.5 18h7"/>',
  pediatrics: '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4 4 0 0 0 7 0M9 10h.01M15 10h.01"/>',
  bone: '<path d="M17 10c.7-.7 1.69 0 2.5 0a2.5 2.5 0 1 0 0-5 .5.5 0 0 1-.5-.5 2.5 2.5 0 1 0-5 0c0 .81.7 1.8 0 2.5l-7 7c-.7.7-1.69 0-2.5 0a2.5 2.5 0 0 0 0 5c.28 0 .5.22.5.5a2.5 2.5 0 1 0 5 0c0-.81-.7-1.8 0-2.5Z"/>',
  cardiology: '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 4.07 2.86 6.93 5.5 9.06M21 8.25c0 1.2-.18 2.27-.5 3.25h-4l-2 3-3-6-2 3H6.5"/>',
  internal: '<path d="M5 3v5a4 4 0 0 0 8 0V3"/><path d="M9 16.5a4.5 4.5 0 0 0 9 0V14"/><circle cx="18" cy="11.5" r="2.5"/>',
  ent: '<path d="M6 8.5a5.5 5.5 0 1 1 11 0c0 2.6-1.5 3.9-2.8 5.2-1 1-1.7 1.8-1.7 3.3a2.5 2.5 0 0 1-5 0"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 1.5-1.5 2-1.5 3"/>',
  neuro: '<path d="M12 4.5v15M9.5 5A3.5 3.5 0 0 0 6 8.5 3 3 0 0 0 5 14a3 3 0 0 0 4 2.8M14.5 5A3.5 3.5 0 0 1 18 8.5 3 3 0 0 1 19 14a3 3 0 0 1-4 2.8"/>',
  nerves: '<path d="M13 2 4.5 12.6a.5.5 0 0 0 .4.8H11l-1 8 8.5-10.6a.5.5 0 0 0-.4-.8H12z"/>',
  nutrition: '<path d="M12 8c-1-2.5-3.2-3.4-5-2.8C5 6 4 8.2 4.6 11c.5 2.4 2 5.2 3.9 6.6 1.1.8 2.3.8 3.5 0M12 8c1-2.5 3.2-3.4 5-2.8C19 6 20 8.2 19.4 11c-.5 2.4-2 5.2-3.9 6.6-1.1.8-2.3.8-3.5 0M12 8V5c0-1.1.9-2 2-2"/>',
  physiotherapy: '<path d="M5 8v8M3 9v6M19 8v8M21 9v6M5 12h14"/>',
  lab: '<path d="M9 3v5.5L4.5 17A2 2 0 0 0 6.3 20h11.4a2 2 0 0 0 1.8-3L15 8.5V3M8 3h8M8.5 14h7"/>',
  testtube: '<path d="M14.5 2v17.5a2.5 2.5 0 0 1-5 0V2M8.5 2h7M14.5 15h-5"/>',
  radiology: '<path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2M7 12h10"/>',
  urology: '<path d="M12 3s6 6.4 6 11a6 6 0 0 1-12 0c0-4.6 6-11 6-11z"/>',
  surgery: '<path d="M3 21l5.5-5.5M8.5 15.5 18 6a2.1 2.1 0 0 0-3-3L5.5 12.5z"/>',
  cosmetic: '<path d="m18 2 4 4M17 7l3-3M19 9 8.7 19.3a2.4 2.4 0 0 1-3.4 0l-.6-.6a2.4 2.4 0 0 1 0-3.4L15 5M9 11l4 4M5 19l-3 3M14 4l6 6"/>',
  family: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
  obesity: '<path d="M12 3v18M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2M7 21h10M16 16l3-8 3 8a3.6 3.6 0 0 1-6 0ZM2 16l3-8 3 8a3.6 3.6 0 0 1-6 0Z"/>',

  // 20 extra icons
  stethoscope: '<path d="M4 3v6a5 5 0 0 0 10 0V3M4 3H2M14 3h-2M9 18a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="14" r="2"/>',
  pill: '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/>',
  capsule: '<path d="M14.5 3.5a4.95 4.95 0 0 1 7 7l-8 8a4.95 4.95 0 0 1-7-7zM7 9l8 8"/>',
  gauge: '<path d="m12 14 4-4M3.34 19a10 10 0 1 1 17.32 0z"/>',
  pulse: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
  thermometer: '<path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/>',
  microscope: '<path d="M6 18h8M3 22h18M14 22a7 7 0 1 0 0-14h-1M9 14h2M15 9V6a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v8a2 2 0 0 0 2 2"/>',
  cross: '<path d="M9 3a1 1 0 0 0-1 1v4H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h4v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4h4a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1h-4V4a1 1 0 0 0-1-1Z"/>',
  shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1Z"/><path d="m9 12 2 2 4-4"/>',
  dna: '<path d="M4 3c0 6 12 6 12 12M16 3c0 1.5-.7 2.7-1.7 3.7M4 21c0-1.5.7-2.7 1.7-3.7M20 21c0-6-12-6-12-12M6 5h6M12 19h6M7.5 9h5M11.5 15h5"/>',
  bandage: '<path d="M4.93 13.41 13.41 4.93a4 4 0 0 1 5.66 5.66l-8.48 8.48a4 4 0 0 1-5.66-5.66Z"/><path d="M10 10.5h.01M13.5 14h.01M11.5 8.5h.01M15 12h.01"/>',
  rehab: '<circle cx="12" cy="4" r="1.5"/><path d="m9 17 3-2 3 2M8 8h8M12 6.5V13"/>',
  record: '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 14h6M12 11v6"/>',
  hand: '<path d="M11 11V5.5a1.5 1.5 0 0 1 3 0V11M14 10.5V4a1.5 1.5 0 0 1 3 0v8M8 11.5V7a1.5 1.5 0 0 1 3 0v4M17 8.5a1.5 1.5 0 0 1 3 0V14a7 7 0 0 1-7 7h-1.5a6 6 0 0 1-4.24-1.76L3.5 16a1.7 1.7 0 0 1 2.4-2.4L8 15.5"/>',
  lungs: '<path d="M12 4a2 2 0 0 0-2 2v6M12 4a2 2 0 0 1 2 2v6M7 9c-2 0-3 1.5-3 3 0 .5.5 7 3 8 1.6.5 3-.7 3-2.5V12c0-1.5-1.5-3-3-3ZM17 9c2 0 3 1.5 3 3 0 .5-.5 7-3 8-1.6.5-3-.7-3-2.5V12c0-1.5 1.5-3 3-3Z"/>',
  glasses: '<circle cx="6" cy="15" r="3.5"/><circle cx="18" cy="15" r="3.5"/><path d="M9.5 15a2.5 2.5 0 0 1 5 0M2.5 13 5 7.5C5.6 6.5 6.2 6 7.5 6M21.5 13 19 7.5C18.4 6.5 17.8 6 16.5 6"/>',
  wellness: '<circle cx="12" cy="12" r="9"/><path d="M8.5 14a4 4 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/>',
  hospital: '<path d="M6 22V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v18M2 22h20M12 6v4M10 8h4M9 14h6M9 18h6"/>',
  ambulance: '<path d="M10 10H6M8 8v4M3 6a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v10H2V6ZM16 9h3.5a1 1 0 0 1 .9.55L22 13v3h-6V9Z"/><circle cx="6.5" cy="18.5" r="1.5"/><circle cx="17.5" cy="18.5" r="1.5"/>',
  mask: '<path d="M4 9c0-1 1-1.7 2-1.7 2 0 4 1 6 1s4-1 6-1c1 0 2 .7 2 1.7v3a6 6 0 0 1-12 0M4 9v3a6 6 0 0 0 .6 2.6M2 10l2 1M22 10l-2 1"/>',
};

export const BUILT_IN_ICON_KEYS = Object.keys(CATEGORY_ICON_PATHS);

const EMOJI_TO_KEY: Record<string, string> = {
  '🦷': 'dental', '✨': 'derma', '🧴': 'derma', '👁': 'eye', '👁️': 'eye',
  '🌸': 'women', '👶': 'pediatrics', '🧒': 'pediatrics', '🦴': 'bone',
  '❤': 'cardiology', '❤️': 'cardiology', '🩺': 'internal', '👂': 'ent',
  '🧠': 'neuro', '⚡': 'nerves', '🥗': 'nutrition', '💪': 'physiotherapy',
  '🔬': 'lab', '🧪': 'testtube', '📡': 'radiology', '📷': 'radiology',
  '🧫': 'urology', '🔪': 'surgery', '💉': 'cosmetic', '👨‍👩‍👧': 'family',
  '👪': 'family', '⚖️': 'obesity', '⚖': 'obesity',
};

export function isBuiltInIcon(icon: string | null | undefined): boolean {
  return !!icon && Object.prototype.hasOwnProperty.call(CATEGORY_ICON_PATHS, icon);
}

/** A non-empty icon that isn't a built-in key is an uploaded image path. */
export function isUploadedIcon(icon: string | null | undefined): boolean {
  return !!icon && !isBuiltInIcon(icon);
}

export function keyForEmoji(emoji: string | null | undefined): string {
  return EMOJI_TO_KEY[(emoji ?? '').trim()] ?? 'med';
}

/**
 * Render a category's resolved visual: uploaded image, built-in vector, or the
 * emoji fallback — mirroring the Blade <x-category-icon> precedence.
 */
export function CategoryIcon({
  icon, emoji, className = 'w-5 h-5',
}: {
  icon?: string | null;
  emoji?: string | null;
  className?: string;
}) {
  if (isUploadedIcon(icon)) {
    return <img src={assetUrl(icon) ?? ''} alt="" aria-hidden className={`${className} object-contain`} />;
  }
  const key = isBuiltInIcon(icon) ? (icon as string) : keyForEmoji(emoji);
  return (
    <svg
      className={className}
      fill="none"
      stroke="currentColor"
      strokeWidth={1.7}
      strokeLinecap="round"
      strokeLinejoin="round"
      viewBox="0 0 24 24"
      aria-hidden
      dangerouslySetInnerHTML={{ __html: CATEGORY_ICON_PATHS[key] }}
    />
  );
}
