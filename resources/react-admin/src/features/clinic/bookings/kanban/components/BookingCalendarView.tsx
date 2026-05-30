import { useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { useKanbanBoard } from '../hooks';
import { KANBAN_COLUMNS, type KanbanCard, type KanbanFilters } from '../types';

interface Props {
  filters: KanbanFilters;
  onOpenCard: (card: KanbanCard) => void;
}

const DAY_START = 8;  // 08:00
const DAY_END   = 21; // 21:00 (exclusive top)

function sameDate(a: Date, b: Date) {
  return a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate();
}

function fmtDateHeader(d: Date, locale: string) {
  return d.toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  });
}

const COLUMN_TONES: Record<string, string> = {
  new:       'border-blue-300 bg-blue-50 text-blue-800',
  confirmed: 'border-emerald-300 bg-emerald-50 text-emerald-800',
  completed: 'border-violet-300 bg-violet-50 text-violet-800',
  cancelled: 'border-rose-300 bg-rose-50 text-rose-800',
};

export function BookingCalendarView({ filters, onOpenCard }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [day, setDay] = useState<Date>(() => {
    const d = new Date(); d.setHours(0, 0, 0, 0); return d;
  });

  // Fetch all bookings within the day window. Reuses the Kanban board
  // endpoint with date_from/date_to set to the selected day so the
  // backend short-circuits to a manageable slice.
  const isoDay = day.toISOString().slice(0, 10);
  const dayFilters = useMemo<KanbanFilters>(() => ({
    ...filters,
    date_from: isoDay,
    date_to:   isoDay,
  }), [filters, isoDay]);

  const { data, isLoading } = useKanbanBoard(dayFilters);

  const allCards = useMemo(() => {
    if (!data) return [] as KanbanCard[];
    return KANBAN_COLUMNS.flatMap((c) => data[c]?.items ?? []);
  }, [data]);

  // Bucket cards into hour slots; cards without appointment_at sit in
  // a separate "غير محدّد الموعد" tray at the top.
  const { byHour, unscheduled } = useMemo(() => {
    const byHour: Record<number, KanbanCard[]> = {};
    const unscheduled: KanbanCard[] = [];
    for (const c of allCards) {
      if (!c.appointment_at) { unscheduled.push(c); continue; }
      const dt = new Date(c.appointment_at);
      if (!sameDate(dt, day)) continue;
      const h = dt.getHours();
      (byHour[h] ||= []).push(c);
    }
    return { byHour, unscheduled };
  }, [allCards, day]);

  const hours = Array.from({ length: DAY_END - DAY_START + 1 }, (_, i) => DAY_START + i);

  function move(days: number) {
    const next = new Date(day); next.setDate(next.getDate() + days); setDay(next);
  }
  function today() {
    const t = new Date(); t.setHours(0, 0, 0, 0); setDay(t);
  }

  return (
    <div className="flex h-[calc(100vh-280px)] min-h-[480px] flex-col overflow-hidden rounded-lg border border-[var(--color-border)] bg-white">
      <div className="flex items-center justify-between border-b border-[var(--color-border)] px-3 py-2">
        <div className="flex items-center gap-1.5">
          <CalendarIcon className="h-4 w-4 text-[var(--color-muted-foreground)]" />
          <span className="text-sm font-semibold">{fmtDateHeader(day, locale)}</span>
        </div>
        <div className="flex items-center gap-1">
          <Button size="sm" variant="outline" onClick={() => move(-1)} className="h-7 px-2">
            <ChevronLeft className="h-3.5 w-3.5" />
          </Button>
          <Button size="sm" variant="outline" onClick={today} className="h-7 px-2 text-[11px]">
            {t('clinic_bookings_kanban.calendar.today')}
          </Button>
          <Button size="sm" variant="outline" onClick={() => move(1)} className="h-7 px-2">
            <ChevronRight className="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        {isLoading && <div className="p-4 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>}

        {unscheduled.length > 0 && (
          <div className="border-b border-[var(--color-border)] bg-amber-50/40 p-3">
            <div className="mb-1.5 text-[10px] font-semibold uppercase text-amber-700">
              {t('clinic_bookings_kanban.calendar.unscheduled')} ({unscheduled.length})
            </div>
            <div className="flex flex-wrap gap-1.5">
              {unscheduled.slice(0, 12).map((card) => (
                <CalendarChip key={card.id} card={card} onOpen={onOpenCard} />
              ))}
              {unscheduled.length > 12 && (
                <span className="text-[10px] text-[var(--color-muted-foreground)]">+{unscheduled.length - 12}</span>
              )}
            </div>
          </div>
        )}

        <div>
          {hours.map((h) => {
            const cards = byHour[h] ?? [];
            return (
              <div key={h} className="flex items-start gap-3 border-b border-[var(--color-border)] px-3 py-2">
                <div className="w-12 shrink-0 pt-0.5 text-end text-[11px] font-medium text-[var(--color-muted-foreground)]">
                  {String(h).padStart(2, '0')}:00
                </div>
                <div className="flex flex-1 flex-wrap gap-1.5">
                  {cards.length === 0 ? (
                    <div className="text-[10px] text-[var(--color-muted-foreground)]">—</div>
                  ) : (
                    cards.map((card) => <CalendarChip key={card.id} card={card} onOpen={onOpenCard} />)
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

function CalendarChip({ card, onOpen }: { card: KanbanCard; onOpen: (c: KanbanCard) => void }) {
  const time = card.appointment_at
    ? new Date(card.appointment_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
    : null;
  return (
    <button
      type="button"
      onClick={() => onOpen(card)}
      className={`inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] transition hover:shadow ${COLUMN_TONES[card.kanban_column]}`}
    >
      {time && <span className="font-mono text-[10px] opacity-80">{time}</span>}
      <span className="truncate font-medium">{card.customer_name}</span>
      {card.service && <span className="truncate opacity-80">· {card.service.name}</span>}
      {card.auto_tags.is_vip && <Badge variant="gold" className="px-1 text-[9px]">VIP</Badge>}
    </button>
  );
}
