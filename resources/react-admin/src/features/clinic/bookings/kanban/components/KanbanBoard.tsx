import { useMemo, useState } from 'react';
import {
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { toast } from 'sonner';
import { useQueryClient } from '@tanstack/react-query';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { bookingKanbanApi } from '../api';
import { useKanbanBoard } from '../hooks';
import { KANBAN_COLUMNS, type KanbanCard, type KanbanColumn as Col, type KanbanFilters } from '../types';
import { KanbanColumnView } from './KanbanColumn';
import { BookingCard } from './BookingCard';
import { MoveConfirmDialog, type MoveIntent, type MoveResult } from './MoveConfirmDialog';

interface Props {
  filters: KanbanFilters;
  onOpenCard: (card: KanbanCard) => void;
}

const COLUMN_TO_STATUS: Record<Col, string> = {
  new: 'new',
  confirmed: 'appointment_set',
  completed: 'completed',
  cancelled: 'cancelled',
};

function colOf(card: KanbanCard): Col {
  return card.kanban_column;
}

export function KanbanBoard({ filters, onOpenCard }: Props) {
  const { t } = useTranslation();
  const qc = useQueryClient();
  const { data, isLoading, isError } = useKanbanBoard(filters);
  const [activeCard, setActiveCard] = useState<KanbanCard | null>(null);
  const [pending, setPending] = useState<{ card: KanbanCard; target: Col; intent: MoveIntent } | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const allCards = useMemo(() => {
    if (!data) return [] as KanbanCard[];
    return KANBAN_COLUMNS.flatMap((c) => data[c]?.items ?? []);
  }, [data]);

  function findCardById(id: number): KanbanCard | undefined {
    return allCards.find((c) => c.id === id);
  }

  function handleDragStart(e: DragStartEvent) {
    const id = Number(e.active.id);
    const card = findCardById(id);
    if (card) setActiveCard(card);
  }

  function handleDragEnd(e: DragEndEvent) {
    setActiveCard(null);
    const id = Number(e.active.id);
    const card = findCardById(id);
    if (!card) return;

    const overId = e.over?.id?.toString() ?? '';
    let target: Col | null = null;
    if (overId.startsWith('column:')) {
      target = overId.slice('column:'.length) as Col;
    } else {
      const overCard = findCardById(Number(e.over?.id));
      if (overCard) target = colOf(overCard);
    }
    if (!target || target === colOf(card)) return;

    const fromCol = colOf(card);

    if ((fromCol === 'completed' || fromCol === 'cancelled') && target !== fromCol) {
      setPending({ card, target, intent: { kind: 'backwards' } });
      return;
    }

    if (target === 'cancelled') {
      setPending({ card, target, intent: { kind: 'cancel' } });
      return;
    }

    if (target === 'completed') {
      setPending({ card, target, intent: { kind: 'completion' } });
      return;
    }

    applyMove(card, target, { status: COLUMN_TO_STATUS[target] });
  }

  async function applyMove(card: KanbanCard, target: Col, result: MoveResult) {
    try {
      const status = result.status === '__backwards__' ? COLUMN_TO_STATUS[target] : result.status;
      await bookingKanbanApi.updateStatus(card.id, {
        status,
        cancel_reason: result.cancel_reason,
        cancel_note: result.cancel_note,
        completion_note: result.completion_note,
      });
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings'] });
      qc.invalidateQueries({ queryKey: ['clinic', 'team-activity'] });
      toast.success(t('clinic_bookings_kanban.move.success'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    } finally {
      setPending(null);
    }
  }

  if (isError) return <div className="p-6 text-sm text-rose-600">{t('errors.generic')}</div>;

  return (
    <>
      <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
        <div className="grid h-[calc(100vh-280px)] min-h-[480px] grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {KANBAN_COLUMNS.map((col) => {
            const payload = data?.[col];
            return (
              <KanbanColumnView
                key={col}
                column={col}
                items={payload?.items ?? []}
                total={payload?.total ?? 0}
                onOpen={onOpenCard}
              />
            );
          })}
        </div>

        <DragOverlay>
          {activeCard ? <div className="w-[280px]"><BookingCard card={activeCard} onOpen={() => {}} /></div> : null}
        </DragOverlay>
      </DndContext>

      {pending && (
        <MoveConfirmDialog
          open
          intent={pending.intent}
          fromColumn={colOf(pending.card)}
          toColumn={pending.target}
          onCancel={() => setPending(null)}
          onConfirm={(r) => applyMove(pending.card, pending.target, r)}
        />
      )}

      {isLoading && <div className="mt-3 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>}
    </>
  );
}
