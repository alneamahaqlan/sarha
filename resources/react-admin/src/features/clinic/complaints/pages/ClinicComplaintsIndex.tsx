import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';

import { useClinicComplaints } from '../hooks';

/**
 * Read-only feed of CUSTOMER complaints raised against this complex.
 * The complex sees what was reported; admins handle resolution.
 *
 * The "file a complaint" surface that used to live here was redesigned
 * into /clinic/reports (platform reports — technical, billing, abusive
 * customer, etc.) because the old form reused customer complaint types
 * that had no meaning from the complex's perspective.
 */

const STATUS_VARIANT: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  new: 'default', in_review: 'warning', resolved: 'success', rejected: 'danger',
};

export function ClinicComplaintsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicComplaints();

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_complaints.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">
          {t('clinic_complaints.subtitle')}
        </p>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_complaints.reference')}</TableHead>
            <TableHead>{t('clinic_complaints.subject')}</TableHead>
            <TableHead>{t('clinic_complaints.type')}</TableHead>
            <TableHead>{t('clinic_complaints.status_label')}</TableHead>
            <TableHead>{t('clinic_complaints.resolution')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((c) => (
              <TableRow key={c.id}>
                <TableCell className="font-mono text-xs" dir="ltr">{c.reference_code}</TableCell>
                <TableCell className="font-medium">
                  {c.subject}
                  <p className="text-xs text-[var(--color-muted-foreground)] max-w-xs truncate">{c.description}</p>
                </TableCell>
                <TableCell className="text-sm">{t(`clinic_complaints.type_${c.type}`, c.type)}</TableCell>
                <TableCell><Badge variant={STATUS_VARIANT[c.status]}>{t(`clinic_complaints.status_${c.status}`)}</Badge></TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)] max-w-xs truncate">{c.resolution ?? '—'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
}
