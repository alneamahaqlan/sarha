import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import { BookingCard } from './BookingCard';
import type { BookingStage, KanbanCard, StageColor } from '../types';

interface Props {
  stage: BookingStage;
  items: KanbanCard[];
  total: number;
  onOpen: (card: KanbanCard) => void;
}

const TONES: Record<StageColor, string> = {
  rose:    'border-rose-200 bg-rose-50/40',
  amber:   'border-amber-200 bg-amber-50/40',
  emerald: 'border-emerald-200 bg-emerald-50/40',
  sky:     'border-sky-200 bg-sky-50/40',
  violet:  'border-violet-200 bg-violet-50/40',
  slate:   'border-slate-200 bg-slate-50/40',
};

const HEADER_TONES: Record<StageColor, string> = {
  rose:    'text-rose-700',
  amber:   'text-amber-700',
  emerald: 'text-emerald-700',
  sky:     'text-sky-700',
  violet:  'text-violet-700',
  slate:   'text-slate-700',
};

export function KanbanColumnView({ stage, items, total, onOpen }: Props) {
  const { t } = useTranslation();
  const { setNodeRef, isOver } = useDroppable({ id: `column:${stage.id}`, data: { stageId: stage.id } });
  const tone = TONES[stage.color] ?? TONES.slate;

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className={`flex items-center justify-between rounded-t-lg border border-b-0 px-3 py-2 ${tone}`}>
        <div className={`truncate text-sm font-semibold ${HEADER_TONES[stage.color] ?? HEADER_TONES.slate}`}>
          {stage.name}
        </div>
        <Badge variant="muted">{total}</Badge>
      </div>
      <div
        ref={setNodeRef}
        className={`flex-1 overflow-y-auto rounded-b-lg border border-t-0 ${tone} ${isOver ? 'ring-2 ring-[var(--color-primary)] ring-inset' : ''}`}
      >
        <SortableContext id={`sortable:${stage.id}`} items={items.map((i) => i.id)} strategy={verticalListSortingStrategy}>
          <div className="space-y-2 p-2">
            {items.length === 0 ? (
              <div className="rounded-md border border-dashed border-[var(--color-border)] bg-white/60 p-6 text-center text-xs text-[var(--color-muted-foreground)]">
                {t('clinic_bookings_kanban.column.empty')}
              </div>
            ) : (
              items.map((card) => <BookingCard key={card.id} card={card} onOpen={onOpen} />)
            )}
          </div>
        </SortableContext>
      </div>
    </div>
  );
}
