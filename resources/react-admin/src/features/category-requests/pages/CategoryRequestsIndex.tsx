import { useState } from 'react';
import { toast } from 'sonner';
import { Check, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useApproveCategoryRequest, useCategoryRequests, useRejectCategoryRequest } from '../hooks';

const STATUS_VARIANT: Record<string, 'warning' | 'success' | 'danger'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
};

export function CategoryRequestsIndex() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<string>('pending');
  const { data, isLoading } = useCategoryRequests({ filter: { status } });
  const approve = useApproveCategoryRequest();
  const reject = useRejectCategoryRequest();

  const act = async (fn: Promise<{ message: string }>) => {
    try {
      const res = await fn;
      toast.success(res.message);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('category_requests.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('category_requests.subtitle')}</p>
      </div>

      <div className="flex gap-1">
        {['pending', 'approved', 'rejected', ''].map((s) => (
          <button
            key={s || 'all'}
            type="button"
            onClick={() => setStatus(s)}
            className={`rounded-md px-3 py-1.5 text-sm ${status === s ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-muted)]'}`}
          >
            {s ? t(`category_requests.status.${s}`) : t('common.all')}
          </button>
        ))}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('category_requests.name')}</TableHead>
            <TableHead>{t('category_requests.clinic')}</TableHead>
            <TableHead>{t('category_requests.status_label')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((r) => (
              <TableRow key={r.id}>
                <TableCell className="font-medium">{r.name}</TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)]">{r.clinic?.name ?? '—'}</TableCell>
                <TableCell><Badge variant={STATUS_VARIANT[r.status]}>{t(`category_requests.status.${r.status}`)}</Badge></TableCell>
                <TableCell className="text-end">
                  {r.status === 'pending' ? (
                    <div className="flex justify-end gap-1">
                      <Button variant="ghost" size="icon" className="text-emerald-600" aria-label={t('category_requests.approve')}
                        onClick={() => act(approve.mutateAsync(r.id))} disabled={approve.isPending}>
                        <Check className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon" className="text-[var(--color-destructive)]" aria-label={t('category_requests.reject')}
                        onClick={() => act(reject.mutateAsync(r.id))} disabled={reject.isPending}>
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                  ) : (
                    <span className="text-xs text-[var(--color-muted-foreground)]">—</span>
                  )}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
}
