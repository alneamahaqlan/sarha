import { toast } from 'sonner';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { useAssign, useAssignees } from '../hooks';
import type { AssigneeKind, AssigneePayload } from '../types';

interface Props {
  bookingId: number;
  current: AssigneePayload | null;
}

export function AssigneePicker({ bookingId, current }: Props) {
  const { t } = useTranslation();
  const auth = useAuth();
  const { data: opts } = useAssignees();
  const mut = useAssign(bookingId);

  // Owner sees full picker. Coordinator/Reception can only self-assign.
  const canManage = auth.can('team.manage');
  const actingType: AssigneeKind | null =
    auth.acting?.type === 'member' ? 'ClinicTeamMember'
    : auth.acting?.type === 'owner' ? 'Clinic'
    : null;
  const actingId = auth.acting?.id ?? null;

  async function onChange(value: string) {
    try {
      if (!value) {
        await mut.mutateAsync({ type: null, id: null });
      } else {
        const [type, id] = value.split(':');
        await mut.mutateAsync({ type: type as AssigneeKind, id: Number(id) });
      }
      toast.success(t('clinic_bookings_kanban.assignee.updated'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  const value = current ? `${current.type}:${current.id}` : '';

  if (!canManage) {
    const selfValue = actingType && actingId ? `${actingType}:${actingId}` : '';
    return (
      <div className="space-y-1.5">
        <Label>{t('clinic_bookings_kanban.assignee.label')}</Label>
        <Select value={value} onChange={(e) => onChange(e.target.value)}>
          <option value="">{t('clinic_bookings_kanban.card.unassigned')}</option>
          {selfValue && <option value={selfValue}>{t('clinic_bookings_kanban.assignee.self_option')}</option>}
        </Select>
      </div>
    );
  }

  return (
    <div className="space-y-1.5">
      <Label>{t('clinic_bookings_kanban.assignee.label')}</Label>
      <Select value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">{t('clinic_bookings_kanban.card.unassigned')}</option>
        {opts?.map((o) => {
          const roleLabel = o.role === 'owner'
            ? t('clinic_team.role.owner')
            : t(`clinic_team.role.${o.role}`);
          return (
            <option key={`${o.type}:${o.id}`} value={`${o.type}:${o.id}`}>
              {o.name} — {roleLabel}
            </option>
          );
        })}
      </Select>
    </div>
  );
}
