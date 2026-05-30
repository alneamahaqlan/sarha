import type { Heat } from '../types';

const TONES: Record<Heat, string> = {
  red: 'bg-red-500',
  yellow: 'bg-amber-400',
  green: 'bg-emerald-400',
};

export function CardHeatBar({ heat }: { heat: Heat }) {
  return <div className={`h-1 w-full rounded-b ${TONES[heat]}`} />;
}
