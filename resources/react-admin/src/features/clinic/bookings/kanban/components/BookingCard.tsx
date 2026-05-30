import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Check, Clock, User, UserCheck } from 'lucide-react';
import { toast } from 'sonner';
import { useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import { extractMessage } from '@/lib/api-client';
import { bookingKanbanApi } from '../api';
import { CardAutoTags } from './CardAutoTags';
import { CardSuggestions } from './CardSuggestions';
import { CardHeatBar } from './CardHeatBar';
import type { KanbanCard, TagDto } from '../types';

interface Props {
  card: KanbanCard;
  onOpen: (card: KanbanCard) => void;
}

function fmtAppt(iso: string | null, locale: string) {
  if (!iso) return null;
  const d = new Date(iso);
  return d.toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function TagChip({ tag }: { tag: TagDto }) {
  const tone = {
    rose:    'bg-rose-100 text-rose-700 border-rose-200',
    amber:   'bg-amber-100 text-amber-700 border-amber-200',
    emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    sky:     'bg-sky-100 text-sky-700 border-sky-200',
    violet:  'bg-violet-100 text-violet-700 border-violet-200',
    slate:   'bg-slate-100 text-slate-700 border-slate-200',
  }[tag.color];
  return <span className={`inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-medium ${tone}`}>{tag.label}</span>;
}

export function BookingCard({ card, onOpen }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const qc = useQueryClient();
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: card.id,
    data: { card },
  });

  // Quick toggle: new → contacted (sub-status inside the "جديد" column).
  // Doesn't move the card to another column; just flips the sub-badge.
  async function markContacted(e: React.MouseEvent) {
    e.stopPropagation();
    try {
      await bookingKanbanApi.updateStatus(card.id, { status: 'contacted' });
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings'] });
      toast.success(t('clinic_bookings_kanban.card.marked_contacted'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.4 : 1,
  };

  const allTags = [...card.customer_tags, ...card.tags];
  const subBadgeLabel = card.sub_badge ? t(`clinic_bookings_kanban.sub_badge.${card.sub_badge}`) : null;

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      onClick={(e) => {
        if (isDragging) return;
        // Don't open when the user is dragging — dnd-kit calls listeners
        // even on click; we treat any non-drag click as open.
        e.stopPropagation();
        onOpen(card);
      }}
      className="group cursor-grab overflow-hidden rounded-lg border border-[var(--color-border)] bg-white shadow-sm transition hover:border-[var(--color-primary)] hover:shadow-md active:cursor-grabbing"
    >
      <div className="space-y-2 p-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <div className="truncate text-sm font-semibold text-[var(--color-foreground)]">
              {card.customer_name}
            </div>
            <div className="truncate text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">
              {card.reference_code}
            </div>
          </div>
          {subBadgeLabel && <Badge variant="muted" className="shrink-0 px-1.5 text-[10px]">{subBadgeLabel}</Badge>}
        </div>

        <CardAutoTags tags={card.auto_tags} />

        {card.service && (
          <div className="text-xs text-[var(--color-foreground)]">{card.service.name}</div>
        )}

        {card.appointment_at && (
          <div className="flex items-center gap-1 text-xs text-[var(--color-muted-foreground)]">
            <Clock className="h-3 w-3" />
            <span>{fmtAppt(card.appointment_at, locale)}</span>
          </div>
        )}

        {allTags.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {allTags.slice(0, 4).map((tag) => <TagChip key={`${tag.scope}-${tag.id}`} tag={tag} />)}
            {allTags.length > 4 && <span className="text-[10px] text-[var(--color-muted-foreground)]">+{allTags.length - 4}</span>}
          </div>
        )}

        <CardSuggestions suggestions={card.suggestions} />

        <div className="flex items-center justify-between border-t border-[var(--color-border)] pt-2 text-[11px]">
          <div className="flex items-center gap-1 text-[var(--color-muted-foreground)]">
            {card.assignee ? (
              <>
                <UserCheck className="h-3 w-3" />
                <span className="truncate">{card.assignee.name}</span>
              </>
            ) : (
              <>
                <User className="h-3 w-3" />
                <span>{t('clinic_bookings_kanban.card.unassigned')}</span>
              </>
            )}
          </div>
          {card.status === 'new' && (
            <button
              type="button"
              onClick={markContacted}
              onPointerDown={(e) => e.stopPropagation()}
              className="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 hover:bg-emerald-100"
              title={t('clinic_bookings_kanban.card.mark_contacted')}
            >
              <Check className="h-3 w-3" />
              {t('clinic_bookings_kanban.card.mark_contacted')}
            </button>
          )}
        </div>
      </div>
      <CardHeatBar heat={card.heat} />
    </div>
  );
}
