import { useState } from 'react';
import { toast } from 'sonner';

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
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import {
  useMarkComplaintInReview,
  useNotifyClinic,
  useRejectComplaint,
  useResolveComplaint,
} from '../hooks';
import type { Complaint } from '../types';

interface CommonProps {
  complaint: Complaint;
  onClose: () => void;
}

export function MarkInReviewDialog({ complaint, onClose }: CommonProps) {
  const { t } = useTranslation();
  const mut = useMarkComplaintInReview();

  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('complaints.actions.mark_in_review_title')}</AlertDialogTitle>
          <AlertDialogDescription>{t('complaints.actions.mark_in_review_body')}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                await mut.mutateAsync(complaint.id);
                toast.success(t('complaints.actions.mark_in_review_done'));
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

export function ResolveDialog({ complaint, onClose }: CommonProps) {
  const { t } = useTranslation();
  const [resolution, setResolution] = useState('');
  const [err, setErr] = useState<string | null>(null);
  const mut = useResolveComplaint();

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('complaints.actions.resolve_title')}</DialogTitle>
          <DialogDescription>{t('complaints.actions.resolve_body')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="resolution">{t('complaints.resolution')}</Label>
          <Textarea
            id="resolution"
            rows={4}
            value={resolution}
            onChange={(e) => {
              setResolution(e.target.value);
              setErr(null);
            }}
          />
          {err && <p className="text-xs text-[var(--color-destructive)]">{err}</p>}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button
            disabled={mut.isPending}
            onClick={async () => {
              if (!resolution.trim()) {
                setErr(t('errors.validation'));
                return;
              }
              try {
                await mut.mutateAsync({ id: complaint.id, resolution: resolution.trim() });
                toast.success(t('complaints.actions.resolve_done'));
                onClose();
              } catch (e) {
                const v = extractValidationErrors(e);
                if (v?.resolution) setErr(v.resolution[0]);
                else toast.error(extractMessage(e, t('errors.generic')));
              }
            }}
          >
            {t('complaints.actions.resolve')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function RejectDialog({ complaint, onClose }: CommonProps) {
  const { t } = useTranslation();
  const [reason, setReason] = useState('');
  const [err, setErr] = useState<string | null>(null);
  const mut = useRejectComplaint();

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('complaints.actions.reject_title')}</DialogTitle>
          <DialogDescription>{t('complaints.actions.reject_body')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="admin_notes">{t('complaints.rejection_reason')}</Label>
          <Textarea
            id="admin_notes"
            rows={3}
            value={reason}
            onChange={(e) => {
              setReason(e.target.value);
              setErr(null);
            }}
          />
          {err && <p className="text-xs text-[var(--color-destructive)]">{err}</p>}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button
            variant="destructive"
            disabled={mut.isPending}
            onClick={async () => {
              if (!reason.trim()) {
                setErr(t('errors.validation'));
                return;
              }
              try {
                await mut.mutateAsync({ id: complaint.id, admin_notes: reason.trim() });
                toast.success(t('complaints.actions.reject_done'));
                onClose();
              } catch (e) {
                const v = extractValidationErrors(e);
                if (v?.admin_notes) setErr(v.admin_notes[0]);
                else toast.error(extractMessage(e, t('errors.generic')));
              }
            }}
          >
            {t('complaints.actions.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function NotifyClinicDialog({ complaint, onClose }: CommonProps) {
  const { t } = useTranslation();
  const mut = useNotifyClinic();

  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('complaints.actions.notify_clinic_title')}</AlertDialogTitle>
          <AlertDialogDescription>{t('complaints.actions.notify_clinic_body')}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                await mut.mutateAsync(complaint.id);
                toast.success(t('complaints.actions.notify_clinic_done'));
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
