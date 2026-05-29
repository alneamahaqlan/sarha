import { Outlet } from 'react-router-dom';
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
import { NotificationBell } from '@/features/notifications/NotificationBell';
import { ImpersonationBanner } from '@/features/impersonation/ImpersonationBanner';
import { AiChatWidget } from '@/features/ai-chat/AiChatWidget';
import { useClinicNavBadges, type ClinicNavBadges } from '@/features/nav-badges/hooks';
import { MobileNav } from './MobileNav';
import { Sidebar } from './Sidebar';

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

  // Clinic sidebar footer shows the clinic name + role; same shape as
  // AdminLayout so the dual-mode Sidebar can render it identically.
  const sidebarFooter = (
    <div className="truncate font-medium text-[var(--color-foreground)]">
      {user?.user.name ?? t('clinic_brand')}
    </div>
  );

  return (
    <div className="flex min-h-screen flex-col overflow-x-hidden bg-[var(--color-background)]">
      <ImpersonationBanner />
      <div className="flex min-w-0 flex-1">
        <Sidebar items={clinicNav} badges={badges} footer={sidebarFooter} />

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="flex h-12 sm:h-14 items-center gap-2 border-b border-[var(--color-border)] bg-white px-3 sm:px-4">
            <div className="flex min-w-0 items-center gap-2 md:hidden">
              <MobileNav items={clinicNav} title={user?.user.name ?? t('clinic_brand')} badges={badges} />
              <span className="text-sm font-medium truncate">{user?.user.name}</span>
            </div>
            <div className="ms-auto flex shrink-0 items-center gap-1 sm:gap-2">
              <NotificationBell />
              <button
                type="button"
                onClick={() => setLocale(locale === 'ar' ? 'en' : 'ar')}
                className="inline-flex h-8 items-center gap-1 rounded-md border border-[var(--color-border)] px-2 sm:px-3 text-xs font-medium hover:bg-[var(--color-muted)]"
                aria-label={t('common.toggle_language')}
              >
                <Languages className="h-3 w-3" />
                <span className="hidden sm:inline">{locale === 'ar' ? 'English' : 'العربية'}</span>
              </button>
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <button
                    type="button"
                    disabled={logout.isPending}
                    className="inline-flex h-8 items-center gap-1 rounded-md px-2 sm:px-3 text-xs font-medium text-[var(--color-destructive)] hover:bg-red-50"
                    aria-label={t('common.logout')}
                  >
                    <LogOut className="h-3 w-3" />
                    <span className="hidden sm:inline">{t('common.logout')}</span>
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
          <main className="flex-1 p-3 sm:p-4 lg:p-6">
            <Outlet />
          </main>
        </div>
      </div>
      <AiChatWidget />
    </div>
  );
}
