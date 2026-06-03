import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Check, Pencil, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useCatalogServices, useApproveCatalogService, useRejectCatalogService, useUpdateCatalogService,
} from '../hooks';
import type { CatalogServiceRow } from '../api';

const STATUSES = ['pending', 'active', 'rejected'] as const;

function EditCatalogDialog({ row, onClose }: { row: CatalogServiceRow; onClose: () => void }) {
  const { t } = useTranslation();
  const update = useUpdateCatalogService();
  const [name, setName] = useState(row.name);
  const [nameEn, setNameEn] = useState(row.name_en ?? '');
  // One alias per line — simplest editor for an array of synonyms.
  const [aliasesText, setAliasesText] = useState((row.aliases ?? []).join('\n'));

  useEffect(() => {
    setName(row.name);
    setNameEn(row.name_en ?? '');
    setAliasesText((row.aliases ?? []).join('\n'));
  }, [row]);

  const onSave = async () => {
    const aliases = aliasesText
      .split('\n')
      .map((a) => a.trim())
      .filter(Boolean);
    try {
      await update.mutateAsync({ id: row.id, name: name.trim(), name_en: nameEn.trim() || null, aliases });
      toast.success(t('catalog_services.updated'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('catalog_services.edit_title', 'تعديل خدمة الكتالوغ')}</DialogTitle>
          <DialogDescription className="sr-only">{t('catalog_services.subtitle')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="cat_name">{t('catalog_services.name')}</Label>
            <Input id="cat_name" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cat_name_en">{t('catalog_services.name_en', 'الاسم بالإنجليزية (اختياري)')}</Label>
            <Input id="cat_name_en" dir="ltr" value={nameEn} onChange={(e) => setNameEn(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cat_aliases">{t('catalog_services.aliases', 'أسماء بديلة')}</Label>
            <Textarea
              id="cat_aliases"
              rows={4}
              value={aliasesText}
              onChange={(e) => setAliasesText(e.target.value)}
              placeholder={t('catalog_services.aliases_ph', 'اسم بديل في كل سطر — تُستخدم للبحث والمطابقة')}
            />
            <p className="text-xs text-[var(--color-muted-foreground)]">
              {t('catalog_services.aliases_hint', 'اكتب اسماً بديلاً في كل سطر. تساعد في ربط الخدمات والبحث والمقارنة.')}
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={update.isPending}>{t('common.cancel')}</Button>
          <Button type="button" onClick={onSave} disabled={update.isPending || !name.trim()}>
            {update.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function CatalogServicesIndex() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<string>('pending');
  const { data, isLoading } = useCatalogServices({ status });
  const approve = useApproveCatalogService();
  const reject = useRejectCatalogService();
  const [editing, setEditing] = useState<CatalogServiceRow | null>(null);

  const rows = data?.data ?? [];

  const onApprove = async (id: number) => {
    try {
      await approve.mutateAsync(id);
      toast.success(t('catalog_services.approved'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const onReject = async (id: number) => {
    try {
      await reject.mutateAsync(id);
      toast.success(t('catalog_services.rejected'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('catalog_services.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('catalog_services.subtitle')}</p>
      </div>

      <div className="flex gap-2">
        {STATUSES.map((s) => (
          <button
            key={s}
            onClick={() => setStatus(s)}
            className={`rounded-md px-3 py-1.5 text-sm ${status === s ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-muted)]'}`}
          >
            {t(`catalog_services.status_${s}`)}
          </button>
        ))}
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('catalog_services.name')}</TableHead>
              <TableHead>{t('catalog_services.category')}</TableHead>
              <TableHead>{t('catalog_services.requested_by')}</TableHead>
              <TableHead className="w-24">{t('catalog_services.linked')}</TableHead>
              <TableHead>{t('catalog_services.date')}</TableHead>
              <TableHead className="w-44" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={6}>{t('common.loading')}</TableCell></TableRow>
            ) : rows.length === 0 ? (
              <TableRow><TableCell colSpan={6}>{t('catalog_services.empty')}</TableCell></TableRow>
            ) : (
              rows.map((r) => (
                <TableRow key={r.id}>
                  <TableCell className="font-medium">
                    {r.name}
                    {r.aliases?.length > 0 && (
                      <span className="mt-0.5 block text-xs font-normal text-[var(--color-muted-foreground)]">
                        {t('catalog_services.aliases')}: {r.aliases.join('، ')}
                      </span>
                    )}
                  </TableCell>
                  <TableCell>{r.category?.name ?? '—'}</TableCell>
                  <TableCell>{r.requested_by?.name ?? '—'}</TableCell>
                  <TableCell><Badge variant="muted">{r.services_count}</Badge></TableCell>
                  <TableCell>{r.created_at?.slice(0, 10) ?? '—'}</TableCell>
                  <TableCell>
                    <div className="flex gap-2">
                      {status === 'pending' && (
                        <>
                          <Button size="sm" onClick={() => onApprove(r.id)} disabled={approve.isPending}>
                            <Check className="h-4 w-4" /> {t('catalog_services.approve')}
                          </Button>
                          <Button size="sm" variant="outline" onClick={() => onReject(r.id)} disabled={reject.isPending}>
                            <X className="h-4 w-4" /> {t('catalog_services.reject')}
                          </Button>
                        </>
                      )}
                      <Button size="sm" variant="ghost" onClick={() => setEditing(r)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {editing && <EditCatalogDialog row={editing} onClose={() => setEditing(null)} />}
    </div>
  );
}
