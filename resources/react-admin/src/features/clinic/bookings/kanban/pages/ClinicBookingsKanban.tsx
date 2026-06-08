import { lazy, Suspense, useState } from 'react';
import { LayoutGrid, Table as TableIcon, Calendar as CalendarIcon, Plus, Download, SlidersHorizontal, Lightbulb } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { KanbanBoard } from '../components/KanbanBoard';
import { StatsBar } from '../components/StatsBar';
import { KanbanFiltersBar } from '../components/KanbanFilters';
import { BookingTableView } from '../components/BookingTableView';
import { BookingCalendarView } from '../components/BookingCalendarView';
import { CreateBookingDialog } from '../components/CreateBookingDialog';
import { StagesManagerDialog } from '../components/StagesManagerDialog';
import { SuggestionSettingsDialog } from '../components/SuggestionSettingsDialog';
import { ExportDialog } from '../components/ExportDialog';
import { useBookingStages } from '../hooks';
import type { KanbanCard, KanbanFilters } from '../types';

const BookingDetailSheet = lazy(() =>
  import('../components/BookingDetailSheet').then((m) => ({ default: m.BookingDetailSheet }))
);

type View = 'kanban' | 'table' | 'calendar';

export function ClinicBookingsKanban() {
  const { t } = useTranslation();
  const [view, setView] = useState<View>('kanban');
  const [filters, setFilters] = useState<KanbanFilters>({});
  const [openCard, setOpenCard] = useState<KanbanCard | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [stagesOpen, setStagesOpen] = useState(false);
  const [suggestionsOpen, setSuggestionsOpen] = useState(false);
  const [exportOpen, setExportOpen] = useState(false);
  const { data: stages } = useBookingStages();

  const patchFilters = (p: Partial<KanbanFilters>) => setFilters((prev) => ({ ...prev, ...p }));
  const clearFilters = () => setFilters({});

  return (
    <div className="space-y-4">
      <header className="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
          <h1 className="text-xl font-semibold">{t('clinic_bookings_kanban.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button onClick={() => setCreateOpen(true)} className="gap-1.5">
            <Plus className="h-4 w-4" />
            <span>{t('clinic_bookings_kanban.create.cta')}</span>
          </Button>
          <Button variant="outline" onClick={() => setExportOpen(true)} className="gap-1.5">
            <Download className="h-4 w-4" />
            <span className="hidden sm:inline">{t('clinic_bookings_kanban.export.cta')}</span>
          </Button>
          <Button variant="outline" onClick={() => setStagesOpen(true)} className="gap-1.5">
            <SlidersHorizontal className="h-4 w-4" />
            <span className="hidden sm:inline">{t('clinic_bookings_kanban.stages.cta')}</span>
          </Button>
          <Button variant="outline" onClick={() => setSuggestionsOpen(true)} className="gap-1.5">
            <Lightbulb className="h-4 w-4" />
            <span className="hidden sm:inline">{t('clinic_bookings_kanban.suggestion_settings.cta')}</span>
          </Button>
          <div className="flex items-center gap-1 rounded-md border border-[var(--color-border)] p-0.5">
            <Button variant={view === 'kanban' ? 'default' : 'ghost'} size="sm" onClick={() => setView('kanban')} className="gap-1">
              <LayoutGrid className="h-4 w-4" />
              <span className="hidden sm:inline">{t('clinic_bookings_kanban.view.kanban')}</span>
            </Button>
            <Button variant={view === 'calendar' ? 'default' : 'ghost'} size="sm" onClick={() => setView('calendar')} className="gap-1">
              <CalendarIcon className="h-4 w-4" />
              <span className="hidden sm:inline">{t('clinic_bookings_kanban.view.calendar')}</span>
            </Button>
            <Button variant={view === 'table' ? 'default' : 'ghost'} size="sm" onClick={() => setView('table')} className="gap-1">
              <TableIcon className="h-4 w-4" />
              <span className="hidden sm:inline">{t('clinic_bookings_kanban.view.table')}</span>
            </Button>
          </div>
        </div>
      </header>

      <StatsBar onFilter={patchFilters} />

      <KanbanFiltersBar filters={filters} onChange={patchFilters} onClear={clearFilters} />

      {view === 'kanban' && <KanbanBoard filters={filters} onOpenCard={setOpenCard} stages={stages ?? []} />}
      {view === 'table' && <BookingTableView filters={filters} onOpenCard={setOpenCard} />}
      {view === 'calendar' && <BookingCalendarView filters={filters} onOpenCard={setOpenCard} />}

      <Suspense fallback={null}>
        {openCard && (
          <BookingDetailSheet
            bookingId={openCard.id}
            customerPhone={openCard.customer_phone}
            onClose={() => setOpenCard(null)}
          />
        )}
      </Suspense>

      {createOpen && <CreateBookingDialog open onClose={() => setCreateOpen(false)} />}
      {stagesOpen && <StagesManagerDialog onClose={() => setStagesOpen(false)} />}
      {suggestionsOpen && <SuggestionSettingsDialog onClose={() => setSuggestionsOpen(false)} />}
      {exportOpen && <ExportDialog filters={filters} onClose={() => setExportOpen(false)} />}
    </div>
  );
}
