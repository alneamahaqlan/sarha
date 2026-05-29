import { NavLink, Outlet } from 'react-router-dom';
import { LogOut, Languages, Sparkles, Stethoscope, Calendar, DollarSign, LayoutDashboard, FileText, ArrowUpFromLine, Building2, CreditCard, BarChart3, UserRound, Package, AlertTriangle, Images, Tags, MessageSquareWarning, LayoutPanelTop, BadgePercent } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';

import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { useAuth } from '@/app/providers/AuthProvider';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { apiClient } from '@/lib/api-client';
import { queryClient } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { NotificationBell } from '@/features/notifications/NotificationBell';
import { ImpersonationBanner } from '@/features/impersonation/ImpersonationBanner';
import { AiChatWidget } from '@/features/ai-chat/AiChatWidget';
import { useClinicNavBadges, type ClinicNavBadges } from '@/features/nav-badges/hooks';
import { MobileNav } from './MobileNav';
import { Logo } from '@/components/ui/Logo';

const clinicNav = [
  { to: '/clinic/dashboard', label: 'clinic_nav.dashboard', icon: LayoutDashboard },
  { to: '/clinic/stats', label: 'clinic_nav.stats', icon: BarChart3 },
  {
    group: 'clinic_nav.group.my_services',
    items: [
      { to: '/clinic/sub-clinics', label: 'clinic_nav.sub_clinics', icon: Stethoscope },
      { to: '/clinic/doctors', label: 'clinic_nav.doctors', icon: UserRound },
      { to: '/clinic/services', label: 'clinic_nav.services', icon: Sparkles },
      { to: '/clinic/offers', label: 'clinic_nav.offers', icon: BadgePercent, badge: 'offer_expiring' as keyof ClinicNavBadges },
      { to: '/clinic/packages', label: 'clinic_nav.packages', icon: Package },
      { to: '/clinic/before-after', label: 'clinic_nav.before_after', icon: Images },
      { to: '/clinic/category-requests', label: 'clinic_nav.category_requests', icon: Tags },
      { to: '/clinic/import-services', label: 'clinic_nav.import_services', icon: ArrowUpFromLine },
    ],
  },
  {
    group: 'clinic_nav.group.bookings',
    items: [
      { to: '/clinic/bookings', label: 'clinic_nav.bookings', icon: Calendar },
      { to: '/clinic/price-quotes', label: 'clinic_nav.price_quotes', icon: DollarSign, badge: 'price_quotes' as keyof ClinicNavBadges },
      { to: '/clinic/complaints', label: 'clinic_nav.complaints', icon: AlertTriangle, badge: 'complaints' as keyof ClinicNavBadges },
      { to: '/clinic/reports', label: 'clinic_nav.reports', icon: MessageSquareWarning },
    ],
  },
  {
    group: 'clinic_nav.group.articles',
    items: [
      { to: '/clinic/articles', label: 'clinic_nav.articles', icon: FileText },
    ],
  },
  {
    group: 'clinic_nav.group.settings',
    items: [
      { to: '/clinic/page-builder', label: 'clinic_nav.page_builder', icon: LayoutPanelTop },
      { to: '/clinic/subscription', label: 'clinic_nav.subscription', icon: CreditCard, badge: 'subscription_expiring' as keyof ClinicNavBadges },
      { to: '/clinic/profile', label: 'clinic_nav.profile', icon: Building2 },
    ],
  },
];

export function ClinicLayout() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const { locale, setLocale } = useLocale();
  const { data: badges } = useClinicNavBadges();

  const logout = useMutation({
    mutationFn: () => apiClient.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear();
      window.location.href = '/app/login';
    },
  });

  return (
    <div className="flex min-h-screen flex-col bg-[var(--color-background)]">
      <ImpersonationBanner />
      <div className="flex flex-1">
      <aside className="hidden w-64 flex-col border-e border-[var(--color-border)] bg-white md:flex">
        <div className="flex items-center gap-2.5 border-b border-[var(--color-border)] px-5 py-4">
          <Logo withText={false} size={32} />
          <span className="text-sm font-semibold leading-tight">{user?.user.name ?? t('clinic_brand')}</span>
        </div>
        <nav className="flex-1 space-y-2 overflow-y-auto p-2">
          {clinicNav.map((entry, idx) => {
            if ('group' in entry) {
              return (
                <div key={`g-${idx}`} className="space-y-1">
                  <div className="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-muted-foreground)]">
                    {t(entry.group)}
                  </div>
                  {entry.items.map((item) => {
                    const Icon = item.icon;
                    const count = item.badge ? badges?.[item.badge] ?? 0 : 0;
                    return (
                      <NavLink
                        key={item.to}
                        to={item.to}
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
                        <span className="flex-1">{t(item.label)}</span>
                        {count > 0 && (
                          <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[var(--color-destructive)] px-1.5 text-xs font-medium text-white">
                            {count > 99 ? '99+' : count}
                          </span>
                        )}
                      </NavLink>
                    );
                  })}
                </div>
              );
            }
            const Icon = entry.icon;
            return (
              <NavLink
                key={entry.to}
                to={entry.to}
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
                <span className="flex-1">{t(entry.label)}</span>
              </NavLink>
            );
          })}
        </nav>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex h-14 items-center justify-between border-b border-[var(--color-border)] bg-white px-4">
          <div className="flex items-center gap-2 md:hidden">
            <MobileNav items={clinicNav} title={user?.user.name ?? t('clinic_brand')} badges={badges} />
            <span className="text-sm font-medium">{user?.user.name}</span>
          </div>
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
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <button
                  type="button"
                  disabled={logout.isPending}
                  className="inline-flex h-8 items-center gap-1 rounded-md px-3 text-xs font-medium text-[var(--color-destructive)] hover:bg-red-50"
                >
                  <LogOut className="h-3 w-3" />
                  {t('common.logout')}
                </button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>{t('common.logout_confirm_title')}</AlertDialogTitle>
                  <AlertDialogDescription>{t('common.logout_confirm_body')}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
                  <AlertDialogAction onClick={() => logout.mutate()} disabled={logout.isPending}>
                    {t('common.logout')}
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </div>
        </header>
        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
      </div>
      <AiChatWidget />
    </div>
  );
}
