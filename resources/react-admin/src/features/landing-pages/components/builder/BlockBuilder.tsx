import { useEffect, useMemo, useState } from 'react';
import {
  DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors, type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext, arrayMove, useSortable, verticalListSortingStrategy, sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Select } from '@/components/ui/select';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useLandingPageBlocks, useReorderBlocks, useAddBlock, useUpdateBlock, useDeleteBlock } from '../../hooks';
import { BLOCK_TYPES, type BlockType, type LandingPageBlock } from '../../types';
import { BlockConfigEditor } from './BlockConfigEditor';

interface Props {
  pageId: number;
}

export function BlockBuilder({ pageId }: Props) {
  const { t } = useTranslation();
  const { data: serverBlocks, isLoading } = useLandingPageBlocks(pageId);
  const reorder = useReorderBlocks(pageId);
  const add = useAddBlock(pageId);
  const del = useDeleteBlock(pageId);

  const [items, setItems] = useState<LandingPageBlock[]>([]);
  const [adding, setAdding] = useState<BlockType>('hero');

  useEffect(() => { if (serverBlocks) setItems(serverBlocks); }, [serverBlocks]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const ids = useMemo(() => items.map((b) => b.id), [items]);

  const onDragEnd = async (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    const oldIndex = items.findIndex((b) => b.id === active.id);
    const newIndex = items.findIndex((b) => b.id === over.id);
    const next = arrayMove(items, oldIndex, newIndex);
    setItems(next); // optimistic
    try {
      await reorder.mutateAsync({ order: next.map((b, i) => ({ id: b.id, sort_order: (i + 1) * 10 })) });
    } catch (err) {
      setItems(items); // rollback
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const handleAdd = async () => {
    try {
      await add.mutateAsync(adding);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  if (isLoading) return <p className="text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</p>;

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-2">
        <div className="space-y-1.5">
          <label className="text-sm font-medium">{t('landing_pages.add_block')}</label>
          <Select value={adding} onChange={(e) => setAdding(e.target.value as BlockType)} className="min-w-48">
            {BLOCK_TYPES.map((bt) => <option key={bt} value={bt}>{t(`landing_pages.blocks.${bt}`)}</option>)}
          </Select>
        </div>
        <Button type="button" onClick={handleAdd} disabled={add.isPending}>
          <Plus className="h-4 w-4" />
          {t('common.add')}
        </Button>
      </div>

      {items.length === 0 ? (
        <p className="py-8 text-center text-[var(--color-muted-foreground)]">{t('landing_pages.no_blocks')}</p>
      ) : (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={ids} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {items.map((block) => (
                <SortableBlockCard
                  key={block.id}
                  pageId={pageId}
                  block={block}
                  onDelete={() => del.mutate(block.id)}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      )}
    </div>
  );
}

function SortableBlockCard({ pageId, block, onDelete }: { pageId: number; block: LandingPageBlock; onDelete: () => void }) {
  const { t } = useTranslation();
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: block.id });
  const update = useUpdateBlock(pageId);
  const [open, setOpen] = useState(false);

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div ref={setNodeRef} style={style} className="rounded-lg border border-[var(--color-border)] bg-[var(--color-card)]">
      <div className="flex items-center gap-2 p-3">
        <button type="button" className="cursor-grab text-[var(--color-muted-foreground)]" {...attributes} {...listeners} aria-label="drag">
          <GripVertical className="h-4 w-4" />
        </button>
        <span className="flex-1 font-medium">{t(`landing_pages.blocks.${block.type}`)}</span>

        <Switch
          checked={block.is_visible}
          onCheckedChange={(v) => update.mutate({ blockId: block.id, values: { is_visible: v } })}
          aria-label={t('landing_pages.block_visible')}
        />
        <Button type="button" variant="ghost" size="icon" onClick={() => setOpen((o) => !o)} aria-label="expand">
          <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
        </Button>
        <Button type="button" variant="ghost" size="icon" className="text-[var(--color-destructive)]" onClick={onDelete} aria-label={t('common.delete')}>
          <Trash2 className="h-4 w-4" />
        </Button>
      </div>

      {open && (
        <div className="border-t border-[var(--color-border)] p-4">
          <BlockConfigEditor pageId={pageId} block={block} />
        </div>
      )}
    </div>
  );
}
