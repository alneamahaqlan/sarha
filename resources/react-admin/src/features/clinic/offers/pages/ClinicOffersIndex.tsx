import { useState } from 'react';
import { toast } from 'sonner';
import { CalendarClock, Pause, Pencil, Plus, Star, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useClinicOffers,
  useDeleteClinicOffer,
  useExtendClinicOffer,
  useUpdateClinicOffer,
} from '../hooks';
import { OfferDialog } from '../components/OfferDialog';
import type { Offer, OfferStatus } from '../types';

const FILTERS: { key: OfferStatus | 'all'; labelKey: string; fallback: string }[] = [
  { key: 'all',       labelKey: 'clinic_offers.filter_all',       fallback: 'الكل' },
  { key: 'active',    labelKey: 'clinic_offers.filter_active',    fallback: 'نشطة الآن' },
  { key: 'scheduled', labelKey: 'clinic_offers.filter_scheduled', fallback: 'مجدولة' },
  { key: 'expired',   labelKey: 'clinic_offers.filter_expired',   fallback: 'منتهية' },
  { key: 'paused',    labelKey: 'clinic_offers.filter_paused',    fallback: 'موقوفة' },
];

export function ClinicOffersIndex() {
  const { t } = useTranslation();
  const [filter, setFilter] = useState<OfferStatus | 'all'>('all');
  const { data: offers, isLoading } = useClinicOffers(filter === 'all' ? undefined : filter);

  const [editing, setEditing] = useState<Offer | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Offer | null>(null);
  const del = useDeleteClinicOffer();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_offers.deleted', 'تم حذف العرض'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_offers.title', 'العروض')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {t('clinic_offers.subtitle', 'أنشئ عروضك الترويجية واتركها تعمل تلقائياً حسب التواريخ.')}
          </p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_offers.create', 'إضافة عرض')}
        </Button>
      </div>

      <div className="flex flex-wrap gap-2">
        {FILTERS.map((f) => (
          <button
            key={f.key}
            type="button"
            onClick={() => setFilter(f.key)}
            className={`inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${
              filter === f.key
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
            }`}
          >
            {t(f.labelKey, f.fallback)}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="rounded-md border border-[var(--color-border)] py-12 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('common.loading')}
        </div>
      ) : !offers || offers.length === 0 ? (
        <EmptyState onCreate={() => setCreating(true)} />
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          {offers.map((o) => (
            <OfferCard
              key={o.id}
              offer={o}
              onEdit={() => setEditing(o)}
              onDelete={() => setDeleting(o)}
            />
          ))}
        </div>
      )}

      {(creating || editing) && (
        <OfferDialog offer={editing} onClose={() => { setCreating(false); setEditing(null); }} />
      )}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_offers.delete_confirm_title', 'حذف العرض')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('clinic_offers.delete_confirm_body', 'سيُحذف العرض نهائياً ولن يَظهر في سجل المجمع.')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function EmptyState({ onCreate }: { onCreate: () => void }) {
  const { t } = useTranslation();
  return (
    <div className="rounded-md border border-dashed border-[var(--color-border)] py-12 text-center">
      <p className="text-sm text-[var(--color-muted-foreground)]">
        {t('clinic_offers.empty_title', 'لا توجد عروض بعد')}
      </p>
      <Button className="mt-4" onClick={onCreate}>
        <Plus className="h-4 w-4" />
        {t('clinic_offers.empty_cta', 'أنشئ أول عرض')}
      </Button>
    </div>
  );
}

