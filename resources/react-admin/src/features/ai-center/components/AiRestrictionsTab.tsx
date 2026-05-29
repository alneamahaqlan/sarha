import { useState } from 'react';
import { toast } from 'sonner';
import { Plus, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useAiRestrictions, useDeleteAiRestriction, useUpdateAiRestriction } from '../hooks';
import { AiRestrictionDialog } from './AiRestrictionDialog';
import type { AiRestriction, AiRestrictionType } from '../types';
import { AiResponseTemplatesSection } from './AiResponseTemplatesSection';

const TYPE_FILTERS: { key: AiRestrictionType | 'all'; labelKey: string; fallback: string }[] = [
  { key: 'all',                labelKey: 'ai_center.filter_all',                fallback: 'الكل' },
  { key: 'banned_topic',       labelKey: 'ai_center.type_banned_topic',         fallback: 'مواضيع ممنوعة' },
  { key: 'emergency_keyword',  labelKey: 'ai_center.type_emergency_keyword',    fallback: 'كلمات طوارئ' },
  { key: 'clinic_blocklist',   labelKey: 'ai_center.type_clinic_blocklist',     fallback: 'مجمعات ممنوعة' },
  { key: 'category_blocklist', labelKey: 'ai_center.type_category_blocklist',   fallback: 'تخصصات ممنوعة' },
];

export function AiRestrictionsTab() {
  const { t } = useTranslation();
  const [filter, setFilter] = useState<AiRestrictionType | 'all'>('all');
  const { data, isLoading } = useAiRestrictions(filter === 'all' ? undefined : filter);
  const del = useDeleteAiRestriction();
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<AiRestriction | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('ai_center.restriction_deleted', 'تم حذف المنع'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-6">
      {/* Restrictions */}
      <section className="space-y-3">
        <div className="flex items-center justify-between gap-2">
          <div>
            <h2 className="text-lg font-semibold">{t('ai_center.restrictions_title', 'الموانع')}</h2>
            <p className="text-sm text-[var(--color-muted-foreground)]">
              {t('ai_center.restrictions_subtitle', 'قواعد تُطبَّق على رد المساعد قبل إرساله للمستخدم.')}
            </p>
          </div>
          <Button onClick={() => setAdding(true)}>
            <Plus className="h-4 w-4" />
            {t('ai_center.add_restriction', 'إضافة منع')}
          </Button>
        </div>

        <div className="flex flex-wrap gap-2">
          {TYPE_FILTERS.map((f) => (
            <button
              key={f.key}
              type="button"
              onClick={() => setFilter(f.key)}
              className={`inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${
                filter === f.key
                  ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                  : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
              }`}
            >
              {t(f.labelKey, f.fallback)}
            </button>
          ))}
        </div>

        {isLoading ? (
          <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
        ) : !data || data.length === 0 ? (
          <div className="rounded-md border border-dashed border-[var(--color-border)] py-8 text-center text-sm text-[var(--color-muted-foreground)]">
            {t('ai_center.no_restrictions', 'لا توجد قواعد بعد. أضف أول قاعدة من زر "إضافة منع".')}
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('ai_center.restriction_type', 'النوع')}</TableHead>
                <TableHead>{t('ai_center.value_phrase', 'القيمة')}</TableHead>
                <TableHead>{t('ai_center.response_override', 'الرد البديل')}</TableHead>
                <TableHead className="w-24">{t('ai_center.is_active', 'مفعّل')}</TableHead>
                <TableHead className="text-end">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.map((r) => (
                <RestrictionRow key={r.id} restriction={r} onDelete={() => setDeleting(r)} />
              ))}
            </TableBody>
          </Table>
        )}
      </section>

      {/* Response Templates */}
      <AiResponseTemplatesSection />

      {adding && <AiRestrictionDialog defaultType="banned_topic" onClose={() => setAdding(false)} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('ai_center.delete_restriction_title', 'حذف المنع')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('ai_center.delete_restriction_body', 'سيُحذف نهائياً ولن يَسري على ردود المساعد.')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function RestrictionRow({ restriction, onDelete }: { restriction: AiRestriction; onDelete: () => void }) {
  const { t } = useTranslation();
  const update = useUpdateAiRestriction(restriction.id);

  const typeBadge: Record<AiRestrictionType, { label: string; cls: string }> = {
    banned_topic:       { label: t('ai_center.type_banned_topic', 'موضوع ممنوع'),        cls: 'bg-red-50 text-red-700' },
    emergency_keyword:  { label: t('ai_center.type_emergency_keyword', 'كلمة طوارئ'),    cls: 'bg-amber-50 text-amber-700' },
    clinic_blocklist:   { label: t('ai_center.type_clinic_blocklist', 'مجمع ممنوع'),     cls: 'bg-gray-100 text-gray-700' },
    category_blocklist: { label: t('ai_center.type_category_blocklist', 'تخصص ممنوع'),  cls: 'bg-gray-100 text-gray-700' },
  };
  const meta = typeBadge[restriction.type];

  const onToggle = async (c: boolean) => {
    try {
      await update.mutateAsync({ is_active: c });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <TableRow>
      <TableCell>
        <Badge className={meta.cls}>{meta.label}</Badge>
      </TableCell>
      <TableCell>
        <span>{restriction.value_label ?? restriction.value}</span>
        {restriction.value_label && (
          <span className="ms-2 font-mono text-xs text-[var(--color-muted-foreground)]">#{restriction.value}</span>
        )}
      </TableCell>
      <TableCell className="max-w-md text-sm text-[var(--color-muted-foreground)]">
        {restriction.response_override
          ? <span className="line-clamp-1">{restriction.response_override}</span>
          : <span className="italic">—</span>}
      </TableCell>
      <TableCell>
        <Switch checked={restriction.is_active} onCheckedChange={onToggle} disabled={update.isPending} />
      </TableCell>
      <TableCell className="text-end">
        <Button variant="ghost" size="icon" onClick={onDelete} className="text-[var(--color-destructive)]"
          aria-label={t('common.delete')}>
          <Trash2 className="h-4 w-4" />
        </Button>
      </TableCell>
    </TableRow>
  );
}
