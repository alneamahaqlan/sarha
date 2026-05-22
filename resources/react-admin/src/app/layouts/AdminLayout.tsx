import { NavLink, Outlet } from 'react-router-dom';
import { LogOut, Languages, MapPin, LayoutDashboard, Tag, Users, Shield, Sparkles, Calendar, AlertTriangle, Filter, Building2, CreditCard, DollarSign, ShieldCheck, Cog, Megaphone } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';

import { useAuth } from '@/app/providers/AuthProvider';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { apiClient } from '@/lib/api-client';
import { queryClient } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { NotificationBell } from '@/features/notifications/NotificationBell';
import { ImpersonationBanner } from '@/features/impersonation/ImpersonationBanner';

const adminNav = [
  { to: '/admin/dashboard', label: 'nav.dashboard', icon: LayoutDashboard },
  { to: '/admin/clinics', label: 'nav.clinics', icon: Building2 },
  { to: '/admin/bookings', label: 'nav.bookings', icon: Calendar },
  { to: '/admin/complaints', label: 'nav.complaints', icon: AlertTriangle },
  { to: '/admin/price-quotes', label: 'nav.price_quotes', icon: DollarSign },
  { to: '/admin/sales-leads', label: 'nav.sales_leads', icon: Filter },
  { to: '/admin/subscriptions', label: 'nav.subscriptions', icon: CreditCard },
  { to: '/admin/users', label: 'nav.users', icon: Users },
  { to: '/admin/services', label: 'nav.services', icon: Sparkles },
  { to: '/admin/cities', label: 'nav.cities', icon: MapPin },
  { to: '/admin/categories', label: 'nav.categories', icon: Tag },
  { to: '/admin/admins', label: 'nav.admins', icon: Shield },
  { to: '/admin/mass-notify', label: 'nav.mass_notify', icon: Megaphone },
  { to: '/admin/system-settings', label: 'nav.system_settings', icon: Cog },
  { to: '/admin/audit-logs', label: 'nav.audit_logs', icon: ShieldCheck },
];

export function AdminLayout() {
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
    <div className="flex min-h-screen flex-col bg-[#f8fafc]">
      <ImpersonationBanner />
      <div className="flex flex-1">
      <aside className="hidden w-64 flex-col border-e border-[var(--color-border)] bg-white md:flex">
        <div className="border-b border-[var(--color-border)] px-5 py-4 text-lg font-semibold">
          {t('brand')}
        </div>
        <nav className="flex-1 space-y-1 p-2">
          {adminNav.map(({ to, label, icon: Icon }) => (
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
        <div className="border-t border-[var(--color-border)] p-3 text-xs text-[var(--color-muted-foreground)]">
          {user?.user.name} · {user?.user.email}
        </div>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex h-14 items-center justify-between border-b border-[var(--color-border)] bg-white px-4">
          <div className="text-sm font-medium md:hidden">{t('brand')}</div>
          <div className="ms-auto flex items-center gap-2">
            <NotificationBell />
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
    </div>
  );
}
