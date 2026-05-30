import { Badge, type BadgeProps } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { ClinicRole, RoleColorToken } from '../types';

/**
 * Compact pill that renders a clinic role with the brand color from
 * the backend's enum. Used in:
 *   - clinic-side header (next to the user's name)
 *   - team table row "role" column
 *   - activity log entries (next to the actor name)
 *
 * Defaults: tone is read from `color`, label is the i18n string for
 * the role. Pass `inactive` to flip the styling for soft-removed
 * members per spec ("غير نشط" alongside the original role).
 */
const VARIANT: Record<RoleColorToken, NonNullable<BadgeProps['variant']>> = {
  gold: 'gold',
  info: 'info',
  muted: 'muted',
};

interface Props {
  role: ClinicRole;
  color?: RoleColorToken | null;
  inactive?: boolean;
  className?: string;
}

export function RoleBadge({ role, color, inactive, className }: Props) {
  const { t } = useTranslation();
  const token = color ?? defaultColorFor(role);
  return (
    <Badge variant={inactive ? 'muted' : VARIANT[token]} className={className}>
      {t(`clinic_team.role.${role}`, { defaultValue: role })}
      {inactive && (
        <span className="ms-1 text-[10px] opacity-70">
          · {t('clinic_team.inactive', { defaultValue: 'inactive' })}
        </span>
      )}
    </Badge>
  );
}

function defaultColorFor(role: ClinicRole): RoleColorToken {
  switch (role) {
    case 'owner':       return 'gold';
    case 'coordinator': return 'info';
    case 'reception':   return 'muted';
  }
}
