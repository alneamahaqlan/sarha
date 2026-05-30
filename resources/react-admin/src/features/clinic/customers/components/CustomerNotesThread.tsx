import { useState } from 'react';
import { Pin, PinOff, Trash2, Pencil, Save, Plus, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useCreateCustomerNote, useCustomerNotes, useDeleteCustomerNote, useUpdateCustomerNote,
} from '../hooks';
import type { CustomerNote } from '../types';

interface Props {
  customerId: number;
  /** Compact mode for the Kanban side panel: small composer, max-h on the thread. */
  compact?: boolean;
}

function relativeTime(iso: string, locale: string): string {
  const ms = Date.now() - new Date(iso).getTime();
  const m = Math.round(ms / 60000);
  if (m < 1)   return locale === 'ar' ? 'الآن' : 'just now';
  if (m < 60)  return locale === 'ar' ? `قبل ${m} د` : `${m}m ago`;
  const h = Math.round(m / 60);
  if (h < 24)  return locale === 'ar' ? `قبل ${h} س` : `${h}h ago`;
  const d = Math.round(h / 24);
  if (d < 30)  return locale === 'ar' ? `قبل ${d} ي` : `${d}d ago`;
  return new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-GB');
}

export function CustomerNotesThread({ customerId, compact }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useCustomerNotes(customerId);
  const createMut = useCreateCustomerNote(customerId);
  const updateMut = useUpdateCustomerNote(customerId);
  const deleteMut = useDeleteCustomerNote(customerId);

  const [draft, setDraft] = useState('');
  const [draftPinned, setDraftPinned] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingBody, setEditingBody] = useState('');

  async function onAdd() {
    if (!draft.trim()) return;
    try {
      await createMut.mutateAsync({ body: draft.trim(), is_pinned: draftPinned });
      setDraft('');
      setDraftPinned(false);
      toast.success(t('clinic_customers.notes.added'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onTogglePin(note: CustomerNote) {
    try {
      await updateMut.mutateAsync({ noteId: note.id, is_pinned: !note.is_pinned });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  function startEdit(note: CustomerNote) {
    setEditingId(note.id);
    setEditingBody(note.body);
  }

  async function onSaveEdit(note: CustomerNote) {
    if (!editingBody.trim()) return;
    try {
      await updateMut.mutateAsync({ noteId: note.id, body: editingBody.trim() });
      setEditingId(null);
      toast.success(t('clinic_customers.notes.updated'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onDelete(note: CustomerNote) {
    if (!confirm(t('clinic_customers.notes.delete_confirm'))) return;
    try {
      await deleteMut.mutateAsync(note.id);
      toast.success(t('clinic_customers.notes.deleted'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <div className="space-y-3">
      <div className="space-y-2">
        <Textarea
          rows={compact ? 2 : 3}
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          placeholder={t('clinic_customers.notes.placeholder')}
        />
        <div className="flex items-center justify-between gap-2">
          <label className="inline-flex items-center gap-1.5 text-[11px] text-[var(--color-muted-foreground)]">
            <input type="checkbox" checked={draftPinned} onChange={(e) => setDraftPinned(e.target.checked)} />
            {t('clinic_customers.notes.pin_on_create')}
          </label>
          <Button size="sm" onClick={onAdd} disabled={!draft.trim() || createMut.isPending} className="h-7 gap-1 text-xs">
            <Plus className="h-3 w-3" />
            {createMut.isPending ? t('common.loading') : t('clinic_customers.notes.add')}
          </Button>
        </div>
      </div>

      <div className={`space-y-2 ${compact ? 'max-h-[280px] overflow-y-auto pr-1' : ''}`}>
        {isLoading && <div className="p-3 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>}
        {!isLoading && (!data || data.length === 0) && (
          <div className="rounded-md border border-dashed border-[var(--color-border)] p-4 text-center text-xs text-[var(--color-muted-foreground)]">
            {t('clinic_customers.notes.empty')}
          </div>
        )}
        {(data ?? []).map((note) => {
          const editing = editingId === note.id;
          return (
            <div key={note.id} className={`rounded-md border p-2.5 ${note.is_pinned ? 'border-amber-300 bg-amber-50/50' : 'border-[var(--color-border)] bg-white'}`}>
              <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-1.5 text-[11px] text-[var(--color-muted-foreground)]">
                  {note.is_pinned && <Pin className="h-3 w-3 text-amber-600" />}
                  <span className="font-medium">{note.created_by_name}</span>
                  <span>·</span>
                  <span>{relativeTime(note.created_at, locale)}</span>
                </div>
                <div className="flex items-center gap-0.5">
                  {note.can_pin && (
                    <button
                      type="button"
                      onClick={() => onTogglePin(note)}
                      className="rounded p-0.5 text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]"
                      title={note.is_pinned ? t('clinic_customers.notes.unpin') : t('clinic_customers.notes.pin')}
                    >
                      {note.is_pinned ? <PinOff className="h-3.5 w-3.5" /> : <Pin className="h-3.5 w-3.5" />}
                    </button>
                  )}
                  {note.can_edit && !editing && (
                    <button type="button" onClick={() => startEdit(note)} className="rounded p-0.5 text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]">
                      <Pencil className="h-3.5 w-3.5" />
                    </button>
                  )}
                  {note.can_delete && (
                    <button type="button" onClick={() => onDelete(note)} className="rounded p-0.5 text-rose-600 hover:bg-rose-50">
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>
              </div>
              <div className="mt-1.5 text-[12px] leading-relaxed">
                {editing ? (
                  <div className="space-y-1.5">
                    <Textarea rows={2} value={editingBody} onChange={(e) => setEditingBody(e.target.value)} />
                    <div className="flex justify-end gap-1">
                      <Button size="sm" variant="ghost" onClick={() => setEditingId(null)} className="h-6 gap-1 text-[11px]">
                        <X className="h-3 w-3" />
                        {t('common.cancel')}
                      </Button>
                      <Button size="sm" onClick={() => onSaveEdit(note)} className="h-6 gap-1 text-[11px]">
                        <Save className="h-3 w-3" />
                        {t('common.save')}
                      </Button>
                    </div>
                  </div>
                ) : (
                  <p className="whitespace-pre-wrap break-words">{note.body}</p>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
