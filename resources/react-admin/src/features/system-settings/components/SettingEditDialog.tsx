import { useState } from 'react';
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

export function SettingEditDialog({ setting, onClose }: Props) {
  const { t } = useTranslation();
  const [value, setValue] = useState(setting.value ?? '');
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
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{setting.label}</DialogTitle>
          <DialogDescription dir="ltr" className="font-mono text-xs">{setting.key}</DialogDescription>
        </DialogHeader>

        {setting.description && (
          <p className="text-sm text-[var(--color-muted-foreground)]">{setting.description}</p>
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
