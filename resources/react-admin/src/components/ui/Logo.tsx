import { useTranslation } from 'react-i18next';

interface LogoProps {
  /** Pulsing neural-network mark — use only in AI contexts. */
  variant?: 'classic' | 'ai';
  /** Show the wordmark beside the mark. */
  withText?: boolean;
  /** Cream letter for dark backgrounds. */
  onDark?: boolean;
  /** Mark size in px. */
  size?: number;
  className?: string;
}

/**
 * Daleel brand mark — the "د" glyph with a gold quality dot (classic) or a
 * pulsing plum neural network (ai). See resources/css/brand-tokens.css.
 */
export function Logo({ variant = 'classic', withText = true, onDark = false, size = 36, className = '' }: LogoProps) {
  const { i18n } = useTranslation();
  const isAr = i18n.language?.startsWith('ar');
  const letter = onDark ? '#FAF7F2' : '#2D6A5C';
  const word = isAr ? 'دليل' : 'Daleel';
  const sub = isAr ? 'المجمعات الطبية' : 'Medical Directory';

  return (
    <span className={`inline-flex items-center gap-2.5 ${className}`}>
      {variant === 'ai' ? (
        <svg width={size} height={size} viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M 20 24 Q 20 20, 24 20 L 56 20 Q 62 20, 62 26 L 62 44 Q 62 58, 48 62 L 26 62 Q 20 62, 20 56 L 20 24 Z M 30 30 L 30 52 L 46 52 Q 52 52, 52 44 L 52 32 Q 52 30, 50 30 L 30 30 Z" fill={letter} />
          <line className="ai-line ai-line-1" x1="62" y1="22" x2="76" y2="14" stroke="#6B4985" strokeWidth="1.5" strokeLinecap="round" />
          <line className="ai-line ai-line-2" x1="62" y1="22" x2="80" y2="28" stroke="#6B4985" strokeWidth="1.5" strokeLinecap="round" />
          <line className="ai-line ai-line-3" x1="76" y1="14" x2="80" y2="28" stroke="#8B6FA8" strokeWidth="1.2" strokeLinecap="round" opacity="0.7" />
          <circle className="ai-dot ai-dot-1" cx="62" cy="22" r="4" fill="#6B4985" />
          <circle className="ai-dot ai-dot-2" cx="76" cy="14" r="3" fill="#8B6FA8" />
          <circle className="ai-dot ai-dot-3" cx="80" cy="28" r="3" fill="#8B6FA8" />
        </svg>
      ) : (
        <svg width={size} height={size} viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M 20 22 Q 20 18, 24 18 L 56 18 Q 62 18, 62 24 L 62 42 Q 62 56, 48 60 L 26 60 Q 20 60, 20 54 L 20 22 Z M 30 28 L 30 50 L 46 50 Q 52 50, 52 42 L 52 30 Q 52 28, 50 28 L 30 28 Z" fill={letter} />
          <circle cx="62" cy="22" r="5" fill="#C9A961" />
        </svg>
      )}
      {withText && (
        <span className="leading-none">
          <span className="block font-bold text-base" style={{ fontFamily: "'Readex Pro', sans-serif", color: onDark ? '#FAF7F2' : '#1F4A3F' }}>{word}</span>
          <span className="block text-[10px] tracking-[0.18em] mt-0.5" style={{ color: '#8A6A28' }}>{sub}</span>
        </span>
      )}
    </span>
  );
}
