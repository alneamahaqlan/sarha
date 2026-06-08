import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { CardAutoTags } from './CardAutoTags';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import type { KanbanCard } from '../types';
import { useKanbanBoard } from '../hooks';

interface Props {
  filters: any;
  onOpenCard: (card: KanbanCard) => void;
}

function fmt(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

export function BookingTableView({ filters, onOpenCard }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useKanbanBoard(filters);

  const rows = Object.values(data ?? {}).flatMap((p) => p?.items ?? []);

  return (
    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-white">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_bookings.columns.customer')}</TableHead>
            <TableHead>{t('clinic_bookings.columns.service')}</TableHead>
            <TableHead>{t('clinic_bookings.columns.appointment')}</TableHead>
            <TableHead>{t('clinic_bookings.columns.status')}</TableHead>
            <TableHead>{t('clinic_bookings_kanban.card.assignee')}</TableHead>
            <TableHead>{t('clinic_bookings_kanban.card.tags')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading && (
            <TableRow><TableCell colSpan={6} className="text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          )}
          {!isLoading && rows.length === 0 && (
            <TableRow><TableCell colSpan={6} className="text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.column.empty')}</TableCell></TableRow>
          )}
          {rows.map((card) => (
            <TableRow key={card.id} className="cursor-pointer hover:bg-[var(--color-muted)]/40" onClick={() => onOpenCard(card)}>
              <TableCell>
                <div className="font-medium">{card.customer_name}</div>
                <div className="text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">{card.customer_phone}</div>
              </TableCell>
              <TableCell>{card.service?.name ?? '—'}</TableCell>
              <TableCell>{fmt(card.appointment_at, locale)}</TableCell>
              <TableCell>
                <Badge variant="muted">{t(`clinic_bookings_kanban.column.${card.kanban_column}`)}</Badge>
              </TableCell>
              <TableCell>{card.assignee?.name ?? '—'}</TableCell>
              <TableCell><CardAutoTags tags={card.auto_tags} compact /></TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
