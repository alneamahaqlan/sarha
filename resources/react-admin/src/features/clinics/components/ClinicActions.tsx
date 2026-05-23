import { useState } from 'react';
import { toast } from 'sonner';
import {
  CheckCircle2,
  CalendarPlus,
  Eye,
  MoreHorizontal,
  PauseCircle,
  XCircle,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import {
  useActivateClinic,
  useApproveClinic,
  useExtendClinic,
  useImpersonateClinic,
  useRejectClinic,
  useSuspendClinic,
} from '../hooks';
import type { Clinic } from '../types';

type ActionKind = 'approve' | 'reject' | 'activate' | 'suspend' | 'extend_30' | 'extend_90' | 'impersonate';

export function ClinicActionsMenu({ clinic }: { clinic: Clinic }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState<ActionKind | null>(null);

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon" aria-label={t('common.actions')}>
            <MoreHorizontal className="h-4 w-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {clinic.status === 'pending' && (
            <>
              <DropdownMenuItem onClick={() => setOpen('approve')}>
                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                {t('clinics.actions.approve')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setOpen('reject')} destructive>
                <XCircle className="h-4 w-4" />
                {t('clinics.actions.reject')}
              </DropdownMenuItem>
            </>
          )}
          {(clinic.status === 'suspended' || clinic.status === 'rejected') && (
            <DropdownMenuItem onClick={() => setOpen('activate')}>
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              {t('clinics.actions.activate')}
            </DropdownMenuItem>
          )}
          {clinic.status === 'active' && (
            <>
              <DropdownMenuItem onClick={() => setOpen('impersonate')}>
                <Eye className="h-4 w-4 text-amber-600" />
                {t('clinics.actions.login_as')}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => setOpen('extend_30')}>
                <CalendarPlus className="h-4 w-4 text-amber-600" />
                {t('clinics.actions.extend_30')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setOpen('extend_90')}>
                <CalendarPlus className="h-4 w-4 text-blue-600" />
                {t('clinics.actions.extend_90')}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => setOpen('suspend')} destructive>
                <PauseCircle className="h-4 w-4" />
                {t('clinics.actions.suspend')}
              </DropdownMenuItem>
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      {open === 'approve' && <ApproveDialog clinic={clinic} onClose={() => setOpen(null)} />}
      {open === 'reject' && <RejectDialog clinic={clinic} onClose={() => setOpen(null)} />}
      {open === 'activate' && <ActivateDialog clinic={clinic} onClose={() => setOpen(null)} />}
      {open === 'suspend' && <SuspendDialog clinic={clinic} onClose={() => setOpen(null)} />}
      {open === 'extend_30' && <ExtendDialog clinic={clinic} days={30} onClose={() => setOpen(null)} />}
      {open === 'extend_90' && <ExtendDialog clinic={clinic} days={90} onClose={() => setOpen(null)} />}
      {open === 'impersonate' && <ImpersonateDialog clinic={clinic} onClose={() => setOpen(null)} />}
    </>
  );
}

interface CommonProps {
  clinic: Clinic;
  onClose: () => void;
}

function ApproveDialog({ clinic, onClose }: CommonProps) {
  const { t } = useTranslation();
  const mut = useApproveClinic();
  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('clinics.actions.approve_title')}</AlertDialogTitle>
          <AlertDialogDescription>{t('clinics.actions.approve_body', { clinic: clinic.name })}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                await mut.mutateAsync(clinic.id);
                toast.success(t('clinics.actions.approved'));
                onClose();
              } catch (err) {
                toast.error(extractMessage(err, t('errors.generic')));
              }
            }}
            className="bg-emerald-600 hover:bg-emerald-700"
          >
            {t('common.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function RejectDialog({ clinic, onClose }: CommonProps) {
  const { t } = useTranslation();
  const [reason, setReason] = useState('');
  const [err, setErr] = useState<string | null>(null);
  const mut = useRejectClinic();
  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinics.actions.reject_title')}</DialogTitle>
          <DialogDescription>{t('clinics.actions.reject_body', { clinic: clinic.name })}</DialogDescription>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="rejection_reason">{t('clinics.actions.reject_reason')}</Label>
          <Textarea
            id="rejection_reason"
            rows={3}
            value={reason}
            onChange={(e) => { setReason(e.target.value); setErr(null); }}
          />
          {err && <p className="text-xs text-[var(--color-destructive)]">{err}</p>}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button
            variant="destructive"
            disabled={mut.isPending}
            onClick={async () => {
              if (!reason.trim()) { setErr(t('errors.validation')); return; }
              try {
                await mut.mutateAsync({ id: clinic.id, reason: reason.trim() });
                toast.success(t('clinics.actions.rejected'));
                onClose();
              } catch (e) {
                const v = extractValidationErrors(e);
                if (v?.rejection_reason) setErr(v.rejection_reason[0]);
                else toast.error(extractMessage(e, t('errors.generic')));
              }
            }}
          >
            {t('clinics.actions.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ActivateDialog({ clinic, onClose }: CommonProps) {
  const { t } = useTranslation();
  const mut = useActivateClinic();
  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('clinics.actions.activate_title')}</AlertDialogTitle>
          <AlertDialogDescription>{t('clinics.actions.activate_body', { clinic: clinic.name })}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                await mut.mutateAsync(clinic.id);
                toast.success(t('clinics.actions.activated'));
                onClose();
              } catch (err) {
                toast.error(extractMessage(err, t('errors.generic')));
              }
            }}
            className="bg-emerald-600 hover:bg-emerald-700"
          >
            {t('common.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function SuspendDialog({ clinic, onClose }: CommonProps) {
  const { t } = useTranslation();
  const mut = useSuspendClinic();
  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('clinics.actions.suspend_title')}</AlertDialogTitle>
          <AlertDialogDescription>{t('clinics.actions.suspend_body', { clinic: clinic.name })}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                await mut.mutateAsync(clinic.id);
                toast.success(t('clinics.actions.suspended'));
                onClose();
              } catch (err) {
                toast.error(extractMessage(err, t('errors.generic')));
              }
            }}
          >
            {t('common.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function ExtendDialog({ clinic, days, onClose }: CommonProps & { days: 30 | 90 }) {
  const { t } = useTranslation();
  const mut = useExtendClinic();
  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t(`clinics.actions.extend_${days}_title`)}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('clinics.actions.extend_body', { clinic: clinic.name, days })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                await mut.mutateAsync({ id: clinic.id, days });
                toast.success(t('clinics.actions.extended', { days }));
                onClose();
              } catch (err) {
                toast.error(extractMessage(err, t('errors.generic')));
              }
            }}
            className="bg-amber-600 hover:bg-amber-700"
          >
            {t('common.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function ImpersonateDialog({ clinic, onClose }: CommonProps) {
  const { t } = useTranslation();
  const mut = useImpersonateClinic();
  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('clinics.actions.login_as_title', { clinic: clinic.name })}</AlertDialogTitle>
          <AlertDialogDescription>{t('clinics.actions.login_as_body')}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                const { redirect } = await mut.mutateAsync(clinic.id);
                window.location.href = redirect;
              } catch (err) {
                toast.error(extractMessage(err, t('errors.generic')));
              }
            }}
            className="bg-amber-600 hover:bg-amber-700"
          >
            {t('common.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

