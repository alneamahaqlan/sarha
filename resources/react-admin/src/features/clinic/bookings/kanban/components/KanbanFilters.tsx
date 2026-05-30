import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAssignees } from '../hooks';
import type { KanbanFilters as F } from '../types';

interface Props {
  filters: F;
  onChange: (patch: Partial<F>) => void;
  onClear: () => void;
}

export function KanbanFiltersBar({ filters, onChange, onClear }: Props) {
  const { t } = useTranslation();
  const { data: assignees } = useAssignees();
  const active = Object.values(filters).some((v) => v != null && v !== '' && v !== false);

  return (
    <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-3">
      <div className="relative flex-1">
        <Search className="absolute top-1/2 h-4 w-4 -translate-y-1/2 start-2.5 text-[var(--color-muted-foreground)]" />
        <Input
          value={filters.search ?? ''}
          onChange={(e) => onChange({ search: e.target.value || undefined })}
          placeholder={t('clinic_bookings_kanban.filters.search_placeholder')}
          className="ps-8"
        />
      </div>

      <Select
        value={filters.assignee_id ? `${filters.assignee_type}:${filters.assignee_id}` : ''}
        onChange={(e) => {
          const v = e.target.value;
          if (!v) onChange({ assignee_id: undefined, assignee_type: undefined });
          else {
            const [type, id] = v.split(':');
            onChange({ assignee_type: type as any, assignee_id: Number(id) });
          }
        }}
        className="lg:max-w-[200px]"
      >
        <option value="">{t('clinic_bookings_kanban.filters.assignee_any')}</option>
        {assignees?.map((a) => (
          <option key={`${a.type}:${a.id}`} value={`${a.type}:${a.id}`}>
            {a.name}
          </option>
        ))}
      </Select>

      <Select
        value={filters.auto_tag ?? ''}
        onChange={(e) => onChange({ auto_tag: (e.target.value || undefined) as any })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_bookings_kanban.filters.tag_any')}</option>
        <option value="urgent_confirm">{t('clinic_bookings_kanban.filters.tag_urgent')}</option>
      </Select>

      <Input
        type="date"
        value={filters.date_from ?? ''}
        onChange={(e) => onChange({ date_from: e.target.value || undefined })}
        className="lg:max-w-[150px]"
      />
      <Input
        type="date"
        value={filters.date_to ?? ''}
        onChange={(e) => onChange({ date_to: e.target.value || undefined })}
        className="lg:max-w-[150px]"
      />

      <label className="flex items-center gap-2 text-sm">
        <Switch
          checked={!!filters.mine_only}
          onCheckedChange={(v) => onChange({ mine_only: v })}
        />
        <span>{t('clinic_bookings_kanban.filters.mine_only')}</span>
      </label>

      {active && (
        <Button variant="outline" size="sm" onClick={onClear} className="gap-1">
          <X className="h-3 w-3" />
          {t('common.reset')}
        </Button>
      )}
    </div>
  );
}
