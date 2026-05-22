import { toast } from 'sonner';

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
import { extractMessage } from '@/lib/api-client';

import { useConvertSalesLead } from '../hooks';
import type { SalesLead, SubscriptionPlan } from '../types';

interface Props {
  lead: SalesLead;
  plan: SubscriptionPlan;
  onClose: () => void;
}

export function ConvertDialog({ lead, plan, onClose }: Props) {
  const { t } = useTranslation();
  const mut = useConvertSalesLead();

  return (
    <AlertDialog open onOpenChange={(o) => !o && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t(`sales_leads.actions.convert_to_${plan}_title`)}</AlertDialogTitle>
          <AlertDialogDescription>
            {t(`sales_leads.actions.convert_to_${plan}_body`, { clinic: lead.clinic_name })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mut.isPending}
            onClick={async () => {
              try {
                const data = await mut.mutateAsync({ id: lead.id, plan });
                toast.success(t('sales_leads.actions.converted', { clinic: data.clinic.name }));
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
