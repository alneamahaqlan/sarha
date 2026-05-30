import { Users, Crown, Repeat, Sparkles, AlertOctagon, Moon, BarChart3 } from 'lucide-react';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useCustomersStats } from '../hooks';
import type { CustomerSegment } from '../types';

interface Props {
  onSegmentClick: (segment: CustomerSegment) => void;
  activeSegment?: CustomerSegment;
}

const TONES = {
  all:           'border-slate-300 bg-slate-50 text-slate-800 hover:bg-slate-100',
  vip:           'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100',
  new_month:     'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100',
  has_complaint: 'border-rose-300 bg-rose-50 text-rose-800 hover:bg-rose-100',
  inactive_90d:  'border-violet-300 bg-violet-50 text-violet-800 hover:bg-violet-100',
  avg:           'border-emerald-300 bg-emerald-50 text-emerald-800',
};

export function CustomersStatsBar({ onSegmentClick, activeSegment }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useCustomersStats();

  const items = [
    { key: 'all' as const,           icon: Users,        label: t('clinic_customers.stats.total'),         value: data?.total ?? 0, tone: TONES.all,           clickable: true },
    { key: 'vip' as const,           icon: Crown,        label: t('clinic_customers.stats.vip'),           value: data?.vip ?? 0, tone: TONES.vip,           clickable: true },
    { key: 'new_month' as const,     icon: Sparkles,     label: t('clinic_customers.stats.new_month'),     value: data?.new_month ?? 0, tone: TONES.new_month,     clickable: true },
    { key: 'has_complaint' as const, icon: AlertOctagon, label: t('clinic_customers.stats.has_complaint'), value: data?.has_complaint ?? 0, tone: TONES.has_complaint, clickable: true },
    { key: 'inactive_90d' as const,  icon: Moon,         label: t('clinic_customers.stats.inactive_90d'),  value: data?.inactive_90d ?? 0, tone: TONES.inactive_90d,  clickable: true },
    { key: '_avg' as const,          icon: BarChart3,    label: t('clinic_customers.stats.avg_bookings'),  value: data?.avg_bookings ?? 0, tone: TONES.avg,           clickable: false },
  ];

  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
      {items.map(({ key, icon: Icon, label, value, tone, clickable }) => {
        const isActive = clickable && activeSegment === key;
        const Comp = clickable ? 'button' : 'div';
        return (
          <Comp
            key={key}
            type={clickable ? 'button' : undefined}
            onClick={clickable ? () => onSegmentClick(key as CustomerSegment) : undefined}
            className={`flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-start transition ${tone} ${isActive ? 'ring-2 ring-offset-1 ring-[var(--color-primary)]' : ''}`}
          >
            <Icon className="h-5 w-5 shrink-0" />
            <div className="flex min-w-0 flex-col">
              <span className="text-lg font-semibold leading-tight">{isLoading ? '…' : value}</span>
              <span className="truncate text-xs leading-tight opacity-90">{label}</span>
            </div>
          </Comp>
        );
      })}
    </div>
  );
}
