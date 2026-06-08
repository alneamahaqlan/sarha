import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { ListFilters } from '../types';

interface Props {
  filters: ListFilters;
  onChange: (patch: Partial<ListFilters>) => void;
  onClear: () => void;
}

export function CustomersFilters({ filters, onChange, onClear }: Props) {
  const { t } = useTranslation();
  const active = Object.entries(filters).some(([k, v]) => k !== 'page' && k !== 'per_page' && v != null && v !== '' && v !== false);

  return (
    <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-3">
      <div className="relative flex-1">
        <Search className="absolute top-1/2 h-4 w-4 -translate-y-1/2 start-2.5 text-[var(--color-muted-foreground)]" />
        <Input
          value={filters.search ?? ''}
          onChange={(e) => onChange({ search: e.target.value || undefined, page: 1 })}
          placeholder={t('clinic_customers.filters.search_placeholder')}
          className="ps-8"
        />
      </div>

      <Select
        value={filters.segment ?? ''}
        onChange={(e) => onChange({ segment: (e.target.value || undefined) as any, page: 1 })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_customers.filters.segment_any')}</option>
        <option value="vip">{t('clinic_customers.filters.segment_vip')}</option>
        <option value="repeat">{t('clinic_customers.filters.segment_repeat')}</option>
        <option value="new">{t('clinic_customers.filters.segment_new')}</option>
        <option value="has_complaint">{t('clinic_customers.filters.segment_has_complaint')}</option>
        <option value="inactive_90d">{t('clinic_customers.filters.segment_inactive')}</option>
      </Select>

      <Select
        value={filters.booking_range ?? ''}
        onChange={(e) => onChange({ booking_range: (e.target.value || undefined) as any, page: 1 })}
        className="lg:max-w-[160px]"
      >
        <option value="">{t('clinic_customers.filters.booking_range_any')}</option>
        <option value="1">{t('clinic_customers.filters.booking_range_1')}</option>
        <option value="2-4">{t('clinic_customers.filters.booking_range_2_4')}</option>
        <option value="5-9">{t('clinic_customers.filters.booking_range_5_9')}</option>
        <option value="10+">{t('clinic_customers.filters.booking_range_10_plus')}</option>
      </Select>

      <Select
        value={filters.last_interaction ?? ''}
        onChange={(e) => onChange({ last_interaction: (e.target.value || undefined) as any, page: 1 })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_customers.filters.last_any')}</option>
        <option value="30d">{t('clinic_customers.filters.last_30d')}</option>
        <option value="90d">{t('clinic_customers.filters.last_90d')}</option>
        <option value="180d_plus">{t('clinic_customers.filters.last_180d_plus')}</option>
      </Select>

      <Select
        value={filters.priority != null ? String(filters.priority) : ''}
        onChange={(e) => onChange({ priority: e.target.value === '' ? undefined : Number(e.target.value), page: 1 })}
        className="lg:max-w-[180px]"
      >
        <option value="">{t('clinic_customers.priority.filter_any')}</option>
        <option value="3">{t('clinic_customers.priority.level_3')}</option>
        <option value="2">{t('clinic_customers.priority.level_2')}</option>
        <option value="1">{t('clinic_customers.priority.level_1')}</option>
        <option value="0">{t('clinic_customers.priority.level_0')}</option>
      </Select>

      <label className="flex items-center gap-2 text-sm">
        <Switch
          checked={!!filters.has_notes}
          onCheckedChange={(v) => onChange({ has_notes: v, page: 1 })}
        />
        <span>{t('clinic_customers.filters.has_notes')}</span>
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
