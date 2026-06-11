import { useEffect, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useServiceLookup, useOfferLookup, useDoctorLookup, useBeforeAfterLookup } from '@/features/lookups/hooks';

import { useUpdateBlock } from '../../hooks';
import type { LandingPageBlock } from '../../types';

interface Props {
  pageId: number;
  clinicId: number | null;
  block: LandingPageBlock;
}

interface NamedItem { id: number; name: string }

/** Manual multi-select of the linked complex's items for a content block. */
function ManualPicker({ items, selected, onChange, emptyHint }: {
  items: NamedItem[] | undefined;
  selected: number[];
  onChange: (ids: number[]) => void;
  emptyHint: string;
}) {
  const toggle = (id: number) =>
    onChange(selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id]);

  if (!items || items.length === 0) {
    return <p className="text-xs text-[var(--color-muted-foreground)]">{emptyHint}</p>;
  }
  return (
    <div className="max-h-52 space-y-1 overflow-y-auto rounded-md border border-[var(--color-border)] p-2">
      {items.map((it) => (
        <label key={it.id} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-[var(--color-muted)]">
          <input type="checkbox" checked={selected.includes(it.id)} onChange={() => toggle(it.id)} />
          <span className="text-sm">{it.name}</span>
        </label>
      ))}
    </div>
  );
}

type Cfg = Record<string, unknown>;
interface FaqItem { q: string; a: string }

export function BlockConfigEditor({ pageId, clinicId, block }: Props) {
  const { t } = useTranslation();
  const update = useUpdateBlock(pageId);
  const [cfg, setCfg] = useState<Cfg>(block.config ?? {});

  useEffect(() => { setCfg(block.config ?? {}); }, [block.config]);

  const set = (k: string, v: unknown) => setCfg((c) => ({ ...c, [k]: v }));
  const str = (k: string) => (cfg[k] as string) ?? '';
  const num = (k: string, d = 6) => (cfg[k] as number) ?? d;

  const isManual = (cfg.source ?? 'auto') === 'manual';
  const manualIds = Array.isArray(cfg.manual_ids) ? (cfg.manual_ids as number[]) : [];
  const cid = clinicId ?? undefined;

  // Only the active block's lookup actually fetches (enabled by clinic id).
  const services = useServiceLookup(block.type === 'services' && isManual ? cid : undefined);
  const offers = useOfferLookup(block.type === 'offers' && isManual ? cid : undefined);
  const doctors = useDoctorLookup(block.type === 'doctors' && isManual ? cid : undefined);
  const gallery = useBeforeAfterLookup(block.type === 'gallery' && isManual ? cid : undefined);

  const manualItems =
    block.type === 'services' ? services.data :
    block.type === 'offers' ? offers.data :
    block.type === 'doctors' ? doctors.data :
    block.type === 'gallery' ? gallery.data : undefined;

  const save = async () => {
    try {
      await update.mutateAsync({ blockId: block.id, values: { config: cfg } });
      toast.success(t('landing_pages.block_saved'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  // Reviews are Google reviews (can't cherry-pick), so they're auto-only with
  // just a limit. The other content blocks support auto/manual selection.
  const hasManual = ['services', 'offers', 'doctors', 'gallery'].includes(block.type);
  const hasLimit = hasManual || block.type === 'reviews';
  const faqItems: FaqItem[] = Array.isArray(cfg.items) ? (cfg.items as FaqItem[]) : [];

  return (
    <div className="space-y-4">
      {(block.type === 'hero' || block.type === 'booking' || block.type === 'countdown') && (
        <div className="space-y-1.5">
          <Label>{t('landing_pages.cfg_heading')}</Label>
          <Input value={str('heading')} onChange={(e) => set('heading', e.target.value)} />
        </div>
      )}

      {block.type === 'hero' && (
        <>
          <div className="space-y-1.5">
            <Label>{t('landing_pages.cfg_subheading')}</Label>
            <Textarea rows={2} value={str('subheading')} onChange={(e) => set('subheading', e.target.value)} />
          </div>
          <div className="flex items-center gap-3">
            <Switch checked={(cfg.show_cta as boolean) ?? true} onCheckedChange={(v) => set('show_cta', v)} />
            <Label>{t('landing_pages.cfg_show_cta')}</Label>
          </div>
        </>
      )}

      {(hasManual || hasLimit) && (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {hasManual && (
            <div className="space-y-1.5">
              <Label>{t('landing_pages.cfg_source')}</Label>
              <Select value={str('source') || 'auto'} onChange={(e) => set('source', e.target.value)}>
                <option value="auto">{t('landing_pages.cfg_source_auto')}</option>
                <option value="manual">{t('landing_pages.cfg_source_manual')}</option>
              </Select>
            </div>
          )}
          {/* Item limit applies to auto mode (manual shows exactly what's picked). */}
          {(!isManual || !hasManual) && (
            <div className="space-y-1.5">
              <Label>{t('landing_pages.cfg_item_limit')}</Label>
              <Input type="number" min={1} max={24} value={num('item_limit')} onChange={(e) => set('item_limit', Number(e.target.value))} />
            </div>
          )}
        </div>
      )}

      {hasManual && isManual && (
        <div className="space-y-1.5">
          <Label>{t('landing_pages.cfg_manual_pick')}</Label>
          {!clinicId ? (
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('landing_pages.cfg_manual_no_clinic')}</p>
          ) : (
            <ManualPicker
              items={manualItems}
              selected={manualIds}
              onChange={(ids) => set('manual_ids', ids)}
              emptyHint={t('landing_pages.cfg_manual_empty')}
            />
          )}
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('landing_pages.cfg_manual_hint')}</p>
        </div>
      )}

      {block.type === 'countdown' && (
        <div className="space-y-1.5">
          <Label>{t('landing_pages.cfg_countdown_target')}</Label>
          <Input type="datetime-local" dir="ltr" value={str('target')} onChange={(e) => set('target', e.target.value)} />
        </div>
      )}

      {block.type === 'faq' && (
        <div className="space-y-3">
          <Label>{t('landing_pages.cfg_faq_items')}</Label>
          {faqItems.map((item, i) => (
            <div key={i} className="rounded-md border border-[var(--color-border)] p-3 space-y-2">
              <div className="flex items-center gap-2">
                <Input
                  placeholder={t('landing_pages.cfg_faq_q')}
                  value={item.q}
                  onChange={(e) => {
                    const next = [...faqItems];
                    next[i] = { ...next[i], q: e.target.value };
                    set('items', next);
                  }}
                />
                <Button type="button" variant="ghost" size="icon" className="text-[var(--color-destructive)]" onClick={() => set('items', faqItems.filter((_, x) => x !== i))} aria-label={t('common.delete')}>
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
              <Textarea
                placeholder={t('landing_pages.cfg_faq_a')}
                rows={2}
                value={item.a}
                onChange={(e) => {
                  const next = [...faqItems];
                  next[i] = { ...next[i], a: e.target.value };
                  set('items', next);
                }}
              />
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" onClick={() => set('items', [...faqItems, { q: '', a: '' }])}>
            <Plus className="h-4 w-4" />
            {t('landing_pages.cfg_faq_add')}
          </Button>
        </div>
      )}

      {block.type === 'map' && (
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('landing_pages.cfg_map_hint')}</p>
      )}

      <div className="flex justify-end">
        <Button type="button" size="sm" onClick={save} disabled={update.isPending}>
          {update.isPending ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </div>
  );
}
