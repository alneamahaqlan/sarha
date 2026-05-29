import { Link, Outlet } from 'react-router-dom';
import { LogOut, Languages, MapPin, LayoutDashboard, Tag, Tags, Users, Shield, Sparkles, Calendar, AlertTriangle, Filter, Building2, CreditCard, DollarSign, ShieldCheck, Cog, Megaphone, FileText, BarChart3, Home, MessageSquareWarning, Bot } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';

import { useAuth } from '@/app/providers/AuthProvider';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { apiClient } from '@/lib/api-client';
import { queryClient } from '@/lib/query-client';
import { Badge } from '@/components/ui/badge';
import { NotificationBell } from '@/features/notifications/NotificationBell';
import { ImpersonationBanner } from '@/features/impersonation/ImpersonationBanner';
import { useAdminNavBadges, type AdminNavBadges } from '@/features/nav-badges/hooks';
import { MobileNav } from './MobileNav';
import { Sidebar } from './Sidebar';

const adminNav = [
  { to: '/admin/dashboard', label: 'nav.dashboard', icon: LayoutDashboard },
  { to: '/admin/analytics', label: 'nav.analytics', icon: BarChart3 },
  {
    group: 'nav.group.clinics',
    items: [
      { to: '/admin/clinics', label: 'nav.clinics', icon: Building2 },
      { to: '/admin/bookings', label: 'nav.bookings', icon: Calendar },
      { to: '/admin/complaints', label: 'nav.complaints', icon: AlertTriangle, badge: 'complaints' as keyof AdminNavBadges },
      { to: '/admin/clinic-reports', label: 'nav.clinic_reports', icon: MessageSquareWarning, badge: 'clinic_reports' as keyof AdminNavBadges },
      { to: '/admin/customer-reports', label: 'nav.customer_reports', icon: MessageSquareWarning, badge: 'customer_reports' as keyof AdminNavBadges },
      { to: '/admin/price-quotes', label: 'nav.price_quotes', icon: DollarSign, badge: 'price_quotes' as keyof AdminNavBadges },
      { to: '/admin/users', label: 'nav.users', icon: Users },
    ],
  },
  {
    group: 'nav.group.content',
    items: [
      { to: '/admin/services', label: 'nav.services', icon: Sparkles },
      { to: '/admin/articles', label: 'nav.articles', icon: FileText },
      { to: '/admin/homepage-sections', label: 'nav.homepage_sections', icon: Home },
    ],
  },
  {
    group: 'nav.group.sales',
    items: [
      { to: '/admin/sales-leads', label: 'nav.sales_leads', icon: Filter },
      { to: '/admin/subscriptions', label: 'nav.subscriptions', icon: CreditCard },
    ],
  },
  {
    group: 'nav.group.system',
    items: [
      { to: '/admin/cities', label: 'nav.cities', icon: MapPin },
      { to: '/admin/categories', label: 'nav.categories', icon: Tag },
      { to: '/admin/category-requests', label: 'nav.category_requests', icon: Tags, badge: 'category_requests' as keyof AdminNavBadges },
      { to: '/admin/admins', label: 'nav.admins', icon: Shield },
      { to: '/admin/mass-notify', label: 'nav.mass_notify', icon: Megaphone },
      { to: '/admin/ai-center', label: 'nav.ai_center', icon: Bot },
      { to: '/admin/system-settings', label: 'nav.system_settings', icon: Cog },
      { to: '/admin/audit-logs', label: 'nav.audit_logs', icon: ShieldCheck },
    ],
  },
];

export function AdminLayout() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const { locale, setLocale } = useLocale();
  const { data: badges } = useAdminNavBadges();

  const logout = useMutation({
    mutationFn: () => apiClient.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear();
      window.location.href = '/app/login';
    },
  });

  const sidebarFooter = (
    <div className="space-y-1">
      <div className="flex items-center gap-2">
        <span className="truncate font-medium text-[var(--color-foreground)]">{user?.user.name}</span>
        {user?.user.role && (
          <Badge variant="muted" className="shrink-0">
            {t(`roles.${user.user.role}`, { defaultValue: user.user.role })}
          </Badge>
        )}
      </div>
      <div className="truncate">{user?.user.email}</div>
    </div>
  );

  return (
    // overflow-x-hidden on the outermost div is the safety net: if any
    // single descendant misses a min-w-0 (a chart, a table, a long
    // word in RTL) the document-level horizontal scroll is still
    // suppressed. Standard pattern for admin shells.
    <div className="flex min-h-screen flex-col overflow-x-hidden bg-[var(--color-background)]">
      <ImpersonationBanner />
      <div className="flex min-w-0 flex-1">
        {/* Dual-mode sidebar — hidden on mobile, icon-rail on tablet,
            full on desktop. MobileNav (Sheet) covers the mobile path. */}
        <Sidebar items={adminNav} badges={badges} footer={sidebarFooter} />

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="flex h-12 sm:h-14 items-center gap-2 border-b border-[var(--color-border)] bg-white px-3 sm:px-4">
            {/* Mobile brand block: min-w-0 + truncate so a long brand
                name shrinks instead of pushing the action buttons off
                the viewport. */}
            <div className="flex min-w-0 items-center gap-2 md:hidden">
              <MobileNav items={adminNav} title={t('brand')} badges={badges} />
              <span className="text-sm font-medium truncate">{t('brand')}</span>
            </div>
            {/* Action block — shrink-0 keeps these icons readable
                while the brand truncates. gap shrinks on xs. */}
            <div className="ms-auto flex shrink-0 items-center gap-1 sm:gap-2">
              {(badges?.clinics_pending ?? 0) > 0 && (
                <Link
                  to="/admin/clinics?status=pending"
                  className="inline-flex h-8 items-center gap-1 sm:gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-1.5 sm:px-3 text-xs font-medium text-amber-800 hover:bg-amber-100"
                  aria-label={t('dashboard.pending_clinics_label')}
                >
                  <Building2 className="h-3.5 w-3.5" />
                  <span className="hidden sm:inline">{t('dashboard.pending_clinics_label')}</span>
                  <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white">
                    {(badges?.clinics_pending ?? 0) > 99 ? '99+' : badges?.clinics_pending}
                  </span>
                </Link>
              )}
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
              <button
                type="button"
                onClick={() => logout.mutate()}
                disabled={logout.isPending}
                className="inline-flex h-8 items-center gap-1 rounded-md px-2 sm:px-3 text-xs font-medium text-[var(--color-destructive)] hover:bg-red-50"
                aria-label={t('common.logout')}
              >
                <LogOut className="h-3 w-3" />
                <span className="hidden sm:inline">{t('common.logout')}</span>
              </button>
            </div>
          </header>
          <main className="flex-1 p-3 sm:p-4 lg:p-6">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  );
}
