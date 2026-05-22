import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';

export function ClinicDashboardPage() {
  const { t } = useTranslation();
  const { user } = useAuth();

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_nav.dashboard')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{user?.user.name}</p>
      </div>
      <div className="rounded-lg border border-dashed border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-muted-foreground)]">
        {t('clinic_dashboard.placeholder')}
      </div>
    </div>
  );
}
