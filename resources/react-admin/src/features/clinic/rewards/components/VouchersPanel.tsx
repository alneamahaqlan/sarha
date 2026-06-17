import { useState } from 'react';
import { toast } from 'sonner';
import { Gift, Plus } from 'lucide-react';

import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { useCan } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

import { useRedeemReward, useRewardVouchers } from '../hooks';
import { describeReward } from '../utils';
import { GrantRewardDialog } from './GrantRewardDialog';
import type { RewardVoucher, VoucherFilters, VoucherStatus } from '../types';

const STATUS_VARIANT: Record<VoucherStatus, 'success' | 'info' | 'muted' | 'danger'> = {
  active: 'success',
  used: 'info',
  expired: 'muted',
  void: 'danger',
};

export function VouchersPanel() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const canManage = useCan('rewards.manage');
  const canRedeem = useCan('rewards.redeem');
  const [filters, setFilters] = useState<VoucherFilters>({ status: '', search: '', page: 1, per_page: 20 });
  const { data, isLoading } = useRewardVouchers(filters);
  const [granting, setGranting] = useState(false);
  const [redeemTarget, setRedeemTarget] = useState<RewardVoucher | null>(null);

  const patch = (p: Partial<VoucherFilters>) => setFilters((f) => ({ ...f, page: 1, ...p }));
  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1">
          <Label className="text-xs">{t('clinic_rewards.filter.status')}</Label>
          <Select value={filters.status ?? ''} onChange={(e) => patch({ status: e.target.value as VoucherStatus | '' })} className="w-40">
            <option value="">{t('clinic_rewards.filter.all')}</option>
            <option value="active">{t('clinic_rewards.status.active')}</option>
            <option value="used">{t('clinic_rewards.status.used')}</option>
            <option value="expired">{t('clinic_rewards.status.expired')}</option>
            <option value="void">{t('clinic_rewards.status.void')}</option>
          </Select>
        </div>
        <div className="flex-1 min-w-[180px] space-y-1">
          <Label className="text-xs">{t('clinic_rewards.filter.search')}</Label>
          <Input dir="ltr" placeholder={t('clinic_rewards.filter.search_placeholder')} value={filters.search ?? ''}
                 onChange={(e) => patch({ search: e.target.value })} />
        </div>
        {canManage && (
          <Button onClick={() => setGranting(true)} className="gap-1.5">
            <Plus className="h-4 w-4" /> {t('clinic_rewards.grant.cta')}
          </Button>
        )}
      </div>

      {/* Table */}
      <div className="overflow-hidden rounded-xl border border-[var(--color-border)] bg-white">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('clinic_rewards.col.code')}</TableHead>
              <TableHead>{t('clinic_rewards.col.customer')}</TableHead>
              <TableHead>{t('clinic_rewards.col.reward')}</TableHead>
              <TableHead>{t('clinic_rewards.col.status')}</TableHead>
              <TableHead>{t('clinic_rewards.col.expires')}</TableHead>
              <TableHead className="text-end">{t('clinic_rewards.col.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={6} className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
            ) : rows.length === 0 ? (
              <TableRow><TableCell colSpan={6} className="py-10 text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_rewards.empty')}</TableCell></TableRow>
            ) : rows.map((v) => (
              <TableRow key={v.id}>
                <TableCell className="font-mono text-xs" dir="ltr">{v.code}</TableCell>
                <TableCell className="text-sm">
                  <div>{v.customer_name || '—'}</div>
                  <div className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">{v.phone}</div>
                </TableCell>
                <TableCell className="text-sm">{describeReward(t, v)}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[v.status]} className="text-[10px]">
                    {t(`clinic_rewards.status.${v.status}`)}
                  </Badge>
                </TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(v.expires_at)}</TableCell>
                <TableCell className="text-end">
                  {canRedeem && v.status === 'active' && !v.is_expired && (
                    <Button size="sm" variant="outline" onClick={() => setRedeemTarget(v)}>
                      {t('clinic_rewards.redeem.cta')}
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-[var(--color-border)] px-3 py-2 text-xs">
            <span className="text-[var(--color-muted-foreground)]">
              {t('clinic_rewards.page_of', { current: meta.current_page, total: meta.last_page })}
            </span>
            <div className="flex gap-1">
              <button type="button" disabled={meta.current_page <= 1}
                      onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                      className="rounded-md border border-[var(--color-border)] px-2 py-1 disabled:opacity-50">‹</button>
              <button type="button" disabled={meta.current_page >= meta.last_page}
                      onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                      className="rounded-md border border-[var(--color-border)] px-2 py-1 disabled:opacity-50">›</button>
            </div>
          </div>
        )}
      </div>

      {granting && <GrantRewardDialog open onClose={() => setGranting(false)} />}
      {redeemTarget && (
        <RedeemDialog voucher={redeemTarget} onClose={() => setRedeemTarget(null)} />
      )}
    </div>
  );
}

/** Reception redemption — confirms + optional booking link; surfaces the server gate message on rejection. */
function RedeemDialog({ voucher, onClose }: { voucher: RewardVoucher; onClose: () => void }) {
  const { t } = useTranslation();
  const redeem = useRedeemReward();
  const [bookingId, setBookingId] = useState('');

  const confirm = async () => {
    try {
      await redeem.mutateAsync({ id: voucher.id, bookingId: bookingId !== '' ? Number(bookingId) : null });
      toast.success(t('clinic_rewards.redeem.done'));
      onClose();
    } catch (err) {
      // The gate returns a localized message (rewards.php) on rejection.
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2"><Gift className="h-4 w-4" /> {t('clinic_rewards.redeem.title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3 text-sm">
          <p className="text-[var(--color-muted-foreground)]">{t('clinic_rewards.redeem.confirm', { code: voucher.code })}</p>
          <div className="space-y-1.5">
            <Label className="text-xs">{t('clinic_rewards.redeem.booking_id')}</Label>
            <Input type="number" dir="ltr" placeholder={t('clinic_rewards.redeem.booking_id_hint')}
                   value={bookingId} onChange={(e) => setBookingId(e.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={confirm} disabled={redeem.isPending}>
            {redeem.isPending ? t('common.loading') : t('clinic_rewards.redeem.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
