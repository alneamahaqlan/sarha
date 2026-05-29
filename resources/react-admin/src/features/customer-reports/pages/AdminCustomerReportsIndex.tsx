import { useState } from 'react';
import { toast } from 'sonner';
import { CheckCircle2, Clock, Flag, XCircle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useAdminCustomerReports, useUpdateAdminCustomerReport } from '../hooks';
import type { AdminCustomerReport, CustomerReportStatus } from '../api';

const STATUS_LIST: (CustomerReportStatus | '')[] = ['new', 'in_review', 'resolved', 'rejected', ''];
const STATUS_VARIANT: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  new: 'default', in_review: 'warning', resolved: 'success', rejected: 'danger',
};
const STATUS_ICON: Record<string, { Icon: typeof Clock; className: string }> = {
  new:       { Icon: Flag,         className: 'text-blue-500' },
  in_review: { Icon: Clock,        className: 'text-amber-500' },
  resolved:  { Icon: CheckCircle2, className: 'text-emerald-600' },
  rejected:  { Icon: XCircle,      className: 'text-rose-500' },
};
const PRIORITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = {
  low: 'default', medium: 'warning', high: 'danger',
};

function StatusActions({ report }: { report: AdminCustomerReport }) {
  const { t } = useTranslation();
  const update = useUpdateAdminCustomerReport(report.id);
  const change = async (status: CustomerReportStatus) => {
    try {
      await update.mutateAsync({ status });
      toast.success(t('admin_customer_reports.updated', 'تم التحديث'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };
  return (
    <Select
      defaultValue={report.status}
      onChange={(e) => change(e.target.value as CustomerReportStatus)}
      disabled={update.isPending}
      className="h-8 text-xs"
    >
      <option value="new">{t('admin_customer_reports.status_new', 'جديد')}</option>
      <option value="in_review">{t('admin_customer_reports.status_in_review', 'قيد المراجعة')}</option>
      <option value="resolved">{t('admin_customer_reports.status_resolved', 'تم الحل')}</option>
      <option value="rejected">{t('admin_customer_reports.status_rejected', 'مرفوض')}</option>
    </Select>
  );
}

export function AdminCustomerReportsIndex() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<string>('new');
  const { data, isLoading } = useAdminCustomerReports({ status });

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('admin_customer_reports.title', 'بلاغات العملاء')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">
          {t('admin_customer_reports.subtitle', 'بلاغات يرفعها المستخدم النهائي للإدارة (مشكلة تقنية، اقتراح، إساءة، محتوى غير لائق).')}
        </p>
      </div>

      <div className="flex gap-1">
        {STATUS_LIST.map((s) => (
          <button
            key={s || 'all'}
            type="button"
            onClick={() => setStatus(s)}
            className={`rounded-md px-3 py-1.5 text-sm ${status === s ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-muted)]'}`}
          >
            {s ? t(`admin_customer_reports.status_${s}`) : t('common.all')}
          </button>
        ))}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('admin_customer_reports.reference', 'الرقم')}</TableHead>
            <TableHead>{t('admin_customer_reports.user', 'العميل')}</TableHead>
            <TableHead>{t('admin_customer_reports.subject', 'الموضوع')}</TableHead>
            <TableHead>{t('admin_customer_reports.type', 'النوع')}</TableHead>
            <TableHead>{t('admin_customer_reports.clinic', 'مجمع')}</TableHead>
            <TableHead>{t('admin_customer_reports.priority', 'الأولوية')}</TableHead>
            <TableHead>{t('admin_customer_reports.status_label', 'الحالة')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell>
            </TableRow>
          ) : (
            data.data.map((r) => {
              const Glyph = STATUS_ICON[r.status];
              return (
                <TableRow key={r.id}>
                  <TableCell className="font-mono text-xs" dir="ltr">{r.reference_code}</TableCell>
                  <TableCell className="text-sm">
                    {r.user?.name ?? '—'}
                    {r.user?.phone && <div className="font-mono text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">{r.user.phone}</div>}
                  </TableCell>
                  <TableCell className="font-medium">
                    {r.subject}
                    <p className="text-xs text-[var(--color-muted-foreground)] max-w-xs truncate">{r.description}</p>
                  </TableCell>
                  <TableCell className="text-sm">{t(`admin_customer_reports.type_${r.type}`, r.type)}</TableCell>
                  <TableCell className="text-sm text-[var(--color-muted-foreground)]">{r.clinic?.name ?? '—'}</TableCell>
                  <TableCell>
                    <Badge variant={PRIORITY_VARIANT[r.priority]}>
                      {t(`admin_customer_reports.priority_${r.priority}`)}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={STATUS_VARIANT[r.status]} className="inline-flex items-center gap-1.5">
                      <Glyph.Icon className={`h-3.5 w-3.5 ${Glyph.className}`} />
                      {t(`admin_customer_reports.status_${r.status}`)}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-end">
                    <StatusActions report={r} />
                  </TableCell>
                </TableRow>
              );
            })
          )}
        </TableBody>
      </Table>
    </div>
  );
}
