import { useState } from 'react';
import { Copy, Check, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter,
  DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';

/**
 * One-time-reveal dialog for a generated temp password. Per spec:
 * "كلمة سر مؤقتة (يُولّدها النظام، تُعرَض مرة واحدة للمدير ليُسلّمها
 * للعضو)". The password is shown after creating a member OR
 * regenerating one — once dismissed, there is no way to view it
 * again; the owner must regenerate to get a fresh one.
 */
interface Props {
  open: boolean;
  password: string | null;
  memberName: string;
  onClose: () => void;
}

export function PasswordRevealDialog({ open, password, memberName, onClose }: Props) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    if (!password) return;
    try {
      await navigator.clipboard.writeText(password);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      /* clipboard API may be blocked — user can select+copy manually */
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_team.temp_password_title', { name: memberName })}</DialogTitle>
          <DialogDescription>{t('clinic_team.temp_password_subtitle')}</DialogDescription>
        </DialogHeader>

        {/* Warning ribbon — the password CANNOT be retrieved later. */}
        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 flex items-start gap-2">
          <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
          <span>{t('clinic_team.temp_password_warning')}</span>
        </div>

        {/* Big mono-spaced password block + copy button */}
        <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-muted)]/40 p-4 flex items-center justify-between gap-3">
          <code className="text-lg font-mono tracking-wider select-all">{password ?? '—'}</code>
          <Button variant="outline" size="sm" onClick={copy}>
            {copied
              ? <><Check className="h-4 w-4" /> {t('clinic_team.copied')}</>
              : <><Copy className="h-4 w-4" /> {t('clinic_team.copy')}</>}
          </Button>
        </div>

        <DialogFooter>
          <Button onClick={onClose}>{t('common.done')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
