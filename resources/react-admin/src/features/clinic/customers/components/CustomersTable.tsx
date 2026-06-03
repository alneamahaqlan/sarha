import { Link } from 'react-router-dom';
import { Phone, MessageCircle, Eye, Crown, Repeat, Sparkles, AlertOctagon, StickyNote } from 'lucide-react';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useCustomers } from '../hooks';
import type { CustomerListRow, ListFilters } from '../types';

interface Props {
  filters: ListFilters;
  onFilterChange: (patch: Partial<ListFilters>) => void;
}

function fmtDate(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
}

function waLink(phone: string) {
  const digits = phone.replace(/[^\d]/g, '');
  return `https://wa.me/${digits.startsWith('0') ? '966' + digits.slice(1) : digits}`;
}

function NameBadges({ row }: { row: CustomerListRow }) {
  const badges: any[] = [];
  if (row.is_vip)              badges.push({ Icon: Crown,        tone: 'text-amber-600' });
  if (row.is_repeat)           badges.push({ Icon: Repeat,       tone: 'text-blue-600' });
  if (row.is_new)              badges.push({ Icon: Sparkles,     tone: 'text-emerald-600' });
  if (row.has_prior_complaint) badges.push({ Icon: AlertOctagon, tone: 'text-rose-600' });
  if (row.has_notes)           badges.push({ Icon: StickyNote,   tone: 'text-violet-600' });
  return (
    <span className="inline-flex items-center gap-0.5">
      {badges.map(({ Icon, tone }, i) => <Icon key={i} className={`h-3.5 w-3.5 ${tone}`} />)}
    </span>
  );
}

function TagChip({ label, color }: { label: string; color: string }) {
  const tone = {
    rose:    'bg-rose-100 text-rose-700 border-rose-200',
    amber:   'bg-amber-100 text-amber-700 border-amber-200',
    emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    sky:     'bg-sky-100 text-sky-700 border-sky-200',
    violet:  'bg-violet-100 text-violet-700 border-violet-200',
    slate:   'bg-slate-100 text-slate-700 border-slate-200',
  }[color] ?? 'bg-slate-100 text-slate-700 border-slate-200';
  return <span className={`inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] ${tone}`}>{label}</span>;
}

export function CustomersTable({ filters, onFilterChange }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useCustomers(filters);

  return (
    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-white">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_customers.table.name')}</TableHead>
            <TableHead>{t('clinic_customers.table.phone')}</TableHead>
            <TableHead>{t('clinic_customers.table.bookings')}</TableHead>
            <TableHead>{t('clinic_customers.table.last_interaction')}</TableHead>
            <TableHead>{t('clinic_customers.table.tags')}</TableHead>
            <TableHead className="w-[1%]">{t('clinic_customers.table.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading && (
            <TableRow><TableCell colSpan={6} className="text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          )}
          {!isLoading && (data?.data?.length ?? 0) === 0 && (
            <TableRow><TableCell colSpan={6} className="text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_customers.empty')}</TableCell></TableRow>
          )}
          {(data?.data ?? []).map((row) => (
            <TableRow key={row.id} className="hover:bg-[var(--color-muted)]/40">
              <TableCell>
                <Link to={`/clinic/customers/${row.id}`} className="flex items-center gap-1.5 font-medium hover:underline">
                  <span>{row.name}</span>
                  <NameBadges row={row} />
                </Link>
                <div className="text-[10px] text-[var(--color-muted-foreground)]">
                  {t('clinic_customers.table.total_completed', { total: row.total_bookings, completed: row.completed_bookings })}
                </div>
              </TableCell>
              <TableCell><span dir="ltr" className="text-xs">{row.phone}</span></TableCell>
              <TableCell>
                <Badge variant="muted">{row.total_bookings}</Badge>
              </TableCell>
              <TableCell>
                <div className="text-xs">{fmtDate(row.last_interaction_at, locale)}</div>
                {row.last_interaction_type && (
                  <div className="text-[10px] text-[var(--color-muted-foreground)]">
                    {t(`clinic_customers.interaction.${row.last_interaction_type}`)}
                  </div>
                )}
              </TableCell>
              <TableCell>
                {row.tags.length > 0 ? (
                  <div className="flex flex-wrap gap-1">
                    {row.tags.slice(0, 3).map((tag) => <TagChip key={tag.id} label={tag.label} color={tag.color} />)}
                    {row.tags.length > 3 && <span className="text-[10px] text-[var(--color-muted-foreground)]">+{row.tags.length - 3}</span>}
                  </div>
                ) : (
                  <span className="text-[10px] text-[var(--color-muted-foreground)]">—</span>
                )}
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-1">
                  <a href={`tel:${row.phone}`} className="rounded-md border border-[var(--color-border)] p-1.5 hover:bg-[var(--color-muted)]" title={t('outreach.call')}>
                    <Phone className="h-3.5 w-3.5" />
                  </a>
                  <a href={waLink(row.phone)} target="_blank" rel="noopener" className="rounded-md border border-emerald-300 p-1.5 text-emerald-700 hover:bg-emerald-50" title={t('outreach.whatsapp')}>
                    <MessageCircle className="h-3.5 w-3.5" />
                  </a>
                  <Link to={`/clinic/customers/${row.id}`} className="rounded-md border border-[var(--color-border)] p-1.5 hover:bg-[var(--color-muted)]" title={t('clinic_customers.actions.open')}>
                    <Eye className="h-3.5 w-3.5" />
                  </Link>
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {data && data.last_page > 1 && (
        <div className="flex items-center justify-between border-t border-[var(--color-border)] px-3 py-2 text-xs">
          <span className="text-[var(--color-muted-foreground)]">
            {t('clinic_customers.page_of', { current: data.current_page, total: data.last_page })}
          </span>
          <div className="flex gap-1">
            <button
              type="button"
              disabled={data.current_page <= 1}
              onClick={() => onFilterChange({ page: (filters.page ?? 1) - 1 })}
              className="rounded-md border border-[var(--color-border)] px-2 py-1 disabled:opacity-50"
            >‹</button>
            <button
              type="button"
              disabled={data.current_page >= data.last_page}
              onClick={() => onFilterChange({ page: (filters.page ?? 1) + 1 })}
              className="rounded-md border border-[var(--color-border)] px-2 py-1 disabled:opacity-50"
            >›</button>
          </div>
        </div>
      )}
    </div>
  );
}
