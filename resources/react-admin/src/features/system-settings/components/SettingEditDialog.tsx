import { useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useUpdateSystemSetting } from '../hooks';
import type { SystemSetting } from '../types';

interface Props {
  setting: SystemSetting;
  onClose: () => void;
}

/** Keys that should render a constrained <select> instead of free text. */
const SELECT_OPTIONS: Record<string, { value: string; label: string }[]> = {
  ai_provider: [
    { value: 'gemini',    label: 'Google Gemini Flash (افتراضي)' },
    { value: 'openai',    label: 'OpenAI (GPT)' },
    { value: 'anthropic', label: 'Anthropic (Claude)' },
  ],
};

export function SettingEditDialog({ setting, onClose }: Props) {
  const { t } = useTranslation();
  const isEncrypted = setting.type === 'encrypted';
  const isLongText  = setting.type === 'text';
  const selectOptions = SELECT_OPTIONS[setting.key];

  // Encrypted secrets are never pre-filled — leaving the input empty means
  // "keep the stored value" (matches backend semantics in SystemSettingController).
  const initialValue = isEncrypted ? '' : (setting.value ?? '');

  const [value, setValue] = useState(initialValue);
  const [showSecret, setShowSecret] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const mut = useUpdateSystemSetting(setting.id);

  const isBoolean = setting.type === 'boolean';
  const boolChecked = value === '1' || value === 'true';

  const onSubmit = async () => {
    try {
      await mut.mutateAsync({ value });
      toast.success(t('system_settings.saved'));
      onClose();
    } catch (e) {
      const v = extractValidationErrors(e);
      if (v?.value) setErr(v.value[0]);
      else toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className={isLongText ? 'sm:max-w-2xl' : undefined}>
        <DialogHeader>
          <DialogTitle>{setting.label}</DialogTitle>
          <DialogDescription dir="ltr" className="font-mono text-xs">{setting.key}</DialogDescription>
        </DialogHeader>

        {setting.description && (
          <p className="text-sm text-[var(--color-muted-foreground)]" dir="auto">{setting.description}</p>
        )}

        <div className="space-y-1.5">
          <Label htmlFor="value">{t('system_settings.value')}</Label>

          {isBoolean ? (
            <div className="flex items-center gap-3">
              <Switch checked={boolChecked} onCheckedChange={(c) => setValue(c ? '1' : '0')} />
              <span className="text-sm text-[var(--color-muted-foreground)]">
                {boolChecked ? t('common.yes') : t('common.no')}
              </span>
            </div>
          ) : selectOptions ? (
            <select
              id="value"
              value={value}
              onChange={(e) => { setValue(e.target.value); setErr(null); }}
              className="flex h-9 w-full rounded-md border border-[var(--color-border)] bg-transparent px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
              dir="ltr"
            >
              {selectOptions.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          ) : isEncrypted ? (
            <div className="space-y-1.5">
              <div className="relative">
                <Input
                  id="value"
                  type={showSecret ? 'text' : 'password'}
                  autoComplete="off"
                  spellCheck={false}
                  dir="ltr"
                  className="pe-10 font-mono"
                  placeholder={setting.value_set ? '•••• (اتركه فارغاً للإبقاء على المفتاح الحالي)' : 'أدخل المفتاح…'}
                  value={value}
                  onChange={(e) => { setValue(e.target.value); setErr(null); }}
                />
                <button
                  type="button"
                  onClick={() => setShowSecret((s) => !s)}
                  className="absolute end-2 top-1/2 -translate-y-1/2 text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]"
                  aria-label={showSecret ? 'إخفاء' : 'إظهار'}
                  tabIndex={-1}
                >
                  {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
              <p className="text-xs text-[var(--color-muted-foreground)]">
                {setting.value_set
                  ? 'المفتاح مُخزَّن ومُشفَّر. اتركه فارغاً للإبقاء، أو اكتب مفتاحاً جديداً للاستبدال.'
                  : 'سيتم تشفير المفتاح قبل تخزينه في قاعدة البيانات.'}
              </p>
            </div>
          ) : isLongText ? (
            <Textarea
              id="value"
              rows={12}
              className="font-mono text-sm leading-relaxed"
              placeholder="اتركه فارغاً لاستخدام القيمة الافتراضية المُضمَّنة في الكود."
              value={value}
              onChange={(e) => { setValue(e.target.value); setErr(null); }}
              dir="auto"
            />
          ) : setting.type === 'json' || (setting.value?.length ?? 0) > 60 ? (
            <Textarea id="value" rows={4} value={value} onChange={(e) => { setValue(e.target.value); setErr(null); }} />
          ) : (
            <Input
              id="value"
              type={setting.type === 'integer' || setting.type === 'decimal' ? 'number' : 'text'}
              value={value}
              onChange={(e) => { setValue(e.target.value); setErr(null); }}
            />
          )}

          {err && <p className="text-xs text-[var(--color-destructive)]">{err}</p>}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={mut.isPending}>
            {mut.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
