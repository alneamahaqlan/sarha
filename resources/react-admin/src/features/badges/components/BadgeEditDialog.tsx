import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Search, X } from 'lucide-react';

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
import type { Badge, BadgeFormValues, ClinicLite } from '../types';
import { BadgeChip, BADGE_ICON_MAP, BADGE_COLOR_MAP } from './BadgeChip';

interface Props {
  open: boolean;
  badge: Badge | null; // null = create
  onClose: () => void;
}

const EMPTY: BadgeFormValues = {
  key: '',
  label_ar: '',
  label_en: '',
  icon: 'star-solid',
  color: 'gold',
  placement: 'both',
  mode: 'manual',
  rule_key: null,
  rule_params: {},
  is_active: true,
  sort_order: 50,
};

export function BadgeEditDialog({ open, badge, onClose }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: meta } = useBadgeMeta();

  const [values, setValues] = useState<BadgeFormValues>(EMPTY);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [clinics, setClinics] = useState<ClinicLite[]>([]); // manual selections
  const [search, setSearch] = useState('');
  const [results, setResults] = useState<ClinicLite[]>([]);

  const create = useCreateBadge();
  const update = useUpdateBadge(badge?.id ?? 0);
  const busy = create.isPending || update.isPending;

  const set = <K extends keyof BadgeFormValues>(k: K, v: BadgeFormValues[K]) =>
    setValues((s) => ({ ...s, [k]: v }));

  useEffect(() => {
    if (!open) return;
    setErrors({});
    setSearch('');
    setResults([]);
    if (badge) {
      setValues({
        key: badge.key, label_ar: badge.label_ar, label_en: badge.label_en,
        icon: badge.icon, color: badge.color, placement: badge.placement,
        mode: badge.mode, rule_key: badge.rule_key, rule_params: badge.rule_params ?? {},
        is_active: badge.is_active, sort_order: badge.sort_order,
      });
      // Preload manual assignments for editing.
      if (badge.mode === 'manual') {
        badgesApi.show(badge.id).then((full) => setClinics(full.manual_clinics ?? [])).catch(() => setClinics([]));
      } else {
        setClinics([]);
      }
    } else {
      setValues(EMPTY);
      setClinics([]);
    }
  }, [open, badge]);

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
      const r = await badgesApi.searchClinics(term.trim());
      setResults(r.filter((c) => !clinics.some((s) => s.id === c.id)));
    } catch { /* ignore */ }
  };

  const addClinic = (c: ClinicLite) => {
    setClinics((s) => [...s, c]);
    setResults((s) => s.filter((r) => r.id !== c.id));
  };
  const removeClinic = (id: number) => setClinics((s) => s.filter((c) => c.id !== id));

  const onSubmit = async () => {
    setErrors({});
    const payload: BadgeFormValues = {
      ...values,
      rule_key: values.mode === 'auto' ? values.rule_key : null,
      rule_params: values.mode === 'auto' ? values.rule_params : {},
    };
    try {
      const saved = badge ? await update.mutateAsync(payload) : await create.mutateAsync(payload);
      if (payload.mode === 'manual') {
        await badgesApi.syncClinics(saved.id, clinics.map((c) => c.id));
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

          <div className="space-y-1">
            <Label>{t('badges.key')}</Label>
            <Input value={values.key} onChange={(e) => set('key', e.target.value)} dir="ltr"
              placeholder="most-booked" disabled={!!badge} />
            <FieldError message={errors.key} />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>{t('badges.icon')}</Label>
              <Select value={values.icon} onChange={(e) => set('icon', e.target.value)}>
                {(meta?.icons ?? Object.keys(BADGE_ICON_MAP)).map((ic) => (
                  <option key={ic} value={ic}>{ic}</option>
                ))}
              </Select>
              <FieldError message={errors.icon} />
            </div>
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
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>{t('badges.placement')}</Label>
              <Select value={values.placement} onChange={(e) => set('placement', e.target.value as BadgeFormValues['placement'])}>
                <option value="both">{t('badges.placement_both')}</option>
                <option value="header">{t('badges.placement_header')}</option>
                <option value="cards">{t('badges.placement_cards')}</option>
              </Select>
            </div>
            <div className="space-y-1">
              <Label>{t('badges.sort_order')}</Label>
              <Input type="number" value={values.sort_order} onChange={(e) => set('sort_order', Number(e.target.value))} />
            </div>
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
                  {meta?.rules.map((r) => (
                    <option key={r.key} value={r.key}>{locale === 'en' ? r.label_en : r.label_ar}</option>
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

          {/* MANUAL: clinic picker */}
          {values.mode === 'manual' && (
            <div className="space-y-2 rounded-lg border border-[var(--color-border)] p-3">
              <Label>{t('badges.assign_clinics')}</Label>
              <div className="relative">
                <Search className="absolute start-2 top-2.5 h-4 w-4 text-[var(--color-muted-foreground)]" />
                <Input className="ps-8" value={search} onChange={(e) => runSearch(e.target.value)}
                  placeholder={t('badges.search_clinics')} />
              </div>
              {results.length > 0 && (
                <div className="max-h-40 overflow-auto rounded-md border border-[var(--color-border)]">
                  {results.map((c) => (
                    <button key={c.id} type="button" onClick={() => addClinic(c)}
                      className="block w-full px-3 py-1.5 text-start text-sm hover:bg-[var(--color-muted)]">
                      {c.name}
                    </button>
                  ))}
                </div>
              )}
              <div className="flex flex-wrap gap-1.5">
                {clinics.map((c) => (
                  <span key={c.id} className="inline-flex items-center gap-1 rounded-full bg-sage-50 px-2 py-0.5 text-xs text-sage-700">
                    {c.name}
                    <button type="button" onClick={() => removeClinic(c.id)}><X className="h-3 w-3" /></button>
                  </span>
                ))}
                {clinics.length === 0 && <span className="text-xs text-[var(--color-muted-foreground)]">{t('badges.no_clinics')}</span>}
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
