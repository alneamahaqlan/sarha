import { Crown, Repeat, Sparkles, AlertOctagon, Moon } from 'lucide-react';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useCustomersStats } from '../hooks';
import type { CustomerSegment } from '../types';

interface Props {
  onPick: (segment: CustomerSegment) => void;
}

const SEGMENTS: Array<{ key: CustomerSegment; icon: any; tone: string; titleKey: string; descKey: string }> = [
  { key: 'vip',           icon: Crown,        tone: 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100',   titleKey: 'clinic_customers.segments.vip.title',           descKey: 'clinic_customers.segments.vip.desc' },
  { key: 'repeat',        icon: Repeat,       tone: 'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100',       titleKey: 'clinic_customers.segments.repeat.title',        descKey: 'clinic_customers.segments.repeat.desc' },
  { key: 'new_month',     icon: Sparkles,     tone: 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100', titleKey: 'clinic_customers.segments.new_month.title', descKey: 'clinic_customers.segments.new_month.desc' },
  { key: 'has_complaint', icon: AlertOctagon, tone: 'border-rose-300 bg-rose-50 text-rose-800 hover:bg-rose-100',       titleKey: 'clinic_customers.segments.has_complaint.title', descKey: 'clinic_customers.segments.has_complaint.desc' },
  { key: 'inactive_90d',  icon: Moon,         tone: 'border-violet-300 bg-violet-50 text-violet-800 hover:bg-violet-100', titleKey: 'clinic_customers.segments.inactive_90d.title',  descKey: 'clinic_customers.segments.inactive_90d.desc' },
];

export function CustomersSegments({ onPick }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useCustomersStats();

  const countOf: Record<CustomerSegment, number> = {
    all:           data?.total ?? 0,
    vip:           data?.vip ?? 0,
    repeat:        data?.repeat ?? 0,
    new:           0,
    new_month:     data?.new_month ?? 0,
    has_complaint: data?.has_complaint ?? 0,
    inactive_90d:  data?.inactive_90d ?? 0,
  };

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {SEGMENTS.map(({ key, icon: Icon, tone, titleKey, descKey }) => (
        <button
          key={key}
          type="button"
          onClick={() => onPick(key)}
          className={`group flex flex-col gap-2 rounded-lg border-2 p-4 text-start transition ${tone}`}
        >
          <div className="flex items-center justify-between">
            <Icon className="h-6 w-6" />
            <span className="text-2xl font-bold tabular-nums">{isLoading ? '…' : countOf[key]}</span>
          </div>
          <div className="font-semibold">{t(titleKey)}</div>
          <div className="text-xs opacity-80">{t(descKey)}</div>
        </button>
      ))}
    </div>
  );
}
