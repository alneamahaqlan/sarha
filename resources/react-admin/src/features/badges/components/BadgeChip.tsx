import {
  Bell, Building2, Calendar, CheckCircle2, Clock, Eye, Flame, Heart, Lightbulb,
  Sparkles, Star, TrendingUp, Trophy, UserPlus, Users, Zap, type LucideIcon,
} from 'lucide-react';

/** Badge icon key → lucide component. Keys mirror the Blade x-icon set. */
export const BADGE_ICON_MAP: Record<string, LucideIcon> = {
  'star-solid': Star,
  'check-circle': CheckCircle2,
  bell: Bell,
  calendar: Calendar,
  users: Users,
  'user-plus': UserPlus,
  trophy: Trophy,
  fire: Flame,
  'trending-up': TrendingUp,
  sparkles: Sparkles,
  bolt: Zap,
  'heart-solid': Heart,
  eye: Eye,
  clock: Clock,
  building: Building2,
  'light-bulb': Lightbulb,
};

/** Palette key → chip classes (approximation of the public Blade palette). */
export const BADGE_COLOR_MAP: Record<string, string> = {
  gold: 'bg-amber-100 text-amber-800',
  sage: 'bg-emerald-50 text-emerald-700',
  emerald: 'bg-emerald-50 text-emerald-700',
  red: 'bg-red-50 text-red-600',
  blue: 'bg-blue-50 text-blue-700',
  amber: 'bg-amber-50 text-amber-700',
  purple: 'bg-purple-50 text-purple-700',
  sky: 'bg-sky-50 text-sky-700',
  gray: 'bg-gray-100 text-gray-600',
};

export function BadgeChip({ icon, color, label }: { icon: string; color: string; label: string }) {
  const Icon = BADGE_ICON_MAP[icon] ?? Star;
  const cls = BADGE_COLOR_MAP[color] ?? BADGE_COLOR_MAP.gold;
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>
      <Icon className="h-3.5 w-3.5" />
      {label}
    </span>
  );
}
