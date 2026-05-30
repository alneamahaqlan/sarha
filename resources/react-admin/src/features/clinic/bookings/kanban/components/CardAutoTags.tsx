import { Badge } from '@/components/ui/badge';
import { Crown, Repeat, Sparkles, AlertOctagon, AlertTriangle } from 'lucide-react';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { AutoTags } from '../types';

export function CardAutoTags({ tags, compact }: { tags: AutoTags; compact?: boolean }) {
  const { t } = useTranslation();
  const items: Array<{ key: string; variant: any; icon: any; label: string }> = [];

  if (tags.is_vip) items.push({ key: 'vip', variant: 'gold', icon: Crown, label: t('clinic_bookings_kanban.tag.vip') });
  if (tags.is_repeat) items.push({ key: 'repeat', variant: 'info', icon: Repeat, label: t('clinic_bookings_kanban.tag.repeat') });
  if (tags.is_new) items.push({ key: 'new', variant: 'default', icon: Sparkles, label: t('clinic_bookings_kanban.tag.new') });
  if (tags.has_open_complaint) items.push({ key: 'complaint', variant: 'danger', icon: AlertOctagon, label: t('clinic_bookings_kanban.tag.complaint') });
  if (tags.cancel_risk) items.push({ key: 'cancel_risk', variant: 'warning', icon: AlertTriangle, label: t('clinic_bookings_kanban.tag.cancel_risk') });

  if (items.length === 0) return null;
  return (
    <div className="flex flex-wrap items-center gap-1">
      {items.map(({ key, variant, icon: Icon, label }) => (
        <Badge key={key} variant={variant} className="gap-1 px-1.5">
          <Icon className="h-3 w-3" />
          {!compact && <span>{label}</span>}
        </Badge>
      ))}
    </div>
  );
}
