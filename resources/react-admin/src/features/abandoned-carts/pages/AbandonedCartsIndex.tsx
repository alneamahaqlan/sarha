import { Link } from 'react-router-dom';
import { ShoppingCart, ChevronLeft } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import {
  Table, TableHeader, TableBody, TableRow, TableHead, TableCell,
} from '@/components/ui/table';
import { useAbandonedCarts } from '../hooks';

export function AbandonedCartsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useAbandonedCarts();

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <ShoppingCart className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('abandoned_carts.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('abandoned_carts.subtitle')}</p>
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('abandoned_carts.col_clinic')}</TableHead>
            <TableHead>{t('abandoned_carts.col_carts')}</TableHead>
            <TableHead>{t('abandoned_carts.col_items')}</TableHead>
            <TableHead>{t('abandoned_carts.col_last_contact')}</TableHead>
            <TableHead>{t('abandoned_carts.col_contacted')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('abandoned_carts.empty')}
              </TableCell>
            </TableRow>
          ) : (
            data.map((row) => (
              <TableRow key={row.clinic?.id ?? Math.random()}>
                <TableCell className="font-medium">{row.clinic?.name ?? '—'}</TableCell>
                <TableCell>{fmt.format(row.carts_count)}</TableCell>
                <TableCell>{fmt.format(row.items_count)}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(row.last_contacted_at)}</TableCell>
                <TableCell>
                  <Badge variant={row.contacted_recently ? 'success' : 'warning'}>
                    {row.contacted_recently ? t('abandoned_carts.contacted_recently') : t('abandoned_carts.not_contacted')}
                  </Badge>
                </TableCell>
                <TableCell className="text-end">
                  {row.clinic && (
                    <Link
                      to={`/admin/abandoned-carts/${row.clinic.id}`}
                      className="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline"
                    >
                      {t('abandoned_carts.view')}
                      <ChevronLeft className="h-3 w-3 rtl:rotate-180" />
                    </Link>
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
