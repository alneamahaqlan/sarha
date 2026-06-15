import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Check, Search, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FieldError } from '@/components/forms/FieldError';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { badgesApi } from '../api';
import { useBadgeMeta, useCreateBadge, useUpdateBadge } from '../hooks';
import type { Badge, BadgeFormValues, BadgeTargetType, TargetLite } from '../types';
import { BadgeChip, BADGE_ICON_MAP, BADGE_COLOR_MAP } from './BadgeChip';

interface Props {
  open: boolean;
  badge: Badge | null; // null = create
  onClose: () => void;
}

const ALL_TYPES: BadgeTargetType[] = ['clinic', 'offer', 'service', 'doctor'];

const EMPTY: BadgeFormValues = {
  key: '',
  target_types: ['clinic'],
  label_ar: '',
  label_en: '',
  description_ar: null,
  description_en: null,
  icon: 'star-solid',
  color: 'gold',
  placement: 'both',
  mode: 'manual',
  rule_key: null,
  rule_params: {},
  is_active: true,
  sort_order: 50,
};

type TargetsMap = Record<BadgeTargetType, TargetLite[]>;
const emptyTargets = (): TargetsMap => ({ clinic: [], offer: [], service: [], doctor: [] });

export function BadgeEditDialog({ open, badge, onClose }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: meta } = useBadgeMeta();

  const [values, setValues] = useState<BadgeFormValues>(EMPTY);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [targets, setTargets] = useState<TargetsMap>(emptyTargets());
  const [activeType, setActiveType] = useState<BadgeTargetType>('clinic');
  const [search, setSearch] = useState('');
  const [results, setResults] = useState<TargetLite[]>([]);

  const create = useCreateBadge();
  const update = useUpdateBadge(badge?.id ?? 0);
  const busy = create.isPending || update.isPending;

  const set = <K extends keyof BadgeFormValues>(k: K, v: BadgeFormValues[K]) =>
    setValues((s) => ({ ...s, [k]: v }));

  const typeLabel = (alias: BadgeTargetType) => {
    const m = meta?.targets.find((x) => x.key === alias);
    return m ? (locale === 'en' ? m.label_en : m.label_ar) : alias;
  };

  useEffect(() => {
    if (!open) return;
    setErrors({});
    setSearch('');
    setResults([]);
    if (badge) {
      const tt: BadgeTargetType[] = badge.target_types?.length ? badge.target_types : ['clinic'];
      setValues({
        key: badge.key, target_types: tt,
        label_ar: badge.label_ar, label_en: badge.label_en,
        description_ar: badge.description_ar, description_en: badge.description_en,
        icon: badge.icon, color: badge.color, placement: badge.placement,
        mode: badge.mode, rule_key: badge.rule_key, rule_params: badge.rule_params ?? {},
        is_active: badge.is_active, sort_order: badge.sort_order,
      });
      setActiveType(tt[0]);
      // Preload manual assignments for editing.
      if (badge.mode === 'manual') {
        badgesApi.show(badge.id)
          .then((full) => setTargets({ ...emptyTargets(), ...(full.manual_targets ?? {}) }))
          .catch(() => setTargets(emptyTargets()));
      } else {
        setTargets(emptyTargets());
      }
    } else {
      setValues(EMPTY);
      setTargets(emptyTargets());
      setActiveType('clinic');
    }
  }, [open, badge]);

  // Keep the manual picker's active tab inside the chosen target types.
  useEffect(() => {
    if (!values.target_types.includes(activeType) && values.target_types.length > 0) {
      setActiveType(values.target_types[0]);
      setResults([]);
      setSearch('');
    }
  }, [values.target_types, activeType]);

  const toggleType = (alias: BadgeTargetType) => {
    setValues((s) => {
      const has = s.target_types.includes(alias);
      const next = has ? s.target_types.filter((x) => x !== alias) : [...s.target_types, alias];
      return { ...s, target_types: next.length ? next : s.target_types };
    });
  };

  // When an auto rule is picked, seed its default params (keep any overlapping values).
  const onRuleChange = (ruleKey: string) => {
    const rule = meta?.rules.find((r) => r.key === ruleKey);
    setValues((s) => ({
      ...s,
      rule_key: ruleKey,
      rule_params: { ...(rule?.default_params ?? {}), ...s.rule_params },
    }));
  };

  const runSearch = async (term: string) => {
    setSearch(term);
    if (term.trim().length < 2) { setResults([]); return; }
    try {
      const r = await badgesApi.searchTargets(activeType, term.trim());
      setResults(r.filter((c) => !(targets[activeType] ?? []).some((s) => s.id === c.id)));
    } catch { /* ignore */ }
  };

  const addTarget = (c: TargetLite) => {
    setTargets((s) => ({ ...s, [activeType]: [...(s[activeType] ?? []), c] }));
    setResults((s) => s.filter((r) => r.id !== c.id));
  };
  const removeTarget = (id: number) =>
    setTargets((s) => ({ ...s, [activeType]: (s[activeType] ?? []).filter((c) => c.id !== id) }));

  const onSubmit = async () => {
    setErrors({});
    const payload: BadgeFormValues = {
      ...values,
      description_ar: values.description_ar?.trim() ? values.description_ar : null,
      description_en: values.description_en?.trim() ? values.description_en : null,
      rule_key: values.mode === 'auto' ? values.rule_key : null,
      rule_params: values.mode === 'auto' ? values.rule_params : {},
    };
    try {
      const saved = badge ? await update.mutateAsync(payload) : await create.mutateAsync(payload);
      if (payload.mode === 'manual') {
        // Sync every target type: chosen types get their selections, the rest are cleared.
        await Promise.all(ALL_TYPES.map((type) =>
          badgesApi.syncTargets(saved.id, type, payload.target_types.includes(type)
            ? (targets[type] ?? []).map((c) => c.id)
            : [])));
      }
      toast.success(badge ? t('badges.updated') : t('badges.created'));
      onClose();
    } catch (e) {
      const v = extractValidationErrors(e);
      if (v) {
        const flat: Record<string, string> = {};
        Object.entries(v).forEach(([k, msgs]) => { flat[k] = Array.isArray(msgs) ? msgs[0] : String(msgs); });
        setErrors(flat);
      } else {
        toast.error(extractMessage(e, t('errors.generic')));
      }
    }
  };

  const previewLabel = locale === 'en' ? (values.label_en || 'Badge') : (values.label_ar || 'شارة');

  const iconList = meta?.icons ?? Object.keys(BADGE_ICON_MAP).map((key) => ({ key, label_ar: key }));

  // Auto rules limited to the chosen target types.
  const availableRules = useMemo(
    () => (meta?.rules ?? []).filter((r) => values.target_types.includes(r.target_type)),
    [meta?.rules, values.target_types],
  );

  const ruleParamKeys = values.mode === 'auto' && values.rule_key
    ? Object.keys(meta?.rules.find((r) => r.key === values.rule_key)?.default_params ?? values.rule_params)
    : [];

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{badge ? t('badges.edit') : t('badges.create')}</DialogTitle>
        </DialogHeader>

        {/* Live preview */}
        <div className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-muted)] px-3 py-2">
          <span className="text-xs text-[var(--color-muted-foreground)]">{t('badges.preview')}:</span>
          <BadgeChip icon={values.icon} color={values.color} label={previewLabel} />
        </div>

        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>{t('badges.label_ar')}</Label>
              <Input value={values.label_ar} onChange={(e) => set('label_ar', e.target.value)} dir="rtl" />
              <FieldError message={errors.label_ar} />
            </div>
            <div className="space-y-1">
              <Label>{t('badges.label_en')}</Label>
              <Input value={values.label_en} onChange={(e) => set('label_en', e.target.value)} dir="ltr" />
              <FieldError message={errors.label_en} />
            </div>
          </div>

          {/* Optional description (tooltip + homepage strip). */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>{t('badges.description_ar', 'وصف (عربي)')}</Label>
              <Input value={values.description_ar ?? ''} onChange={(e) => set('description_ar', e.target.value)} dir="rtl"
                placeholder={t('badges.description_hint', 'اختياري')} />
              <FieldError message={errors.description_ar} />
            </div>
            <div className="space-y-1">
              <Label>{t('badges.description_en', 'Description (EN)')}</Label>
              <Input value={values.description_en ?? ''} onChange={(e) => set('description_en', e.target.value)} dir="ltr"
                placeholder={t('badges.description_hint', 'اختياري')} />
              <FieldError message={errors.description_en} />
            </div>
          </div>

          <div className="space-y-1">
            <Label>{t('badges.key')}</Label>
            <Input value={values.key} onChange={(e) => set('key', e.target.value)} dir="ltr"
              placeholder="most-booked" disabled={!!badge} />
            <FieldError message={errors.key} />
          </div>

          {/* Target types — which entities this badge can be attached to. */}
          <div className="space-y-1">
            <Label>{t('badges.target_types', 'يُطبَّق على')}</Label>
            <div className="flex flex-wrap gap-1.5">
              {(meta?.targets ?? ALL_TYPES.map((k) => ({ key: k, label_ar: k, label_en: k }))).map((tt) => {
                const active = values.target_types.includes(tt.key as BadgeTargetType);
                return (
                  <button key={tt.key} type="button" onClick={() => toggleType(tt.key as BadgeTargetType)}
                    className={`rounded-full border px-3 py-1 text-xs font-medium transition ${active
                      ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                      : 'border-[var(--color-border)] text-[var(--color-muted-foreground)]'}`}>
                    {locale === 'en' ? tt.label_en : tt.label_ar}
                  </button>
                );
              })}
            </div>
            <FieldError message={errors.target_types} />
          </div>

          {/* Icon picker — visual grid with Arabic names. */}
          <div className="space-y-1">
            <Label>{t('badges.icon')}</Label>
            <div className="grid grid-cols-4 gap-1.5 sm:grid-cols-6">
              {iconList.map((ic) => {
                const Icon = BADGE_ICON_MAP[ic.key] ?? BADGE_ICON_MAP['star-solid'];
                const selected = values.icon === ic.key;
                return (
                  <button key={ic.key} type="button" title={ic.key} onClick={() => set('icon', ic.key)}
                    className={`relative flex flex-col items-center gap-1 rounded-lg border p-1.5 transition ${selected
                      ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
                      : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'}`}>
                    <Icon className="h-5 w-5 text-[var(--color-foreground)]" />
                    <span className="w-full truncate text-center text-[10px] leading-tight text-[var(--color-muted-foreground)]">{ic.label_ar}</span>
                    {selected && <Check className="absolute end-0.5 top-0.5 h-3 w-3 text-[var(--color-primary)]" />}
                  </button>
                );
              })}
            </div>
            <FieldError message={errors.icon} />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>{t('badges.color')}</Label>
              <div className="flex flex-wrap gap-1.5 pt-1">
                {(meta?.colors ?? Object.keys(BADGE_COLOR_MAP)).map((col) => (
                  <button key={col} type="button" onClick={() => set('color', col)}
                    title={col}
                    className={`h-7 w-7 rounded-full ring-2 ${BADGE_COLOR_MAP[col] ?? ''} ${values.color === col ? 'ring-[var(--color-primary)]' : 'ring-transparent'}`} />
                ))}
              </div>
              <FieldError message={errors.color} />
            </div>
            <div className="space-y-1">
              <Label>{t('badges.sort_order')}</Label>
              <Input type="number" value={values.sort_order} onChange={(e) => set('sort_order', Number(e.target.value))} />
            </div>
          </div>

          <div className="space-y-1">
            <Label>{t('badges.placement')}</Label>
            <Select value={values.placement} onChange={(e) => set('placement', e.target.value as BadgeFormValues['placement'])}>
              <option value="both">{t('badges.placement_both')}</option>
              <option value="header">{t('badges.placement_header')}</option>
              <option value="cards">{t('badges.placement_cards')}</option>
            </Select>
          </div>

          <div className="space-y-1">
            <Label>{t('badges.mode')}</Label>
            <Select value={values.mode} onChange={(e) => set('mode', e.target.value as BadgeFormValues['mode'])}>
              <option value="manual">{t('badges.mode_manual')}</option>
              <option value="auto">{t('badges.mode_auto')}</option>
            </Select>
            <p className="text-[11px] text-[var(--color-muted-foreground)]">
              {values.mode === 'auto' ? t('badges.mode_auto_hint') : t('badges.mode_manual_hint')}
            </p>
          </div>

          {/* AUTO: rule + params */}
          {values.mode === 'auto' && (
            <div className="space-y-3 rounded-lg border border-[var(--color-border)] p-3">
              <div className="space-y-1">
                <Label>{t('badges.rule')}</Label>
                <Select value={values.rule_key ?? ''} onChange={(e) => onRuleChange(e.target.value)}>
                  <option value="" disabled>{t('badges.rule_pick')}</option>
                  {availableRules.map((r) => (
                    <option key={r.key} value={r.key}>
                      {(locale === 'en' ? r.label_en : r.label_ar)} · {typeLabel(r.target_type)}
                    </option>
                  ))}
                </Select>
                <FieldError message={errors.rule_key} />
              </div>
              {ruleParamKeys.length > 0 && (
                <div className="grid grid-cols-2 gap-3">
                  {ruleParamKeys.map((pk) => (
                    <div key={pk} className="space-y-1">
                      <Label className="text-xs">{t(`badges.param.${pk}`, pk)}</Label>
                      <Input type="number" value={values.rule_params[pk] ?? 0}
                        onChange={(e) => setValues((s) => ({ ...s, rule_params: { ...s.rule_params, [pk]: Number(e.target.value) } }))} />
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* MANUAL: per-type target picker */}
          {values.mode === 'manual' && (
            <div className="space-y-2 rounded-lg border border-[var(--color-border)] p-3">
              <Label>{t('badges.assign_targets', 'إسناد يدوي')}</Label>

              {/* Type tabs (only the chosen target types). */}
              {values.target_types.length > 1 && (
                <div className="flex flex-wrap gap-1.5">
                  {values.target_types.map((tt) => (
                    <button key={tt} type="button"
                      onClick={() => { setActiveType(tt); setResults([]); setSearch(''); }}
                      className={`rounded-md px-2.5 py-1 text-xs font-medium ${activeType === tt
                        ? 'bg-[var(--color-muted)] text-[var(--color-foreground)]'
                        : 'text-[var(--color-muted-foreground)]'}`}>
                      {typeLabel(tt)} ({(targets[tt] ?? []).length})
                    </button>
                  ))}
                </div>
              )}

              <div className="relative">
                <Search className="absolute start-2 top-2.5 h-4 w-4 text-[var(--color-muted-foreground)]" />
                <Input className="ps-8" value={search} onChange={(e) => runSearch(e.target.value)}
                  placeholder={t('badges.search_targets', 'ابحث…') + ' — ' + typeLabel(activeType)} />
              </div>
              {results.length > 0 && (
                <div className="max-h-40 overflow-auto rounded-md border border-[var(--color-border)]">
                  {results.map((c) => (
                    <button key={c.id} type="button" onClick={() => addTarget(c)}
                      className="block w-full px-3 py-1.5 text-start text-sm hover:bg-[var(--color-muted)]">
                      {c.name}
                    </button>
                  ))}
                </div>
              )}
              <div className="flex flex-wrap gap-1.5">
                {(targets[activeType] ?? []).map((c) => (
                  <span key={c.id} className="inline-flex items-center gap-1 rounded-full bg-sage-50 px-2 py-0.5 text-xs text-sage-700">
                    {c.name}
                    <button type="button" onClick={() => removeTarget(c.id)}><X className="h-3 w-3" /></button>
                  </span>
                ))}
                {(targets[activeType] ?? []).length === 0 &&
                  <span className="text-xs text-[var(--color-muted-foreground)]">{t('badges.no_targets', 'لا يوجد')}</span>}
              </div>
            </div>
          )}

          <label className="flex items-center justify-between rounded-lg border border-[var(--color-border)] px-3 py-2">
            <span className="text-sm font-medium">{t('badges.is_active')}</span>
            <Switch checked={values.is_active} onCheckedChange={(c) => set('is_active', c)} />
          </label>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={busy}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={busy}>{t('common.save')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
