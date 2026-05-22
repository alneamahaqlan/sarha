import { useTranslation } from '@/app/providers/LocaleProvider';

export function DashboardPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('nav.dashboard')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">
          PoC scaffold. Resource conversion proceeds resource-by-resource starting with Cities.
        </p>
      </div>

      <div className="rounded-lg border border-dashed border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-muted-foreground)]">
        Widgets and Recharts panels will land in Phase G. For now use the sidebar to open Cities.
      </div>
    </div>
  );
}
