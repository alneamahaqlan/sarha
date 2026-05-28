import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useCreateClinicCategoryRequest } from '@/features/clinic/category-requests/hooks';

interface Props {
  open: boolean;
  onClose: () => void;
  /**
   * Optional id of the service the request is being submitted from. When
   * present, the admin's approval auto-attaches the new specialty back to
   * this service (no manual re-edit needed). Null = generic request.
   */
  serviceId?: number | null;
}

/**
 * "Suggest a new specialty" — small dialog the clinic admin opens from
 * inside the service form when they can't find the specialty they need.
 * Submission goes to /clinic/category-requests; admin reviews it from
 * /admin/category-requests.
 */
export function RequestSpecialtyDialog({ open, onClose, serviceId }: Props) {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const create = useCreateClinicCategoryRequest();

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = name.trim();
    if (!trimmed) return;
    try {
      await create.mutateAsync({ name: trimmed, service_id: serviceId ?? null });
      toast.success(t('clinic_services.specialty_request_sent', 'تم إرسال الطلب للإدارة'));
      setName('');
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>
              {t('clinic_services.request_specialty_title', 'اقترح تخصصاً جديداً')}
            </DialogTitle>
            <DialogDescription>
              {t(
                'clinic_services.request_specialty_desc',
                'سيُراجع الطلب من الإدارة. عند الموافقة، يُضاف التخصص تلقائياً لخدمتك.',
              )}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-1.5">
            <Label htmlFor="request-specialty-name">
              {t('clinic_services.request_specialty_name', 'اسم التخصص المقترح')} *
            </Label>
            <Input
              id="request-specialty-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={t(
                'clinic_services.request_specialty_placeholder',
                'مثال: العلاج بالإبر الصينية',
              )}
              autoFocus
              maxLength={255}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={create.isPending}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={create.isPending || !name.trim()}>
              {create.isPending ? t('common.loading') : t('clinic_services.request_specialty_submit', 'إرسال الطلب')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
