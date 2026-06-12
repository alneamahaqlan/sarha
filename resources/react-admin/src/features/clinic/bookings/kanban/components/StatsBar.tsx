import { Calendar, AlertTriangle, Clock4, Target, FileWarning } from 'lucide-react';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useKanbanStats } from '../hooks';
import type { KanbanFilters } from '../types';

interface Props {
  onFilter: (patch: Partial<KanbanFilters>) => void;
}

const TONES = {
  today: 'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100',
  no_show: 'border-rose-300 bg-rose-50 text-rose-800 hover:bg-rose-100',
  urgent: 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100',
  rate: 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100',
  incomplete: 'border-orange-300 bg-orange-50 text-orange-800 hover:bg-orange-100',
};

export function StatsBar({ onFilter }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useKanbanStats();

  const items = [
    {
      key: 'today',
      icon: Calendar,
      value: isLoading ? '…' : data?.today_count ?? 0,
      label: t('clinic_bookings_kanban.stats.today'),
      onClick: () => {
        const today = new Date().toISOString().slice(0, 10);
        onFilter({ date_from: today, date_to: today });
      },
      tone: TONES.today,
    },
    {
      key: 'no_show',
      icon: AlertTriangle,
      value: isLoading ? '…' : data?.yesterday_no_show ?? 0,
      label: t('clinic_bookings_kanban.stats.no_show_yesterday'),
      onClick: () => {
        const y = new Date(); y.setDate(y.getDate() - 1);
        const d = y.toISOString().slice(0, 10);
        onFilter({ date_from: d, date_to: d });
      },
      tone: TONES.no_show,
    },
    {
      key: 'urgent',
      icon: Clock4,
      value: isLoading ? '…' : data?.needs_urgent_confirm ?? 0,
      label: t('clinic_bookings_kanban.stats.urgent_confirm'),
      onClick: () => onFilter({ auto_tag: 'urgent_confirm' }),
      tone: TONES.urgent,
    },
    {
      key: 'rate',
      icon: Target,
      value: isLoading ? '…' : data?.weekly_confirm_rate == null ? '—' : `${Math.round((data!.weekly_confirm_rate ?? 0) * 100)}%`,
      label: t('clinic_bookings_kanban.stats.weekly_rate'),
      onClick: () => onFilter({}),
      tone: TONES.rate,
    },
    {
      key: 'incomplete',
      icon: FileWarning,
      value: isLoading ? '…' : data?.incomplete_services ?? 0,
      label: t('clinic_bookings_kanban.stats.incomplete'),
      onClick: () => onFilter({ incomplete: true }),
      tone: TONES.incomplete,
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
      {items.map(({ key, icon: Icon, value, label, onClick, tone }) => (
        <button
          key={key}
          type="button"
          onClick={onClick}
          className={`flex items-center gap-3 rounded-lg border px-3 py-2.5 text-start transition ${tone}`}
        >
          <Icon className="h-5 w-5 shrink-0" />
          <div className="flex min-w-0 flex-col">
            <span className="text-lg font-semibold leading-tight">{value}</span>
            <span className="truncate text-xs leading-tight opacity-90">{label}</span>
          </div>
        </button>
      ))}
    </div>
  );
}
