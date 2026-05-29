import { useState } from 'react';
import { toast } from 'sonner';
import { Pencil, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useAiResponseTemplates,
  useCreateAiResponseTemplate,
  useDeleteAiResponseTemplate,
  useUpdateAiResponseTemplate,
} from '../hooks';
import type { AiResponseTemplate } from '../types';

export function AiResponseTemplatesSection() {
  const { t } = useTranslation();
  const { data, isLoading } = useAiResponseTemplates();
  const del = useDeleteAiResponseTemplate();
  const [editing, setEditing] = useState<AiResponseTemplate | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<AiResponseTemplate | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('ai_center.template_deleted', 'تم حذف القالب'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <section className="space-y-3 pt-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold">{t('ai_center.templates_title', 'قوالب الردود')}</h2>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {t('ai_center.templates_subtitle', 'مكتبة ردود جاهزة لإعادة استخدامها داخل الموانع.')}
          </p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('ai_center.add_template', 'إضافة قالب')}
        </Button>
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : !data || data.length === 0 ? (
        <div className="rounded-md border border-dashed border-[var(--color-border)] py-8 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('ai_center.no_templates', 'لا توجد قوالب بعد.')}
        </div>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('ai_center.template_label', 'التسمية')}</TableHead>
              <TableHead>{t('ai_center.template_content', 'النص')}</TableHead>
              <TableHead className="w-24">{t('ai_center.is_active', 'مفعّل')}</TableHead>
              <TableHead className="text-end">{t('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.map((tpl) => (
              <TableRow key={tpl.id}>
                <TableCell className="font-medium">{tpl.label}</TableCell>
                <TableCell className="max-w-md text-sm text-[var(--color-muted-foreground)]">
                  <span className="line-clamp-1">{tpl.content}</span>
                </TableCell>
                <TableCell>{tpl.is_active ? t('common.yes', 'نعم') : t('common.no', 'لا')}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(tpl)} aria-label={t('common.edit')}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(tpl)}
                      className="text-[var(--color-destructive)]" aria-label={t('common.delete')}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      {(creating || editing) && (
        <TemplateDialog template={editing} onClose={() => { setCreating(false); setEditing(null); }} />
      )}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('ai_center.delete_template_title', 'حذف القالب')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('ai_center.delete_template_body', 'سيُحذف القالب نهائياً.')}
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
    </section>
  );
}

function TemplateDialog({ template, onClose }: { template: AiResponseTemplate | null; onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateAiResponseTemplate();
  const update = useUpdateAiResponseTemplate(template?.id ?? 0);

  const [label, setLabel] = useState(template?.label ?? '');
  const [content, setContent] = useState(template?.content ?? '');
  const [isActive, setIsActive] = useState(template?.is_active ?? true);

  const submitting = create.isPending || update.isPending;

  const onSubmit = async () => {
    try {
      if (template) {
        await update.mutateAsync({ label, content, is_active: isActive });
        toast.success(t('ai_center.template_updated', 'تم تحديث القالب'));
      } else {
        await create.mutateAsync({ label, content, is_active: isActive });
        toast.success(t('ai_center.template_created', 'تم إنشاء القالب'));
      }
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{template ? t('ai_center.edit_template', 'تعديل القالب') : t('ai_center.add_template', 'إضافة قالب')}</DialogTitle>
          <DialogDescription>{t('ai_center.template_subtitle', 'استخدِم القوالب لإعادة استخدام ردود طويلة في عدة موانع.')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="t-label">{t('ai_center.template_label', 'التسمية')}</Label>
            <Input id="t-label" value={label} onChange={(e) => setLabel(e.target.value)} maxLength={255} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="t-content">{t('ai_center.template_content', 'النص')}</Label>
            <Textarea id="t-content" value={content} onChange={(e) => setContent(e.target.value)} rows={4} maxLength={4000} />
          </div>
          <div className="flex items-center gap-2">
            <Switch checked={isActive} onCheckedChange={setIsActive} />
            <Label>{t('ai_center.is_active', 'مفعّل')}</Label>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={submitting}>
            {t('common.cancel')}
          </Button>
          <Button onClick={onSubmit} disabled={submitting || !label.trim() || !content.trim()}>
            {submitting ? t('common.saving', 'يحفظ…') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
