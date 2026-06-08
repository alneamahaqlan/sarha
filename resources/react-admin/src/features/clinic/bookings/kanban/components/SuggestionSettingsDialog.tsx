import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Clock, PhoneCall, Repeat, AlertTriangle, Sparkles, RotateCcw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useSuggestionSettings, useUpdateSuggestionSettings } from '../hooks';
import type { SuggestionKey, SuggestionSettings } from '../types';

/**
 * Client mirror of the server bounds (Clinic::SUGGESTION_BOUNDS). `field`
 * is the tunable threshold for each suggestion — absent for first_contact,
 * which is purely a presence check with no threshold.
 */
const META: Record<SuggestionKey, {
  icon: typeof Clock;
  tone: string;
  field?: 'hours' | 'count';
  min: number;
  max: number;
  fallback: number;
}> = {
  confirm_urgent: { icon: Clock, tone: 'text-red-600', field: 'hours', min: 1, max: 168, fallback: 24 },
  first_contact: { icon: Sparkles, tone: 'text-blue-600', min: 0, max: 0, fallback: 0 },
  retry_call: { icon: Repeat, tone: 'text-amber-600', field: 'hours', min: 1, max: 72, fallback: 2 },
  reminder_soon: { icon: PhoneCall, tone: 'text-amber-600', field: 'hours', min: 1, max: 168, fallback: 48 },
  cancel_risk: { icon: AlertTriangle, tone: 'text-rose-600', field: 'count', min: 1, max: 10, fallback: 2 },
};

const ORDER: SuggestionKey[] = ['confirm_urgent', 'first_contact', 'retry_call', 'reminder_soon', 'cancel_risk'];

/** Per-clinic Kanban suggestions: toggle each nudge on/off + tune its threshold. */
export function SuggestionSettingsDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation();
  const { data: settings, isLoading } = useSuggestionSettings();
  const saveMut = useUpdateSuggestionSettings();

  const [draft, setDraft] = useState<SuggestionSettings | null>(null);

  // Seed the editable draft once the server settings arrive.
  useEffect(() => {
    if (settings && !draft) setDraft(structuredClone(settings));
  }, [settings, draft]);

  const dirty = !!draft && !!settings && JSON.stringify(draft) !== JSON.stringify(settings);

  function setEnabled(key: SuggestionKey, enabled: boolean) {
    setDraft((d) => (d ? { ...d, [key]: { ...d[key], enabled } } : d));
  }

  function setThreshold(key: SuggestionKey, raw: string) {
    const field = META[key].field;
    if (!field) return;
    const n = Number(raw);
    setDraft((d) => (d ? { ...d, [key]: { ...d[key], [field]: Number.isFinite(n) ? n : d[key][field] } } : d));
  }

  function clampThreshold(key: SuggestionKey) {
    const { field, min, max, fallback } = META[key];
    if (!field) return;
    setDraft((d) => {
      if (!d) return d;
      const current = d[key][field];
      const safe = current == null || Number.isNaN(current) ? fallback : Math.max(min, Math.min(max, current));
      return { ...d, [key]: { ...d[key], [field]: safe } };
    });
  }

  async function onSave() {
    if (!draft) return;
    try {
      await saveMut.mutateAsync(draft);
      toast.success(t('clinic_bookings_kanban.suggestion_settings.saved'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings_kanban.suggestion_settings.title')}</DialogTitle>
          <DialogDescription>{t('clinic_bookings_kanban.suggestion_settings.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="max-h-[55vh] space-y-2 overflow-y-auto pe-1">
          {isLoading || !draft ? (
            <p className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">
              {t('common.loading')}
            </p>
          ) : (
            ORDER.map((key) => {
              const meta = META[key];
              const Icon = meta.icon;
              const row = draft[key];
              const field = meta.field;
              return (
                <div
                  key={key}
                  className="flex items-start gap-3 rounded-lg border border-[var(--color-border)] bg-white p-3"
                >
                  <Icon className={`mt-0.5 h-4 w-4 shrink-0 ${meta.tone}`} />
                  <div className="min-w-0 flex-1">
                    <div className="text-sm font-medium">
                      {t(`clinic_bookings_kanban.suggestion.${key}`)}
                    </div>
                    <p className="mt-0.5 text-[11px] leading-relaxed text-[var(--color-muted-foreground)]">
                      {t(`clinic_bookings_kanban.suggestion_settings.desc.${key}`)}
                    </p>
                    {field && (
                      <div className="mt-2 flex items-center gap-2">
                        <span className="text-[11px] text-[var(--color-muted-foreground)]">
                          {t(`clinic_bookings_kanban.suggestion_settings.threshold.${key}`)}
                        </span>
                        <Input
                          type="number"
                          min={meta.min}
                          max={meta.max}
                          value={row[field] ?? meta.fallback}
                          disabled={!row.enabled}
                          onChange={(e) => setThreshold(key, e.target.value)}
                          onBlur={() => clampThreshold(key)}
                          className="h-8 w-20 text-center"
                        />
                        <span className="text-[11px] text-[var(--color-muted-foreground)]">
                          {t(`clinic_bookings_kanban.suggestion_settings.unit.${field}`)}
                        </span>
                      </div>
                    )}
                  </div>
                  <Switch
                    checked={row.enabled}
                    onCheckedChange={(c) => setEnabled(key, c)}
                    className="mt-0.5 shrink-0"
                  />
                </div>
              );
            })
          )}
        </div>

        <p className="flex items-start gap-1.5 text-[11px] leading-relaxed text-[var(--color-muted-foreground)]">
          <RotateCcw className="mt-0.5 h-3 w-3 shrink-0" />
          {t('clinic_bookings_kanban.suggestion_settings.hint')}
        </p>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.close')}</Button>
          <Button onClick={onSave} disabled={!dirty || saveMut.isPending}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
