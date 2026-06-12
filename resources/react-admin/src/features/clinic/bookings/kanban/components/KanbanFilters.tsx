import { useState } from 'react';
import { Search, X, SlidersHorizontal } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useSubClinicLookup, useClinicServices } from '@/features/clinic/services/hooks';
import { useAssignees, useTagLabels } from '../hooks';
import { ACQUISITION_SOURCES, type KanbanFilters as F } from '../types';

interface Props {
  filters: F;
  onChange: (patch: Partial<F>) => void;
  onClear: () => void;
}

export function KanbanFiltersBar({ filters, onChange, onClear }: Props) {
  const { t } = useTranslation();
  const { data: assignees } = useAssignees();
  const { data: tagLabels } = useTagLabels();
  const { data: subClinics } = useSubClinicLookup();
  const { data: services } = useClinicServices({ per_page: 100 });
  const activeCount = Object.values(filters).filter((v) => v != null && v !== '' && v !== false).length;
  const active = activeCount > 0;
  const [open, setOpen] = useState(false);

  return (
    <div className="space-y-2">
      {/* Search stays visible at all times; on mobile the filter controls are
          tucked behind a toggle so the board isn't pushed off-screen. */}
      <div className="flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute top-1/2 h-4 w-4 -translate-y-1/2 start-2.5 text-[var(--color-muted-foreground)]" />
          <Input
            value={filters.search ?? ''}
            onChange={(e) => onChange({ search: e.target.value || undefined })}
            placeholder={t('clinic_bookings_kanban.filters.search_placeholder')}
            className="ps-8"
          />
        </div>
        <Button
          type="button"
          variant="outline"
          onClick={() => setOpen((o) => !o)}
          className="shrink-0 gap-1.5 lg:hidden"
          aria-expanded={open}
        >
          <SlidersHorizontal className="h-4 w-4" />
          {t('clinic_bookings_kanban.filters.toggle')}
          {activeCount > 0 && (
            <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[var(--color-primary)] px-1 text-[11px] font-bold text-white">
              {activeCount}
            </span>
          )}
        </Button>
      </div>

      {/* Filters: collapsed on mobile (toggled above), always inline + wrapped
          on desktop. */}
      <div
        className={cn(
          'flex-col gap-2 lg:flex lg:flex-row lg:flex-wrap lg:items-center lg:gap-3',
          open ? 'flex' : 'hidden',
        )}
      >
      <Select
        value={filters.sub_clinic_id ?? ''}
        onChange={(e) => onChange({ sub_clinic_id: e.target.value ? Number(e.target.value) : undefined })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_bookings_kanban.filters.sub_clinic_any')}</option>
        {subClinics?.map((sc) => (
          <option key={sc.id} value={sc.id}>{sc.name}</option>
        ))}
      </Select>

      <Select
        value={filters.service_id ?? ''}
        onChange={(e) => onChange({ service_id: e.target.value ? Number(e.target.value) : undefined })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_bookings_kanban.filters.service_any')}</option>
        {services?.data.map((s) => (
          <option key={s.id} value={s.id}>{s.name}</option>
        ))}
      </Select>

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
        value={filters.auto_tag ? `auto:${filters.auto_tag}` : filters.custom_tag ? `custom:${filters.custom_tag}` : ''}
        onChange={(e) => {
          const v = e.target.value;
          if (!v) { onChange({ auto_tag: undefined, custom_tag: undefined }); return; }
          const sep = v.indexOf(':');
          const kind = v.slice(0, sep);
          const val = v.slice(sep + 1);
          if (kind === 'auto') onChange({ auto_tag: val as any, custom_tag: undefined });
          else onChange({ custom_tag: val, auto_tag: undefined });
        }}
        className="lg:max-w-[200px]"
      >
        <option value="">{t('clinic_bookings_kanban.filters.tag_any')}</option>
        <optgroup label={t('clinic_bookings_kanban.filters.tag_group_auto')}>
          <option value="auto:urgent_confirm">{t('clinic_bookings_kanban.filters.tag_urgent')}</option>
          <option value="auto:vip">{t('clinic_bookings_kanban.filters.tag_vip')}</option>
          <option value="auto:repeat">{t('clinic_bookings_kanban.filters.tag_repeat')}</option>
          <option value="auto:new_customer">{t('clinic_bookings_kanban.filters.tag_new_customer')}</option>
          <option value="auto:has_complaint">{t('clinic_bookings_kanban.filters.tag_has_complaint')}</option>
        </optgroup>
        {(tagLabels?.length ?? 0) > 0 && (
          <optgroup label={t('clinic_bookings_kanban.filters.tag_group_custom')}>
            {tagLabels!.map((tag) => (
              <option key={tag.label} value={`custom:${tag.label}`}>{tag.label}</option>
            ))}
          </optgroup>
        )}
      </Select>

      <Select
        value={filters.acquisition_source ?? ''}
        onChange={(e) => onChange({ acquisition_source: (e.target.value || undefined) as any })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_bookings_kanban.source.filter_any')}</option>
        {ACQUISITION_SOURCES.map((s) => (
          <option key={s} value={s}>{t(`clinic_bookings_kanban.source.opt.${s}`)}</option>
        ))}
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
    </div>
  );
}
