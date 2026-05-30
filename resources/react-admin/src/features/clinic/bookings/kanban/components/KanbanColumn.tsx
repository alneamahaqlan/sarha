import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import { BookingCard } from './BookingCard';
import type { KanbanCard, KanbanColumn as Col } from '../types';

interface Props {
  column: Col;
  items: KanbanCard[];
  total: number;
  onOpen: (card: KanbanCard) => void;
}

const TONES: Record<Col, string> = {
  new:       'border-blue-200 bg-blue-50/40',
  confirmed: 'border-emerald-200 bg-emerald-50/40',
  completed: 'border-violet-200 bg-violet-50/40',
  cancelled: 'border-rose-200 bg-rose-50/40',
};

const HEADER_TONES: Record<Col, string> = {
  new:       'text-blue-700',
  confirmed: 'text-emerald-700',
  completed: 'text-violet-700',
  cancelled: 'text-rose-700',
};

export function KanbanColumnView({ column, items, total, onOpen }: Props) {
  const { t } = useTranslation();
  const { setNodeRef, isOver } = useDroppable({ id: `column:${column}`, data: { column } });

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className={`flex items-center justify-between rounded-t-lg border border-b-0 px-3 py-2 ${TONES[column]}`}>
        <div className={`text-sm font-semibold ${HEADER_TONES[column]}`}>
          {t(`clinic_bookings_kanban.column.${column}`)}
        </div>
        <Badge variant="muted">{total}</Badge>
      </div>
      <div
        ref={setNodeRef}
        className={`flex-1 overflow-y-auto rounded-b-lg border border-t-0 ${TONES[column]} ${isOver ? 'ring-2 ring-[var(--color-primary)] ring-inset' : ''}`}
      >
        <SortableContext id={`sortable:${column}`} items={items.map((i) => i.id)} strategy={verticalListSortingStrategy}>
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
