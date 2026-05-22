import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useAuditLogs } from '../hooks';

export function AuditLogsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  const queryParams = useMemo(
    () => ({ page, per_page: 20, search: search.trim() || undefined, sort: '-created_at' }),
    [page, search],
  );
  const { data, isLoading, isFetching } = useAuditLogs(queryParams);

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('audit_logs.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('audit_logs.subtitle')}</p>
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
        <Input
          className="ps-9"
          placeholder={t('common.search')}
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
        />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('audit_logs.admin_name')}</TableHead>
            <TableHead>{t('audit_logs.action')}</TableHead>
            <TableHead>{t('audit_logs.model')}</TableHead>
            <TableHead>{t('audit_logs.model_id')}</TableHead>
            <TableHead>{t('audit_logs.ip_address')}</TableHead>
            <TableHead>{t('audit_logs.date')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((log) => (
              <TableRow key={log.id}>
                <TableCell className="font-medium">{log.admin_name ?? '—'}</TableCell>
                <TableCell>
                  <Badge variant="outline" className="font-mono">{log.action}</Badge>
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{log.model_basename ?? '—'}</TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{log.model_id ?? '—'}</TableCell>
                <TableCell dir="ltr" className="text-xs text-[var(--color-muted-foreground)]">{log.ip_address ?? '—'}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(log.created_at)}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">
            {data.meta.from}–{data.meta.to} / {data.meta.total}
          </span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              {t('common.back')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= data.meta.last_page || isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              ›
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
