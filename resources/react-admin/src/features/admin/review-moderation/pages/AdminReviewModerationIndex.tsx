import { useState } from 'react';
import { toast } from 'sonner';
import { ShieldAlert } from 'lucide-react';

import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

import { useModerateReview, useReportedReviews } from '../hooks';
import type { AdminReviewRow, ModFilters } from '../types';

function stars(n: number | null) {
  const v = n ?? 0;
  return '★'.repeat(v) + '☆'.repeat(5 - v);
}

export function AdminReviewModerationIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [scope, setScope] = useState<ModFilters['scope']>('pending');
  const { data, isLoading } = useReportedReviews({ scope, per_page: 20 });
  const moderate = useModerateReview();
  const [hideTarget, setHideTarget] = useState<AdminReviewRow | null>(null);

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

  const rows = data?.data ?? [];

  const dismiss = async (r: AdminReviewRow) => {
    try {
      await moderate.mutateAsync({ id: r.id, action: 'dismiss' });
      toast.success(t('admin_review_moderation.dismissed'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="max-w-3xl space-y-5">
      <div className="flex items-center gap-2">
        <ShieldAlert className="h-6 w-6 text-amber-500" />
        <div>
          <h1 className="text-2xl font-semibold">{t('admin_review_moderation.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('admin_review_moderation.subtitle')}</p>
        </div>
      </div>

      <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {t('admin_review_moderation.guard_notice')}
      </div>

      <Tabs value={scope} onValueChange={(v) => setScope(v as ModFilters['scope'])}>
        <TabsList className="grid w-full max-w-xs grid-cols-2">
          <TabsTrigger value="pending">{t('admin_review_moderation.tab_pending')}</TabsTrigger>
          <TabsTrigger value="decided">{t('admin_review_moderation.tab_decided')}</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : rows.length === 0 ? (
        <div className="rounded-xl border border-[var(--color-border)] bg-white py-10 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('admin_review_moderation.empty')}
        </div>
      ) : (
        <div className="space-y-3">
          {rows.map((r) => (
            <div key={r.id} className="rounded-xl border border-[var(--color-border)] bg-white p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <a href={`/clinic/${r.clinic?.slug}`} className="text-sm font-semibold text-[var(--color-primary)]">{r.clinic?.name}</a>
                  <div className="mt-1 text-gold-500 text-sm" dir="ltr">{stars(r.clinic_rating)}</div>
                </div>
                <span className="text-xs text-[var(--color-muted-foreground)]">{fmt(r.submitted_at)}</span>
              </div>
              {r.comment && <p className="mt-2 text-sm">{r.comment}</p>}

              {r.report && (
                <div className="mt-3 rounded-lg bg-amber-50 border border-amber-100 p-3 text-xs text-amber-800">
                  <div className="font-semibold">{t('admin_review_moderation.report_label')}: {t(`clinic_reviews.report.reason_${r.report.reason}`)}</div>
                  {r.report.note && <p className="mt-0.5">{r.report.note}</p>}
                  <p className="mt-0.5 text-amber-600">{t('admin_review_moderation.reported_by', { name: r.report.by_name ?? '—' })}</p>
                </div>
              )}

              {r.moderation ? (
                <div className="mt-3 text-xs text-[var(--color-muted-foreground)]">
                  {t(`admin_review_moderation.decided_${r.moderation.action}`)}
                  {r.moderation.reason ? ` — ${r.moderation.reason}` : ''} · {r.moderation.by ?? ''}
                </div>
              ) : (
                <div className="mt-3 flex justify-end gap-2">
                  <Button size="sm" variant="outline" onClick={() => dismiss(r)} disabled={moderate.isPending}>
                    {t('admin_review_moderation.dismiss')}
                  </Button>
                  <Button size="sm" variant="destructive" onClick={() => setHideTarget(r)}>
                    {t('admin_review_moderation.hide')}
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {hideTarget && <HideDialog review={hideTarget} onClose={() => setHideTarget(null)} />}
    </div>
  );
}

function HideDialog({ review, onClose }: { review: AdminReviewRow; onClose: () => void }) {
  const { t } = useTranslation();
  const moderate = useModerateReview();
  const [reason, setReason] = useState('');

  const confirm = async () => {
    try {
      await moderate.mutateAsync({ id: review.id, action: 'hide', reason: reason.trim() });
      toast.success(t('admin_review_moderation.hidden'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('admin_review_moderation.hide_title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <p className="text-xs text-amber-700">{t('admin_review_moderation.hide_notice')}</p>
          <div className="space-y-1.5">
            <Label>{t('admin_review_moderation.hide_reason')} <span className="text-[var(--color-destructive)]">*</span></Label>
            <Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('admin_review_moderation.hide_reason_placeholder')} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="destructive" onClick={confirm} disabled={moderate.isPending || reason.trim().length < 3}>
            {t('admin_review_moderation.hide_confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
