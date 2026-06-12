import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import {
  useCreateTeamMember, useUpdateTeamMember,
} from '../hooks';
import type { ClinicTeamMember, TeamMemberFormValues } from '../types';

/**
 * Add / edit dialog. Phone is editable only in CREATE mode (immutable
 * after creation to avoid login surprises). Role select excludes
 * "owner" since the owner is the Clinic itself, not a member row.
 *
 * On successful CREATE the dialog calls `onCreatedWithPassword(pw,
 * member)` so the parent can pop the PasswordRevealDialog.
 */
interface Props {
  open: boolean;
  member?: ClinicTeamMember | null;
  onClose: () => void;
  onCreatedWithPassword?: (password: string, member: ClinicTeamMember) => void;
}

export function TeamMemberFormDialog({ open, member, onClose, onCreatedWithPassword }: Props) {
  const { t } = useTranslation();
  const create = useCreateTeamMember();
  const update = useUpdateTeamMember(member?.id ?? 0);

  const [values, setValues] = useState<TeamMemberFormValues & { is_active?: boolean }>({
    name: '',
    phone: '',
    role: 'coordinator',
    is_active: true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (member) {
      setValues({
        name: member.name,
        phone: member.phone,
        role: (member.role === 'owner' ? 'coordinator' : member.role),
        is_active: member.is_active,
      });
    } else {
      setValues({ name: '', phone: '', role: 'coordinator', is_active: true });
    }
    setErrors({});
  }, [member, open]);

  const set = <K extends keyof typeof values>(k: K, v: (typeof values)[K]) =>
    setValues((s) => ({ ...s, [k]: v }));

  const onSubmit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    setErrors({});
    try {
      if (member) {
        await update.mutateAsync({
          name: values.name,
          role: values.role,
          is_active: values.is_active,
        });
        toast.success(t('clinic_team.updated'));
        onClose();
      } else {
        const result = await create.mutateAsync({
          name: values.name,
          phone: values.phone,
          role: values.role,
        });
        toast.success(t('clinic_team.created'));
        onClose();
        // Hand the temp password to the parent so it can fire the
        // one-time reveal dialog. We do this AFTER onClose so the
        // forms don't stack.
        onCreatedWithPassword?.(result.temp_password, result.data);
      }
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) {
        const flat: Record<string, string> = {};
        Object.entries(ve).forEach(([k, msgs]) => { flat[k] = msgs[0]; });
        setErrors(flat);
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{member ? t('clinic_team.edit_title') : t('clinic_team.create_title')}</DialogTitle>
        </DialogHeader>

        <form noValidate onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="name">{t('clinic_team.form.name')}</Label>
            <Input
              id="name"
              value={values.name}
              onChange={(e) => set('name', e.target.value)}
              required
            />
            <FieldError message={errors.name} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="phone">{t('clinic_team.form.phone')}</Label>
            <Input
              id="phone"
              dir="ltr"
              value={values.phone}
              onChange={(e) => set('phone', e.target.value)}
              disabled={Boolean(member)} // immutable on edit
              required
              placeholder="05XXXXXXXX"
            />
            {member && (
              <p className="text-[11px] text-[var(--color-muted-foreground)]">
                {t('clinic_team.form.phone_immutable_hint')}
              </p>
            )}
            <FieldError message={errors.phone} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="role">{t('clinic_team.form.role')}</Label>
            <Select
              id="role"
              value={values.role}
              onChange={(e) => set('role', e.target.value as 'coordinator' | 'reception')}
            >
              <option value="coordinator">{t('clinic_team.role.coordinator')}</option>
              <option value="reception">{t('clinic_team.role.reception')}</option>
            </Select>
            <FieldError message={errors.role} />
          </div>

          {member && (
            <div className="flex items-center justify-between rounded-md border border-[var(--color-border)] p-3">
              <div>
                <Label htmlFor="is_active" className="cursor-pointer">{t('clinic_team.form.is_active')}</Label>
                <p className="mt-0.5 text-[11px] text-[var(--color-muted-foreground)]">
                  {t('clinic_team.form.is_active_hint')}
                </p>
              </div>
              <Switch
                id="is_active"
                checked={Boolean(values.is_active)}
                onCheckedChange={(v) => set('is_active', v)}
              />
            </div>
          )}

          <FormErrorSummary
            errors={errors}
            labels={{
              name: t('clinic_team.form.name'),
              phone: t('clinic_team.form.phone'),
              role: t('clinic_team.form.role'),
            }}
          />

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? t('common.loading') : (member ? t('common.save') : t('clinic_team.create_button'))}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
