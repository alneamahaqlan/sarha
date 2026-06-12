import { useState } from 'react';
import { toast } from 'sonner';
import { ShoppingCart } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Table, TableHeader, TableBody, TableRow, TableHead, TableCell,
} from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
  useCartRequests, useApproveCart, useRejectCart, useDisableCart,
} from '../hooks';
import type { CartRequestClinic, CartStatus } from '../api';

const STATUS_VARIANT: Record<CartStatus, 'warning' | 'success' | 'danger' | 'muted'> = {
  pending: 'warning',
  active: 'success',
  rejected: 'danger',
  disabled: 'muted',
};

export function CartModerationPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <ShoppingCart className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('cart.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('cart.subtitle')}</p>
        </div>
      </div>

      <RequestsSection />
    </div>
  );
}

function RequestsSection() {
  const { t } = useTranslation();
  const [status, setStatus] = useState('pending');
  const { data, isLoading } = useCartRequests(status);
  const approve = useApproveCart();
  const disable = useDisableCart();
  const [rejecting, setRejecting] = useState<CartRequestClinic | null>(null);

  const act = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      await fn();
      toast.success(t(ok));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-3">
      <Tabs value={status} onValueChange={setStatus}>
        <TabsList>
          <TabsTrigger value="pending">{t('cart.tab_pending')}</TabsTrigger>
          <TabsTrigger value="active">{t('cart.tab_active')}</TabsTrigger>
          <TabsTrigger value="all">{t('cart.tab_all')}</TabsTrigger>
        </TabsList>

        <TabsContent value={status}>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('cart.col_clinic')}</TableHead>
                <TableHead>{t('cart.col_status')}</TableHead>
                <TableHead className="text-end">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">
                    {t('common.loading')}
                  </TableCell>
                </TableRow>
              ) : !data || data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">
                    {t('common.no_data')}
                  </TableCell>
                </TableRow>
              ) : (
                data.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{c.name}</TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[c.cart_status]}>
                        {t(`cart.status_${c.cart_status}`)}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-end">
                      <div className="flex justify-end gap-1">
                        {c.cart_status !== 'active' && (
                          <Button size="sm" disabled={approve.isPending}
                            onClick={() => act(() => approve.mutateAsync(c.id), 'cart.approved')}>
                            {c.cart_status === 'pending' ? t('cart.approve') : t('cart.enable')}
                          </Button>
                        )}
                        {c.cart_status === 'pending' && (
                          <Button size="sm" variant="outline" onClick={() => setRejecting(c)}>
                            {t('cart.reject')}
                          </Button>
                        )}
                        {c.cart_status === 'active' && (
                          <Button size="sm" variant="destructive" disabled={disable.isPending}
                            onClick={() => act(() => disable.mutateAsync(c.id), 'cart.disabled')}>
                            {t('cart.disable')}
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TabsContent>
      </Tabs>

      {rejecting && <RejectDialog clinic={rejecting} onClose={() => setRejecting(null)} />}
    </div>
  );
}

function RejectDialog({ clinic, onClose }: { clinic: CartRequestClinic; onClose: () => void }) {
  const { t } = useTranslation();
  const reject = useRejectCart();
  const [reason, setReason] = useState('');

  const submit = async () => {
    try {
      await reject.mutateAsync({ id: clinic.id, reason });
      toast.success(t('cart.rejected'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('cart.reject_title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="reason">{t('cart.reject_reason')}</Label>
          <Textarea id="reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="destructive" onClick={submit} disabled={reject.isPending}>
            {reject.isPending ? t('common.loading') : t('cart.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
