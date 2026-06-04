import { Outlet, useLocation } from 'react-router-dom';

import { RouteErrorBoundary } from '@/app/components/RouteErrorBoundary';
import { LogOut, Languages, Sparkles, Stethoscope, Calendar, DollarSign, LayoutDashboard, FileText, ArrowUpFromLine, Building2, CreditCard, BarChart3, UserRound, Package, AlertTriangle, Images, Tags, MessageSquareWarning, LayoutPanelTop, BadgePercent, Users } from 'lucide-react';
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
import { RoleBadge } from '@/features/clinic/team/components/RoleBadge';
import { MobileNav } from './MobileNav';
import { Sidebar } from './Sidebar';

/**
 * Nav structure carries a `requires` ability per item. The render
 * pass filters items the active user can't access (sidebar + mobile
 * nav both) so a coordinator session never sees "My team" / "Subscription"
 * and a reception session sees only bookings/quotes/complaints/profile.
 *
 * Owner sessions match everything because their permission map is
 * built from `*` in the backend enum.
 */
type NavItem = {
  to: string;
  label: string;
  icon: any;
  badge?: keyof ClinicNavBadges;
  requires?: string;
};
type NavGroup = { group: string; items: NavItem[] };
type NavEntry = NavItem | NavGroup;

const clinicNav: NavEntry[] = [
  { to: '/clinic/dashboard', label: 'clinic_nav.dashboard', icon: LayoutDashboard },
  { to: '/clinic/stats', label: 'clinic_nav.stats', icon: BarChart3, requires: 'stats.view' },
  {
    group: 'clinic_nav.group.my_services',
    items: [
      { to: '/clinic/sub-clinics', label: 'clinic_nav.sub_clinics', icon: Stethoscope, requires: 'sub_clinics.view' },
      { to: '/clinic/doctors', label: 'clinic_nav.doctors', icon: UserRound, requires: 'doctors.view' },
      { to: '/clinic/services', label: 'clinic_nav.services', icon: Sparkles, requires: 'services.view' },
      { to: '/clinic/offers', label: 'clinic_nav.offers', icon: BadgePercent, badge: 'offer_expiring' as keyof ClinicNavBadges, requires: 'offers.view' },
      { to: '/clinic/packages', label: 'clinic_nav.packages', icon: Package, requires: 'packages.view' },
      { to: '/clinic/before-after', label: 'clinic_nav.before_after', icon: Images, requires: 'before_after.view' },
      { to: '/clinic/category-requests', label: 'clinic_nav.category_requests', icon: Tags, requires: 'category_requests.view' },
      { to: '/clinic/import-services', label: 'clinic_nav.import_services', icon: ArrowUpFromLine, requires: 'services.manage' },
    ],
  },
  {
    group: 'clinic_nav.group.bookings',
    items: [
      { to: '/clinic/bookings', label: 'clinic_nav.bookings', icon: Calendar, requires: 'bookings.view' },
      { to: '/clinic/customers', label: 'clinic_nav.customers', icon: Users, requires: 'customers.view' },
      { to: '/clinic/price-quotes', label: 'clinic_nav.price_quotes', icon: DollarSign, badge: 'price_quotes' as keyof ClinicNavBadges, requires: 'price_quotes.view' },
      { to: '/clinic/complaints', label: 'clinic_nav.complaints', icon: AlertTriangle, badge: 'complaints' as keyof ClinicNavBadges, requires: 'complaints.view' },
      { to: '/clinic/reports', label: 'clinic_nav.reports', icon: MessageSquareWarning, requires: 'complaints.view' },
    ],
  },
  {
    group: 'clinic_nav.group.articles',
    items: [
      { to: '/clinic/articles', label: 'clinic_nav.articles', icon: FileText, requires: 'articles.view' },
    ],
  },
  {
    group: 'clinic_nav.group.team',
    items: [
      { to: '/clinic/team', label: 'clinic_nav.team', icon: Users, requires: 'team.view' },
    ],
  },
  {
    group: 'clinic_nav.group.settings',
    items: [
      { to: '/clinic/page-builder', label: 'clinic_nav.page_builder', icon: LayoutPanelTop, requires: 'page_builder.view' },
      { to: '/clinic/subscription', label: 'clinic_nav.subscription', icon: CreditCard, badge: 'subscription_expiring' as keyof ClinicNavBadges, requires: 'subscription.view' },
      { to: '/clinic/profile', label: 'clinic_nav.profile', icon: Building2, requires: 'profile.view' },
    ],
  },
];

/**
 * Walks the static nav tree and drops items the active session
 * can't access. Empty groups are removed entirely so the sidebar
 * doesn't render orphaned section headers.
 */
function filterNavByPermissions(nav: NavEntry[], can: (perm: string) => boolean): NavEntry[] {
  const allowed = (perm?: string) => !perm || can(perm);
  return nav
    .map((entry) => {
      if ('items' in entry) {
        const items = entry.items.filter((i) => allowed(i.requires));
        return items.length ? { ...entry, items } : null;
      }
      return allowed(entry.requires) ? entry : null;
    })
    .filter((x): x is NavEntry => x !== null);
}

export function ClinicLayout() {
  const { user, can, acting } = useAuth();
  const location = useLocation();
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

  // Permission-aware nav — drops items the active role can't reach.
  const visibleNav = filterNavByPermissions(clinicNav, can);

  // Display name in the header is the acting actor (team member when
  // present, clinic name for owner sessions). The clinic name is
  // always shown as the section brand below.
  const headerName = acting?.name ?? user?.user.name ?? t('clinic_brand');
  const showRoleBadge = acting !== null;

  // Clinic sidebar footer shows the clinic name + role; same shape as
  // AdminLayout so the dual-mode Sidebar can render it identically.
  const sidebarFooter = (
    <div className="space-y-1">
      <div className="truncate font-medium text-[var(--color-foreground)]">
        {user?.user.name ?? t('clinic_brand')}
      </div>
      {acting && (
        <div className="flex items-center gap-1.5 text-xs">
          <span className="truncate">{acting.name}</span>
          <RoleBadge role={acting.role} />
        </div>
      )}
    </div>
  );

  return (
    <div className="flex min-h-screen flex-col overflow-x-hidden bg-[var(--color-background)]">
      <ImpersonationBanner />
      <div className="flex min-w-0 flex-1">
        <Sidebar items={visibleNav} badges={badges} footer={sidebarFooter} />

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="flex h-12 sm:h-14 items-center gap-2 border-b border-[var(--color-border)] bg-white px-3 sm:px-4">
            <div className="flex min-w-0 items-center gap-2 md:hidden">
              <MobileNav items={visibleNav} title={user?.user.name ?? t('clinic_brand')} badges={badges} />
              <span className="text-sm font-medium truncate">{headerName}</span>
              {showRoleBadge && <RoleBadge role={acting!.role} className="shrink-0" />}
            </div>
            {/* Desktop: name + role badge on the leading side. */}
            <div className="hidden md:flex items-center gap-2 min-w-0">
              <span className="text-sm font-medium truncate">{headerName}</span>
              {showRoleBadge && <RoleBadge role={acting!.role} className="shrink-0" />}
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
            <RouteErrorBoundary resetKey={location.pathname}>
              <Outlet />
            </RouteErrorBoundary>
          </main>
        </div>
      </div>
      <AiChatWidget />
    </div>
  );
}
