import { useState } from 'react';
import { toast } from 'sonner';
import { Star, MessageSquare, Flag } from 'lucide-react';

import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

import { useClinicReviews, useReplyReview, useReportReview } from '../hooks';
import type { ReportReason, ReviewFilters, VerifiedReviewRow } from '../types';

function Stars({ n }: { n: number | null }) {
  const v = n ?? 0;
  return (
    <span className="text-gold-500" dir="ltr" aria-label={`${v}/5`}>
      {'★'.repeat(v)}<span className="text-gray-200">{'★'.repeat(5 - v)}</span>
    </span>
  );
}

export function ClinicReviewsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [filters, setFilters] = useState<ReviewFilters>({ rating: undefined, replied: '', page: 1, per_page: 20 });
  const { data, isLoading } = useClinicReviews(filters);

  const patch = (p: Partial<ReviewFilters>) => setFilters((f) => ({ ...f, page: 1, ...p }));
  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div className="max-w-3xl space-y-5">
      <div className="flex items-center gap-2">
        <Star className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_reviews.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_reviews.subtitle')}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1">
          <Label className="text-xs">{t('clinic_reviews.filter.rating')}</Label>
          <Select className="w-36" value={filters.rating ? String(filters.rating) : ''}
                  onChange={(e) => patch({ rating: e.target.value ? Number(e.target.value) : undefined })}>
            <option value="">{t('clinic_reviews.filter.all')}</option>
            {[5, 4, 3, 2, 1].map((r) => <option key={r} value={r}>{'★'.repeat(r)}</option>)}
          </Select>
        </div>
        <div className="space-y-1">
          <Label className="text-xs">{t('clinic_reviews.filter.replied')}</Label>
          <Select className="w-40" value={filters.replied ?? ''} onChange={(e) => patch({ replied: e.target.value as ReviewFilters['replied'] })}>
            <option value="">{t('clinic_reviews.filter.all')}</option>
            <option value="no">{t('clinic_reviews.filter.not_replied')}</option>
            <option value="yes">{t('clinic_reviews.filter.replied_yes')}</option>
          </Select>
        </div>
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : rows.length === 0 ? (
        <div className="rounded-xl border border-[var(--color-border)] bg-white py-10 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('clinic_reviews.empty')}
        </div>
      ) : (
        <div className="space-y-3">
          {rows.map((r) => <ReviewCard key={r.id} review={r} fmt={fmt} />)}
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-xs">
          <span className="text-[var(--color-muted-foreground)]">{t('clinic_reviews.page_of', { current: meta.current_page, total: meta.last_page })}</span>
          <div className="flex gap-1">
            <button type="button" disabled={meta.current_page <= 1} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                    className="rounded-md border border-[var(--color-border)] px-2 py-1 disabled:opacity-50">‹</button>
            <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                    className="rounded-md border border-[var(--color-border)] px-2 py-1 disabled:opacity-50">›</button>
          </div>
        </div>
      )}
    </div>
  );
}

