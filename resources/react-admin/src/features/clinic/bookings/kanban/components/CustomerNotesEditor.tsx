import { useEffect, useState } from 'react';
import { Save, StickyNote } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useUpdateCustomerNotes } from '../hooks';

interface Props {
  customerId: number | null;
  initialNotes: string | null | undefined;
}

/**
 * Customer-level free-text note (lives on Customer.notes from
 * phase 1). Shown on the Customer 360 tab — applies to every
 * booking for this customer, not just the one currently open.
 */
export function CustomerNotesEditor({ customerId, initialNotes }: Props) {
  const { t } = useTranslation();
  const [value, setValue] = useState(initialNotes ?? '');
  const mut = useUpdateCustomerNotes(customerId);

  useEffect(() => {
    setValue(initialNotes ?? '');
  }, [initialNotes, customerId]);

  if (!customerId) return null;

  const dirty = (initialNotes ?? '') !== value;

  async function onSave() {
    try {
      await mut.mutateAsync(value.trim() ? value : null);
      toast.success(t('clinic_bookings_kanban.customer_notes.saved'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <section className="space-y-2 rounded-lg border border-[var(--color-border)] bg-white p-3">
      <Label className="flex items-center gap-1.5 text-xs font-semibold">
        <StickyNote className="h-3.5 w-3.5" />
        {t('clinic_bookings_kanban.customer_notes.label')}
      </Label>
      <Textarea
        rows={3}
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder={t('clinic_bookings_kanban.customer_notes.placeholder')}
      />
      <div className="flex justify-end">
        <Button size="sm" onClick={onSave} disabled={!dirty || mut.isPending} className="h-7 gap-1 text-xs">
          <Save className="h-3 w-3" />
          {mut.isPending ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </section>
  );
}
