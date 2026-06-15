import {
  BadgeCheck, BadgePercent, Bell, Building2, Calendar, CheckCircle2, Clock, Eye, Flame,
  Gift, GraduationCap, Heart, Lightbulb, Megaphone, Rocket, ShieldCheck, Sparkles, Star,
  Tag, ThumbsUp, Ticket, TrendingUp, Trophy, UserPlus, Users, Zap, type LucideIcon,
} from 'lucide-react';

/** Badge icon key → lucide component. Keys mirror the Blade x-icon set + BadgeIcons.php. */
export const BADGE_ICON_MAP: Record<string, LucideIcon> = {
  'star-solid': Star,
  'check-circle': CheckCircle2,
  'check-badge': BadgeCheck,
  'shield-check': ShieldCheck,
  trophy: Trophy,
  fire: Flame,
  'trending-up': TrendingUp,
  'rocket-launch': Rocket,
  bolt: Zap,
  sparkles: Sparkles,
  'heart-solid': Heart,
  'hand-thumb-up': ThumbsUp,
  users: Users,
  'user-plus': UserPlus,
  eye: Eye,
  calendar: Calendar,
  clock: Clock,
  bell: Bell,
  tag: Tag,
  'receipt-percent': BadgePercent,
  gift: Gift,
  ticket: Ticket,
  megaphone: Megaphone,
  'academic-cap': GraduationCap,
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
