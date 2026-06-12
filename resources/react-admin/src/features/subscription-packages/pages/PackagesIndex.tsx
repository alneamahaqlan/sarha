import { useState } from 'react';
import { CheckCircle2, Clock, Edit3, Plus, Trash2, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { RiyalSymbol } from '@/lib/money';
import { extractMessage } from '@/lib/api-client';

import { useDeletePackage, usePackages } from '../hooks';
import { PackageEditDialog } from '../components/PackageEditDialog';
import type { ColorToken, SubscriptionPackage } from '../types';

/**
 * Maps the abstract color_token to concrete Tailwind classes for
 * the price headline + accent strip. Kept here so the React layer
 * doesn't leak brand-hex knowledge into the API.
 */
const COLOR_CLASSES: Record<ColorToken, { strip: string; price: string; chip: string }> = {
  gray:    { strip: 'bg-gray-300',           price: 'text-gray-700',    chip: 'bg-gray-100 text-gray-700' },
  sage:    { strip: 'bg-sage-500',           price: 'text-sage-700',    chip: 'bg-sage-100 text-sage-700' },
  gold:    { strip: 'bg-gold-primary',       price: 'text-gold-deep',   chip: 'bg-gold-whisper text-gold-deep' },
  plum:    { strip: 'bg-plum-primary',       price: 'text-plum-deep',   chip: 'bg-plum-whisper text-plum-deep' },
  info:    { strip: 'bg-blue-400',           price: 'text-blue-700',    chip: 'bg-blue-50 text-blue-700' },
  warning: { strip: 'bg-amber-400',          price: 'text-amber-700',   chip: 'bg-amber-50 text-amber-700' },
};

export function PackagesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: packages, isLoading } = usePackages();
  const [editing, setEditing] = useState<SubscriptionPackage | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<SubscriptionPackage | null>(null);
  const del = useDeletePackage();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('packages.deleted'));
      setDeleting(null);
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
      setDeleting(null);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl sm:text-2xl font-semibold">{t('packages.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('packages.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)} className="self-start sm:self-auto">
          <Plus className="h-4 w-4" />
          {t('packages.create')}
        </Button>
      </div>

      {isLoading && (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {packages?.map((p) => {
          const c = COLOR_CLASSES[p.color_token] ?? COLOR_CLASSES.gray;
          const name = locale === 'ar' ? p.name_ar : p.name_en;
          const tagline = locale === 'ar' ? p.tagline_ar : p.tagline_en;
          return (
            <div key={p.id} className="relative overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm">
              <div className={`h-1.5 ${c.strip}`} />
              <div className="p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0 flex-1">
                    <h2 className="text-lg font-semibold truncate">{name}</h2>
                    {tagline && <p className="text-xs text-[var(--color-muted-foreground)] line-clamp-2">{tagline}</p>}
                  </div>
                  <span className={`shrink-0 text-[10px] px-2 py-0.5 rounded-full ${c.chip}`}>{p.slug}</span>
                </div>

                <div className="flex items-baseline gap-1">
                  {p.monthly_price === 0 ? (
                    <span className={`text-2xl font-bold ${c.price}`}>{t('packages.free')}</span>
                  ) : (
                    <>
                      <span className={`text-2xl font-bold ${c.price}`}>{p.monthly_price.toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US')} <RiyalSymbol /></span>
                      <span className="text-xs text-[var(--color-muted-foreground)]">{t('packages.sar_per_month').replace('ر.س', '').replace('SAR', '')}</span>
                    </>
                  )}
                </div>

                <div className="flex flex-wrap gap-1">
                  {p.is_active
                    ? <Badge variant="success" className="text-[10px]"><CheckCircle2 className="h-2.5 w-2.5" /> {t('packages.active')}</Badge>
                    : <Badge variant="muted" className="text-[10px]"><Clock className="h-2.5 w-2.5" /> {t('packages.inactive')}</Badge>}
                  <Badge variant="muted" className="text-[10px]">{t('packages.clinics_count', { count: p.clinics_count })}</Badge>
                </div>

                {/* Limits summary */}
                <div className="grid grid-cols-2 gap-1 text-xs">
                  <Quota label={t('packages.q.services')} value={p.services_limit} t={t} />
                  <Quota label={t('packages.q.articles')} value={p.articles_monthly_limit} t={t} />
                  <Quota label={t('packages.q.replies')} value={p.quote_replies_monthly_limit} t={t} />
                  <Quota label={t('packages.q.banners')} value={p.banner_slots} t={t} />
                </div>

                {/* Features dotted */}
                <ul className="text-xs space-y-0.5">
                  <Feat ok={p.ai_article_generation} label={t('packages.f.ai_article_generation')} />
                  <Feat ok={p.featured_in_search} label={t('packages.f.featured_in_search')} />
                  <Feat ok={p.google_reviews_sync} label={t('packages.f.google_reviews_sync')} />
                  <Feat ok={p.verified_badge} label={t('packages.f.verified_badge')} />
                  <Feat ok={p.allow_offers_packages} label={t('packages.f.allow_offers_packages')} />
                  <Feat ok={p.allow_doctors_before_after} label={t('packages.f.allow_doctors_before_after')} />
                </ul>

                <div className="flex items-center gap-1 pt-2 border-t border-[var(--color-border)]">
                  <Button variant="ghost" size="sm" onClick={() => setEditing(p)}>
                    <Edit3 className="h-3.5 w-3.5" /> {t('common.edit')}
                  </Button>
                  <Button
                    variant="ghost" size="sm"
                    onClick={() => setDeleting(p)}
                    disabled={p.clinics_count > 0}
                    title={p.clinics_count > 0 ? t('packages.cannot_delete_has_clinics') : undefined}
                    className="text-[var(--color-destructive)]"
                  >
                    <Trash2 className="h-3.5 w-3.5" /> {t('common.delete')}
                  </Button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <PackageEditDialog
        open={creating || editing !== null}
        pkg={editing}
        onClose={() => { setCreating(false); setEditing(null); }}
      />

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('packages.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('packages.delete_confirm_body', { name: deleting ? (locale === 'ar' ? deleting.name_ar : deleting.name_en) : '' })}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>{t('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function Quota({ label, value, t }: { label: string; value: number | null; t: (k: string) => string }) {
  return (
    <div className="flex items-center justify-between rounded-md bg-[var(--color-muted)]/40 px-2 py-1">
      <span className="text-[var(--color-muted-foreground)]">{label}</span>
      <span className="font-medium">{value === null ? '∞' : value}</span>
    </div>
  );
}

function Feat({ ok, label }: { ok: boolean; label: string }) {
  return (
    <li className="flex items-center gap-1.5">
      {ok ? <CheckCircle2 className="h-3 w-3 text-emerald-600" /> : <XCircle className="h-3 w-3 text-gray-300" />}
      <span className={ok ? '' : 'text-[var(--color-muted-foreground)] line-through'}>{label}</span>
    </li>
  );
}
