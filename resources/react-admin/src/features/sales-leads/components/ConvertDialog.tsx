import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { usePackages } from '@/features/subscription-packages/hooks';

import { useConvertSalesLead } from '../hooks';
import type { BillingCycle, SalesLead } from '../types';

interface Props {
  lead: SalesLead;
  onClose: () => void;
}

// Paid months per cycle — mirrors SubscriptionService::activate on the server.
const MONTHS: Record<BillingCycle, number> = { quarterly: 3, annual: 12 };

export function ConvertDialog({ lead, onClose }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const mut = useConvertSalesLead();
  const { data: packages, isLoading } = usePackages();

  const [packageId, setPackageId] = useState<number | null>(null);
  const [cycle, setCycle] = useState<BillingCycle>('quarterly');
  const [amount, setAmount] = useState<string>('');

  const selected = useMemo(
    () => packages?.find((p) => p.id === packageId) ?? null,
    [packages, packageId],
  );

  // Default to the first (lowest-tier) active package once loaded.
  useEffect(() => {
    if (packageId === null && packages && packages.length > 0) {
      setPackageId(packages[0].id);
    }
  }, [packages, packageId]);

  // Prefill the amount from the package's monthly_price × cycle months.
  // The package stays the source of truth for the default; the admin can
  // override it with a custom per-subscription price afterwards.
  useEffect(() => {
    if (selected) {
      setAmount(String(selected.monthly_price * MONTHS[cycle]));
    }
  }, [selected, cycle]);

  const submit = async () => {
    if (!packageId) return;
    try {
      const data = await mut.mutateAsync({
        id: lead.id,
        payload: { package_id: packageId, billing_cycle: cycle, amount: Number(amount) || 0 },
      });
      toast.success(t('sales_leads.actions.converted', { clinic: data.clinic.name }));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('sales_leads.actions.convert_title')}</DialogTitle>
          <DialogDescription>
            {t('sales_leads.actions.convert_body', { clinic: lead.clinic_name })}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="convert-package">{t('sales_leads.actions.package_label')}</Label>
            <Select
              id="convert-package"
              value={packageId ?? ''}
              onChange={(e) => setPackageId(Number(e.target.value) || null)}
              disabled={isLoading}
            >
              {packages?.map((p) => (
                <option key={p.id} value={p.id}>
                  {(locale === 'ar' ? p.name_ar : p.name_en)} — {p.monthly_price} {t('common.sar')}
                </option>
              ))}
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="convert-cycle">{t('sales_leads.actions.cycle_label')}</Label>
            <Select
              id="convert-cycle"
              value={cycle}
              onChange={(e) => setCycle(e.target.value as BillingCycle)}
            >
              <option value="quarterly">{t('sales_leads.actions.cycle_quarterly')}</option>
              <option value="annual">{t('sales_leads.actions.cycle_annual')}</option>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="convert-amount">{t('sales_leads.actions.amount_label')}</Label>
            <div className="flex items-center gap-2">
              <Input
                id="convert-amount"
                type="number"
                min={0}
                step={1}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="flex-1"
              />
              <span className="text-sm text-[var(--color-muted-foreground)]">{t('common.sar')}</span>
            </div>
            <p className="text-xs text-[var(--color-muted-foreground)]">
              {t('sales_leads.actions.amount_hint')}
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button onClick={submit} disabled={mut.isPending || !packageId}>
            {t('common.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
