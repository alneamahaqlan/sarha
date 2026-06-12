import { useState } from 'react';
import { Heart, TrendingUp } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Table, TableHeader, TableBody, TableRow, TableHead, TableCell,
} from '@/components/ui/table';
import { useFavorites } from '../hooks';

export function FavoritesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [page, setPage] = useState(1);
  const { data, isLoading } = useFavorites(page);

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Heart className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('favorites.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('favorites.subtitle')}</p>
        </div>
      </div>

      {/* Most-saved demand signal */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white">
        <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
          <TrendingUp className="h-4 w-4 text-emerald-600" />
          <h2 className="text-sm font-semibold">{t('favorites.top_saved')}</h2>
        </div>
        <div className="divide-y divide-[var(--color-border)]">
          {!data || data.top_saved.length === 0 ? (
            <div className="px-4 py-6 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
          ) : (
            data.top_saved.map((row, i) => (
              <div key={`${row.type}-${row.name}-${i}`} className="flex items-center justify-between px-4 py-2.5 text-sm">
                <div className="flex min-w-0 items-center gap-2">
                  <span className="text-xs font-semibold text-[var(--color-muted-foreground)]">{i + 1}</span>
                  <Badge variant="muted">{t(`favorites.type_${row.type}`)}</Badge>
                  <span className="truncate font-medium">{row.name}</span>
                </div>
                <Badge variant="success">{t('favorites.saves_n', { count: row.saves })}</Badge>
              </div>
            ))
          )}
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('favorites.col_item')}</TableHead>
            <TableHead>{t('favorites.col_type')}</TableHead>
            <TableHead>{t('favorites.col_user')}</TableHead>
            <TableHead>{t('favorites.col_phone')}</TableHead>
            <TableHead>{t('favorites.col_saved_at')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell>
            </TableRow>
          ) : (
            data.data.map((row) => (
              <TableRow key={row.id}>
                <TableCell className="font-medium">
                  {row.name}
                  {row.deleted && <span className="ms-1 text-xs text-red-500">({t('abandoned_carts.item_deleted')})</span>}
                </TableCell>
                <TableCell><Badge variant="muted">{t(`favorites.type_${row.type}`)}</Badge></TableCell>
                <TableCell>{row.user?.name ?? '—'}</TableCell>
                <TableCell dir="ltr">{row.user?.phone ?? '—'}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(row.saved_at)}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-center gap-3">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            {t('common.previous')}
          </Button>
          <span className="text-xs text-[var(--color-muted-foreground)]">
            {fmt.format(data.meta.current_page)} / {fmt.format(data.meta.last_page)}
          </span>
          <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            {t('common.next')}
          </Button>
        </div>
      )}
    </div>
  );
}
