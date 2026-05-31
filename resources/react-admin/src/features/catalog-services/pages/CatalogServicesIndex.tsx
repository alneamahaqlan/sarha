import { useState } from 'react';
import { toast } from 'sonner';
import { Check, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useCatalogServices, useApproveCatalogService, useRejectCatalogService } from '../hooks';

const STATUSES = ['pending', 'active', 'rejected'] as const;

export function CatalogServicesIndex() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<string>('pending');
  const { data, isLoading } = useCatalogServices({ status });
  const approve = useApproveCatalogService();
  const reject = useRejectCatalogService();

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
              <TableHead className="w-32" />
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
                  <TableCell className="font-medium">{r.name}</TableCell>
                  <TableCell>{r.category?.name ?? '—'}</TableCell>
                  <TableCell>{r.requested_by?.name ?? '—'}</TableCell>
                  <TableCell><Badge variant="muted">{r.services_count}</Badge></TableCell>
                  <TableCell>{r.created_at?.slice(0, 10) ?? '—'}</TableCell>
                  <TableCell>
                    {status === 'pending' && (
                      <div className="flex gap-2">
                        <Button size="sm" onClick={() => onApprove(r.id)} disabled={approve.isPending}>
                          <Check className="h-4 w-4" /> {t('catalog_services.approve')}
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => onReject(r.id)} disabled={reject.isPending}>
                          <X className="h-4 w-4" /> {t('catalog_services.reject')}
                        </Button>
                      </div>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
