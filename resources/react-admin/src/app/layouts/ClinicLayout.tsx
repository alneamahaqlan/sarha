import { NavLink, Outlet } from 'react-router-dom';
import { LogOut, Languages, Sparkles, Tag, Calendar, DollarSign, LayoutDashboard, FileText, ArrowUpFromLine, Building2 } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';

import { useAuth } from '@/app/providers/AuthProvider';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { apiClient } from '@/lib/api-client';
import { queryClient } from '@/lib/query-client';
import { cn } from '@/lib/utils';

const clinicNav = [
  { to: '/clinic/dashboard', label: 'clinic_nav.dashboard', icon: LayoutDashboard },
  { to: '/clinic/services', label: 'clinic_nav.services', icon: Sparkles },
  { to: '/clinic/categories', label: 'clinic_nav.categories', icon: Tag },
  { to: '/clinic/import-services', label: 'clinic_nav.import_services', icon: ArrowUpFromLine },
  { to: '/clinic/bookings', label: 'clinic_nav.bookings', icon: Calendar },
  { to: '/clinic/price-quotes', label: 'clinic_nav.price_quotes', icon: DollarSign },
  { to: '/clinic/articles', label: 'clinic_nav.articles', icon: FileText },
  { to: '/clinic/profile', label: 'clinic_nav.profile', icon: Building2 },
];

export function ClinicLayout() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const { locale, setLocale } = useLocale();

  const logout = useMutation({
    mutationFn: () => apiClient.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear();
      window.location.href = '/app/login';
    },
  });

  return (
    <div className="flex min-h-screen bg-[#f8fafc]">
      <aside className="hidden w-64 flex-col border-e border-[var(--color-border)] bg-white md:flex">
        <div className="border-b border-[var(--color-border)] px-5 py-4 text-lg font-semibold">
          {user?.user.name ?? t('clinic_brand')}
        </div>
        <nav className="flex-1 space-y-1 p-2">
          {clinicNav.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                  isActive
                    ? 'bg-[var(--color-primary)] text-white'
                    : 'text-[var(--color-foreground)] hover:bg-[var(--color-muted)]',
                )
              }
            >
              <Icon className="h-4 w-4" />
              {t(label)}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex h-14 items-center justify-between border-b border-[var(--color-border)] bg-white px-4">
          <div className="text-sm font-medium md:hidden">{user?.user.name}</div>
          <div className="ms-auto flex items-center gap-2">
            <button
              type="button"
              onClick={() => setLocale(locale === 'ar' ? 'en' : 'ar')}
              className="inline-flex h-8 items-center gap-1 rounded-md border border-[var(--color-border)] px-3 text-xs font-medium hover:bg-[var(--color-muted)]"
            >
              <Languages className="h-3 w-3" />
              {locale === 'ar' ? 'English' : 'العربية'}
            </button>
            <button
              type="button"
              onClick={() => logout.mutate()}
              disabled={logout.isPending}
              className="inline-flex h-8 items-center gap-1 rounded-md px-3 text-xs font-medium text-[var(--color-destructive)] hover:bg-red-50"
            >
              <LogOut className="h-3 w-3" />
              {t('common.logout')}
            </button>
          </div>
        </header>
        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
