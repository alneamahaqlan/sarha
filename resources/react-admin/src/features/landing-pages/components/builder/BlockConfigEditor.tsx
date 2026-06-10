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

import { useUpdateBlock } from '../../hooks';
import type { LandingPageBlock } from '../../types';

interface Props {
  pageId: number;
  block: LandingPageBlock;
}

type Cfg = Record<string, unknown>;
interface FaqItem { q: string; a: string }

export function BlockConfigEditor({ pageId, block }: Props) {
  const { t } = useTranslation();
  const update = useUpdateBlock(pageId);
  const [cfg, setCfg] = useState<Cfg>(block.config ?? {});

  useEffect(() => { setCfg(block.config ?? {}); }, [block.config]);

  const set = (k: string, v: unknown) => setCfg((c) => ({ ...c, [k]: v }));
  const str = (k: string) => (cfg[k] as string) ?? '';
  const num = (k: string, d = 6) => (cfg[k] as number) ?? d;

  const save = async () => {
    try {
      await update.mutateAsync({ blockId: block.id, values: { config: cfg } });
      toast.success(t('landing_pages.block_saved'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const hasSource = ['services', 'offers', 'doctors', 'gallery', 'reviews'].includes(block.type);
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

      {hasSource && (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-1.5">
            <Label>{t('landing_pages.cfg_source')}</Label>
            <Select value={str('source') || 'auto'} onChange={(e) => set('source', e.target.value)}>
              <option value="auto">{t('landing_pages.cfg_source_auto')}</option>
              <option value="manual">{t('landing_pages.cfg_source_manual')}</option>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>{t('landing_pages.cfg_item_limit')}</Label>
            <Input type="number" min={1} max={24} value={num('item_limit')} onChange={(e) => set('item_limit', Number(e.target.value))} />
          </div>
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