function OfferCard({ offer, onEdit, onDelete }: {
  offer: Offer;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const { t } = useTranslation();
  const update = useUpdateClinicOffer(offer.id);
  const extend = useExtendClinicOffer();

  const togglePaused = async () => {
    try {
      await update.mutateAsync({ is_active: !offer.is_active });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const extendByWeek = async () => {
    try {
      await extend.mutateAsync({ id: offer.id, days: 7 });
      toast.success(t('clinic_offers.extended', 'تم تمديد العرض 7 أيام'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const status = offer.status;
  const statusStyles: Record<typeof status, string> = {
    active:    'bg-emerald-50 text-emerald-700',
    scheduled: 'bg-blue-50 text-blue-700',
    expired:   'bg-gray-100 text-gray-600',
    paused:    'bg-amber-50 text-amber-700',
  };

  return (
    <div className="overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm">
      <div className="relative aspect-[16/9] bg-gradient-to-br from-[var(--color-muted)] to-white">
        {offer.image_url && (
          <img src={offer.image_url} alt="" className="absolute inset-0 h-full w-full object-cover" loading="lazy" />
        )}
        {offer.discount_percentage !== null && offer.discount_percentage > 0 && (
          <span className="absolute top-3 start-3 rounded-full bg-red-500 px-3 py-1 text-xs font-bold text-white shadow-md">
            -{offer.discount_percentage}%
          </span>
        )}
        {offer.is_featured && (
          <span className="absolute top-3 end-3 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2 py-1 text-[11px] font-bold text-white shadow-md">
            <Star className="h-3 w-3" />
            {t('clinic_offers.featured', 'مميّز')}
          </span>
        )}
      </div>

      <div className="p-4">
        <div className="flex items-start justify-between gap-2">
          <h3 className="line-clamp-1 font-semibold">{offer.title}</h3>
          <Badge className={statusStyles[status]}>
            {t(`clinic_offers.status_${status}`, status)}
          </Badge>
        </div>

        <div className="mt-1 text-xs text-[var(--color-muted-foreground)]">
          {offer.type === 'service' ? (
            <span>{t('clinic_offers.on_service', 'على خدمة')}: {offer.service?.name ?? '—'}</span>
          ) : (
            <span className="font-semibold text-amber-700">{t('clinic_offers.type_general', 'عرض عام')}</span>
          )}
        </div>

        {offer.price !== null && (
          <div className="mt-3 flex items-baseline gap-2">
            <span className="text-lg font-bold text-[var(--color-primary)]">
              {Number(offer.price).toLocaleString()} {t('common.sar', 'ر.س')}
            </span>
            {offer.old_price !== null && (
              <span className="text-sm text-[var(--color-muted-foreground)] line-through">
                {Number(offer.old_price).toLocaleString()}
              </span>
            )}
          </div>
        )}

        <Countdown endsAt={offer.ends_at} status={status} />

        <div className="mt-4 flex items-center justify-between gap-1">
          <div className="flex gap-1">
            <Button variant="outline" size="sm" onClick={onEdit}>
              <Pencil className="h-3.5 w-3.5" />
              {t('common.edit')}
            </Button>
            <Button variant="outline" size="sm" onClick={extendByWeek} disabled={extend.isPending}>
              <CalendarClock className="h-3.5 w-3.5" />
              {t('clinic_offers.extend_7d', 'تمديد 7 أيام')}
            </Button>
          </div>
          <div className="flex gap-1">
            <Button variant="ghost" size="icon" onClick={togglePaused} disabled={update.isPending}
              title={offer.is_active ? t('clinic_offers.pause', 'إيقاف مؤقت') : t('clinic_offers.resume', 'إعادة تفعيل')}>
              <Pause className="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" onClick={onDelete}
              className="text-[var(--color-destructive)]"
              title={t('common.delete')}>
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Countdown({ endsAt, status }: { endsAt: string | null; status: OfferStatus }) {
  const { t } = useTranslation();
  if (!endsAt) return null;

  const end = new Date(endsAt).getTime();
  const now = Date.now();
  const ms = end - now;

  if (status === 'expired') {
    return <p className="mt-2 text-xs text-gray-500">{t('clinic_offers.expired_at', 'انتهى في')}: {new Date(endsAt).toLocaleDateString()}</p>;
  }
  if (ms <= 0) return null;

  const hours = Math.floor(ms / (1000 * 60 * 60));
  const days = Math.floor(hours / 24);
  const urgent = hours < 24;

  return (
    <p className={`mt-2 text-xs ${urgent ? 'font-semibold text-red-600' : 'text-amber-600'}`}>
      {urgent
        ? t('clinic_offers.ends_in_hours', { defaultValue: 'ينتهي خلال {{h}} ساعة', h: hours })
        : t('clinic_offers.ends_in_days', { defaultValue: 'ينتهي خلال {{d}} أيام', d: days })}
    </p>
  );
}
