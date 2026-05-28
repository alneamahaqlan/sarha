import { useState } from 'react';
import { toast } from 'sonner';
import { CheckCircle2, Clock, Plus, XCircle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useClinicCategoryRequests, useCreateClinicCategoryRequest } from '../hooks';

/** Mirrors the admin-side status palette so both panels feel like the same product. */
const STATUS_VARIANT: Record<string, 'warning' | 'success' | 'danger'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
};
const STATUS_ICON: Record<string, { Icon: typeof Clock; className: string }> = {
  pending:  { Icon: Clock,        className: 'text-amber-500' },
  approved: { Icon: CheckCircle2, className: 'text-emerald-600' },
  rejected: { Icon: XCircle,      className: 'text-rose-500' },
};

/**
 * Clinic view of the specialty-request workflow. The clinic admin sees the
 * full history of what they've proposed and where each request stands —
 * complements the inline "suggest" dialog in the service form.
 */
export function ClinicCategoryRequestsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicCategoryRequests();
  const create = useCreateClinicCategoryRequest();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = name.trim();
    if (!trimmed) return;
    try {
      await create.mutateAsync({ name: trimmed });
      toast.success(t('clinic_services.specialty_request_sent', 'تم إرسال الطلب للإدارة'));
      setName('');
      setOpen(false);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const rows = data ?? [];
  const pendingCount = rows.filter((r) => r.status === 'pending').length;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">
            {t('clinic_category_requests.title', 'طلبات التخصصات')}
          </h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {t(
              'clinic_category_requests.subtitle',
              'تتبّع التخصصات اللي اقترحتها على الإدارة. عند الموافقة، يُضاف التخصص لخدماتك تلقائياً.',
            )}
          </p>
        </div>
        <Button onClick={() => setOpen(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_category_requests.new', 'طلب جديد')}
        </Button>
      </div>

      {pendingCount > 0 && (
        <div className="inline-flex items-center gap-2 rounded-md bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
          <Clock className="h-3.5 w-3.5" />
          {t('clinic_category_requests.pending_hint', '{{count}} طلب قيد المراجعة', { count: pendingCount })}
        </div>
      )}

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_category_requests.name', 'التخصص المطلوب')}</TableHead>
            <TableHead>{t('clinic_category_requests.status_label', 'الحالة')}</TableHead>
            <TableHead>{t('clinic_category_requests.created_at', 'تاريخ الإرسال')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={3} className="py-12 text-center text-[var(--color-muted-foreground)]">
                <div className="space-y-2">
                  <Plus className="mx-auto h-8 w-8 opacity-40" />
                  <div>{t('clinic_category_requests.empty', 'لم تقدّم أي طلب بعد')}</div>
                </div>
              </TableCell>
            </TableRow>
          ) : (
            rows.map((r) => {
              const Glyph = STATUS_ICON[r.status];
              return (
                <TableRow key={r.id}>
                  <TableCell className="font-medium">{r.name}</TableCell>
                  <TableCell>
                    <Badge variant={STATUS_VARIANT[r.status]} className="inline-flex items-center gap-1.5">
                      <Glyph.Icon className={`h-3.5 w-3.5 ${Glyph.className}`} />
                      {t(`category_requests.status.${r.status}`)}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-sm text-[var(--color-muted-foreground)]">
                    {r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}
                  </TableCell>
                </TableRow>
              );
            })
          )}
        </TableBody>
      </Table>

      <Dialog open={open} onOpenChange={(o) => !o && setOpen(false)}>
        <DialogContent>
          <form onSubmit={submit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{t('clinic_services.request_specialty_title', 'اقترح تخصصاً جديداً')}</DialogTitle>
              <DialogDescription>
                {t(
                  'clinic_category_requests.dialog_desc',
                  'يُراجع الطلب من الإدارة، وعند الموافقة يُنشأ التخصص ويصبح متاحاً لإضافته لخدماتك.',
                )}
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-1.5">
              <Label htmlFor="cr-name">{t('clinic_services.request_specialty_name', 'اسم التخصص المقترح')} *</Label>
              <Input
                id="cr-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder={t('clinic_services.request_specialty_placeholder', 'مثال: العلاج بالإبر الصينية')}
                autoFocus
                maxLength={255}
              />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={create.isPending}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={create.isPending || !name.trim()}>
                {create.isPending ? t('common.loading') : t('clinic_services.request_specialty_submit', 'إرسال الطلب')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
