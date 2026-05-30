import { useState } from 'react';
import { Plus, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useAddTag, useRemoveTag } from '../hooks';
import { TAG_COLORS, type TagColor, type TagDto } from '../types';

interface Props {
  bookingId: number;
  bookingTags: TagDto[];
  customerTags: TagDto[];
}

function TagChip({ tag, onRemove }: { tag: TagDto; onRemove: () => void }) {
  const tone = {
    rose:    'bg-rose-100 text-rose-700 border-rose-200',
    amber:   'bg-amber-100 text-amber-700 border-amber-200',
    emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    sky:     'bg-sky-100 text-sky-700 border-sky-200',
    violet:  'bg-violet-100 text-violet-700 border-violet-200',
    slate:   'bg-slate-100 text-slate-700 border-slate-200',
  }[tag.color];
  return (
    <span className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs ${tone}`}>
      {tag.label}
      <button type="button" onClick={onRemove} className="rounded hover:bg-black/10">
        <X className="h-3 w-3" />
      </button>
    </span>
  );
}

export function TagsManager({ bookingId, bookingTags, customerTags }: Props) {
  const { t } = useTranslation();
  const addMut = useAddTag(bookingId);
  const rmMut = useRemoveTag(bookingId);
  const [scope, setScope] = useState<'booking' | 'customer'>('customer');
  const [label, setLabel] = useState('');
  const [color, setColor] = useState<TagColor>('sky');

  async function onAdd() {
    const trimmed = label.trim();
    if (!trimmed) return;
    try {
      await addMut.mutateAsync({ scope, label: trimmed, color });
      setLabel('');
      toast.success(t('clinic_bookings_kanban.tags.added'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onRemove(tag: TagDto) {
    try {
      await rmMut.mutateAsync({ scope: tag.scope, tagId: tag.id });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <div className="space-y-3">
      {customerTags.length > 0 && (
        <div className="space-y-1.5">
          <Label>{t('clinic_bookings_kanban.tags.customer_section')}</Label>
          <div className="flex flex-wrap gap-1.5">
            {customerTags.map((tag) => <TagChip key={`c-${tag.id}`} tag={tag} onRemove={() => onRemove(tag)} />)}
          </div>
        </div>
      )}
      {bookingTags.length > 0 && (
        <div className="space-y-1.5">
          <Label>{t('clinic_bookings_kanban.tags.booking_section')}</Label>
          <div className="flex flex-wrap gap-1.5">
            {bookingTags.map((tag) => <TagChip key={`b-${tag.id}`} tag={tag} onRemove={() => onRemove(tag)} />)}
          </div>
        </div>
      )}

      <div className="space-y-1.5">
        <Label>{t('clinic_bookings_kanban.tags.add_new')}</Label>
        <div className="grid grid-cols-3 gap-2">
          <Select value={scope} onChange={(e) => setScope(e.target.value as any)}>
            <option value="customer">{t('clinic_bookings_kanban.tags.scope_customer')}</option>
            <option value="booking">{t('clinic_bookings_kanban.tags.scope_booking')}</option>
          </Select>
          <Select value={color} onChange={(e) => setColor(e.target.value as TagColor)}>
            {TAG_COLORS.map((c) => <option key={c} value={c}>{t(`clinic_bookings_kanban.tags.color_${c}`)}</option>)}
          </Select>
          <div className="flex gap-1">
            <Input
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              placeholder={t('clinic_bookings_kanban.tags.label_placeholder')}
              maxLength={60}
            />
            <Button size="sm" onClick={onAdd} disabled={!label.trim() || addMut.isPending} className="shrink-0 gap-1">
              <Plus className="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
