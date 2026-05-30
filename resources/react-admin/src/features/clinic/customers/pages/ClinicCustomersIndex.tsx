import { useState } from 'react';
import { Table as TableIcon, LayoutGrid } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { CustomersStatsBar } from '../components/CustomersStatsBar';
import { CustomersFilters } from '../components/CustomersFilters';
import { CustomersTable } from '../components/CustomersTable';
import { CustomersSegments } from '../components/CustomersSegments';
import type { CustomerSegment, ListFilters } from '../types';

type View = 'table' | 'segments';

export function ClinicCustomersIndex() {
  const { t } = useTranslation();
  const [view, setView] = useState<View>('table');
  const [filters, setFilters] = useState<ListFilters>({ page: 1, per_page: 50 });

  const patch = (p: Partial<ListFilters>) => setFilters((prev) => ({ ...prev, ...p }));
  const clear = () => setFilters({ page: 1, per_page: 50 });

  const onSegmentClick = (segment: CustomerSegment) => {
    if (segment === 'all') {
      patch({ segment: undefined, page: 1 });
    } else {
      patch({ segment: segment as Exclude<CustomerSegment, 'all'>, page: 1 });
      setView('table');
    }
  };

  return (
    <div className="space-y-4">
      <header className="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
          <h1 className="text-xl font-semibold">{t('clinic_customers.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_customers.subtitle')}</p>
        </div>
        <div className="flex items-center gap-1 rounded-md border border-[var(--color-border)] p-0.5">
          <Button variant={view === 'table' ? 'default' : 'ghost'} size="sm" onClick={() => setView('table')} className="gap-1">
            <TableIcon className="h-4 w-4" />
            <span className="hidden sm:inline">{t('clinic_customers.view.table')}</span>
          </Button>
          <Button variant={view === 'segments' ? 'default' : 'ghost'} size="sm" onClick={() => setView('segments')} className="gap-1">
            <LayoutGrid className="h-4 w-4" />
            <span className="hidden sm:inline">{t('clinic_customers.view.segments')}</span>
          </Button>
        </div>
      </header>

      <CustomersStatsBar onSegmentClick={onSegmentClick} activeSegment={filters.segment as CustomerSegment | undefined} />

      {view === 'table' ? (
        <>
          <CustomersFilters filters={filters} onChange={patch} onClear={clear} />
          <CustomersTable filters={filters} onFilterChange={patch} />
        </>
      ) : (
        <CustomersSegments onPick={onSegmentClick} />
      )}
    </div>
  );
}
