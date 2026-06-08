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
import type { BookingStage, KanbanCard, KanbanFilters, StageKind } from '../types';
import { KanbanColumnView } from './KanbanColumn';
import { BookingCard } from './BookingCard';
import { MoveConfirmDialog, type MoveIntent, type MoveResult } from './MoveConfirmDialog';

interface Props {
  filters: KanbanFilters;
  onOpenCard: (card: KanbanCard) => void;
  stages: BookingStage[];
}

/** The storage status a booking takes when it enters a stage of each kind. */
const REP_STATUS: Record<StageKind, string> = {
  new: 'new',
  confirmed: 'appointment_set',
  completed: 'completed',
  cancelled: 'cancelled',
};

const TERMINAL: StageKind[] = ['completed', 'cancelled'];

type MovePayload = {
  stage_id: number;
  status?: string;
  cancel_reason?: string;
  cancel_note?: string;
  completion_note?: string;
};

export function KanbanBoard({ filters, onOpenCard, stages }: Props) {
  const { t } = useTranslation();
  const qc = useQueryClient();
  const { data, isLoading, isError } = useKanbanBoard(filters);
  const [activeCard, setActiveCard] = useState<KanbanCard | null>(null);
  const [pending, setPending] = useState<{ card: KanbanCard; targetStage: BookingStage; intent: MoveIntent } | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  // Flatten board into a card list + a cardId → stage-id (board key) map.
  const { allCards, cardStageKey } = useMemo(() => {
    const cards: KanbanCard[] = [];
    const map = new Map<number, string>();
    if (data) {
      for (const [stageKey, payload] of Object.entries(data)) {
        for (const item of payload?.items ?? []) {
          cards.push(item);
          map.set(item.id, stageKey);
        }
      }
    }
    return { allCards: cards, cardStageKey: map };
  }, [data]);

  const stageById = useMemo(() => {
    const m = new Map<number, BookingStage>();
    stages.forEach((s) => m.set(s.id, s));
    return m;
  }, [stages]);

  const findCardById = (id: number) => allCards.find((c) => c.id === id);
  const sourceStageOf = (card: KanbanCard): BookingStage | undefined => {
    const key = cardStageKey.get(card.id);
    return key ? stageById.get(Number(key)) : undefined;
  };

  function handleDragStart(e: DragStartEvent) {
    const card = findCardById(Number(e.active.id));
    if (card) setActiveCard(card);
  }

  function handleDragEnd(e: DragEndEvent) {
    setActiveCard(null);
    const card = findCardById(Number(e.active.id));
    if (!card) return;

    const overId = e.over?.id?.toString() ?? '';
    let targetStageId: number | null = null;
    if (overId.startsWith('column:')) {
      targetStageId = Number(overId.slice('column:'.length));
    } else {
      const overCard = findCardById(Number(e.over?.id));
      const key = overCard ? cardStageKey.get(overCard.id) : undefined;
      if (key) targetStageId = Number(key);
    }
    if (!targetStageId) return;

    const targetStage = stageById.get(targetStageId);
    if (!targetStage) return;

    const sourceStage = sourceStageOf(card);
    if (sourceStage && sourceStage.id === targetStage.id) return; // dropped on same column

    const sourceKind = sourceStage?.kind;
    const targetKind = targetStage.kind;

    // Same kind → reposition only; status is preserved (e.g. keep
    // "contacted" when moving between two "new"-kind stages).
    if (sourceKind === targetKind) {
      applyMove(card, { stage_id: targetStage.id });
      return;
    }

    // Leaving a terminal kind (completed/cancelled) → confirm first.
    if (sourceKind && TERMINAL.includes(sourceKind) && !TERMINAL.includes(targetKind)) {
      setPending({ card, targetStage, intent: { kind: 'backwards' } });
      return;
    }
    if (targetKind === 'cancelled') { setPending({ card, targetStage, intent: { kind: 'cancel' } }); return; }
    if (targetKind === 'completed') { setPending({ card, targetStage, intent: { kind: 'completion' } }); return; }

    applyMove(card, { stage_id: targetStage.id, status: REP_STATUS[targetKind] });
  }

  async function applyMove(card: KanbanCard, payload: MovePayload) {
    try {
      await bookingKanbanApi.updateStatus(card.id, payload);
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings'] });
      qc.invalidateQueries({ queryKey: ['clinic', 'team-activity'] });
      toast.success(t('clinic_bookings_kanban.move.success'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    } finally {
      setPending(null);
    }
  }

  function confirmPending(r: MoveResult) {
    if (!pending) return;
    const targetKind = pending.targetStage.kind;
    const status = r.status === '__backwards__' ? REP_STATUS[targetKind] : r.status;
    applyMove(pending.card, {
      stage_id: pending.targetStage.id,
      status,
      cancel_reason: r.cancel_reason,
      cancel_note: r.cancel_note,
      completion_note: r.completion_note,
    });
  }

  if (isError) return <div className="p-6 text-sm text-rose-600">{t('errors.generic')}</div>;

  return (
    <>
      <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
        <div className="flex h-[calc(100vh-280px)] min-h-[480px] gap-3 overflow-x-auto pb-2">
          {stages.map((stage) => {
            const payload = data?.[String(stage.id)];
            return (
              <div key={stage.id} className="w-[280px] shrink-0">
                <KanbanColumnView
                  stage={stage}
                  items={payload?.items ?? []}
                  total={payload?.total ?? 0}
                  onOpen={onOpenCard}
                />
              </div>
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
          toName={pending.targetStage.name}
          onCancel={() => setPending(null)}
          onConfirm={confirmPending}
        />
      )}

      {isLoading && <div className="mt-3 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>}
    </>
  );
}