function ReviewCard({ review, fmt }: { review: VerifiedReviewRow; fmt: (iso: string | null) => string }) {
  const { t } = useTranslation();
  const replyMut = useReplyReview();
  const [editing, setEditing] = useState(false);
  const [reporting, setReporting] = useState(false);
  const [text, setText] = useState(review.reply?.text ?? '');

  const save = async () => {
    try {
      await replyMut.mutateAsync({ id: review.id, text: text.trim() });
      toast.success(t('clinic_reviews.reply_saved'));
      setEditing(false);
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 text-sm font-semibold text-[var(--color-foreground)]">
            {review.customer_name || t('clinic_reviews.anon')}
            <span className="inline-flex items-center gap-0.5 rounded-full bg-sage-mist px-1.5 py-0.5 text-[10px] text-sage-deep">{t('clinic_reviews.verified')}</span>
          </div>
          <div className="mt-1 text-sm"><Stars n={review.clinic_rating} /></div>
          {review.doctor && review.doctor_rating ? (
            <div className="mt-0.5 text-xs text-[var(--color-muted-foreground)]">
              {review.doctor.name}: <Stars n={review.doctor_rating} />
            </div>
          ) : null}
        </div>
        <span className="shrink-0 text-xs text-[var(--color-muted-foreground)]">{fmt(review.submitted_at)}</span>
      </div>

      {review.comment && <p className="mt-2 text-sm text-[var(--color-foreground)]">{review.comment}</p>}

      {/* Reply */}
      {review.reply && !editing ? (
        <div className="mt-3 rounded-lg bg-[var(--color-muted)]/40 p-3">
          <div className="mb-0.5 flex items-center justify-between">
            <span className="text-[11px] font-semibold text-[var(--color-foreground)]">{t('clinic_reviews.your_reply')}</span>
            <Button size="sm" variant="ghost" className="h-6 px-1.5 text-[11px]" onClick={() => { setText(review.reply!.text); setEditing(true); }}>
              {t('common.edit')}
            </Button>
          </div>
          <p className="text-sm text-[var(--color-foreground)]">{review.reply.text}</p>
        </div>
      ) : editing ? (
        <div className="mt-3 space-y-2">
          <Textarea rows={3} value={text} onChange={(e) => setText(e.target.value)} placeholder={t('clinic_reviews.reply_placeholder')} />
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="outline" onClick={() => setEditing(false)}>{t('common.cancel')}</Button>
            <Button size="sm" disabled={replyMut.isPending || text.trim().length < 2} onClick={save}>{t('common.save')}</Button>
          </div>
        </div>
      ) : (
        <div className="mt-3">
          <Button size="sm" variant="outline" className="gap-1.5" onClick={() => { setText(''); setEditing(true); }}>
            <MessageSquare className="h-3.5 w-3.5" /> {t('clinic_reviews.reply_cta')}
          </Button>
        </div>
      )}

      {/* Report spam/abuse — flags for admin; does NOT hide the review. */}
      <div className="mt-3 border-t border-[var(--color-border)] pt-2 flex items-center justify-between">
        {review.report ? (
          <span className="text-[11px] text-amber-700">
            {review.report.decided
              ? t(`clinic_reviews.report.decided_${review.report.action ?? 'dismissed'}`)
              : t('clinic_reviews.report.under_review')}
          </span>
        ) : (
          <button type="button" onClick={() => setReporting(true)}
                  className="inline-flex items-center gap-1 text-[11px] text-[var(--color-muted-foreground)] hover:text-[var(--color-destructive)]">
            <Flag className="h-3 w-3" /> {t('clinic_reviews.report.cta')}
          </button>
        )}
      </div>

      {reporting && <ReportDialog reviewId={review.id} onClose={() => setReporting(false)} />}
    </div>
  );
}

function ReportDialog({ reviewId, onClose }: { reviewId: number; onClose: () => void }) {
  const { t } = useTranslation();
  const reportMut = useReportReview();
  const [reason, setReason] = useState<ReportReason>('spam');
  const [note, setNote] = useState('');

  const submit = async () => {
    try {
      await reportMut.mutateAsync({ id: reviewId, reason, note: note.trim() || undefined });
      toast.success(t('clinic_reviews.report.sent'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_reviews.report.title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_reviews.report.notice')}</p>
          <div className="space-y-1.5">
            <Label>{t('clinic_reviews.report.reason')}</Label>
            <Select value={reason} onChange={(e) => setReason(e.target.value as ReportReason)}>
              <option value="spam">{t('clinic_reviews.report.reason_spam')}</option>
              <option value="abuse">{t('clinic_reviews.report.reason_abuse')}</option>
              <option value="fake">{t('clinic_reviews.report.reason_fake')}</option>
              <option value="other">{t('clinic_reviews.report.reason_other')}</option>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>{t('clinic_reviews.report.note')}</Label>
            <Textarea rows={3} value={note} onChange={(e) => setNote(e.target.value)} placeholder={t('clinic_reviews.report.note_placeholder')} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={submit} disabled={reportMut.isPending}>{t('clinic_reviews.report.submit')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
