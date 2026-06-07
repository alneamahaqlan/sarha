import { lazy, Suspense, type ReactNode, useEffect } from 'react';
import { Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';

import { useAuth } from '@/app/providers/AuthProvider';
import { AdminLayout } from '@/app/layouts/AdminLayout';
import { ClinicLayout } from '@/app/layouts/ClinicLayout';
import { apiClient } from '@/lib/api-client';
import { queryClient } from '@/lib/query-client';

/**
 * Route-level guard for clinic-side pages that require a specific
 * ability. If the active user lacks it, redirect to the dashboard
 * with a toast instead of rendering the page. Mirrors the backend's
 * `clinic.role:` middleware so hand-typing a forbidden URL behaves
 * the same as hitting it from a hidden nav item.
 */
function RoleGuard({ ability, children }: { ability: string; children: ReactNode }) {
  const { can } = useAuth();
  const navigate = useNavigate();
  const allowed = can(ability);
  useEffect(() => {
    if (!allowed) {
      toast.error('ليس لديك صلاحية للوصول لهذه الصفحة.');
      navigate('/app/clinic/dashboard', { replace: true });
    }
  }, [allowed, navigate]);
  if (!allowed) return null;
  return <>{children}</>;
}

// All resource pages are lazy-loaded so the initial bundle stays small.
// Layouts + auth provider stay eager (they're needed for the shell).
const LoginPage           = lazy(() => import('@/features/auth/pages/LoginPage').then(m => ({ default: m.LoginPage })));
const DashboardPage       = lazy(() => import('@/features/dashboard/pages/DashboardPage').then(m => ({ default: m.DashboardPage })));
const CitiesIndex         = lazy(() => import('@/features/cities/pages/CitiesIndex').then(m => ({ default: m.CitiesIndex })));
const CategoriesIndex        = lazy(() => import('@/features/categories/pages/CategoriesIndex').then(m => ({ default: m.CategoriesIndex })));
const HomepageSectionsIndex  = lazy(() => import('@/features/homepage-sections/pages/HomepageSectionsIndex').then(m => ({ default: m.HomepageSectionsIndex })));
const StaticPagesIndex       = lazy(() => import('@/features/static-pages/pages/StaticPagesIndex').then(m => ({ default: m.StaticPagesIndex })));
const NavigationLinksIndex   = lazy(() => import('@/features/navigation-links/pages/NavigationLinksIndex').then(m => ({ default: m.NavigationLinksIndex })));
const AiCenterPage           = lazy(() => import('@/features/ai-center/pages/AiCenterPage').then(m => ({ default: m.AiCenterPage })));
const UsersIndex          = lazy(() => import('@/features/users/pages/UsersIndex').then(m => ({ default: m.UsersIndex })));
const UserProfilePage     = lazy(() => import('@/features/users/pages/UserProfilePage').then(m => ({ default: m.UserProfilePage })));
const AdminsIndex         = lazy(() => import('@/features/admins/pages/AdminsIndex').then(m => ({ default: m.AdminsIndex })));
const ServicesIndex       = lazy(() => import('@/features/services/pages/ServicesIndex').then(m => ({ default: m.ServicesIndex })));
const BookingsIndex       = lazy(() => import('@/features/bookings/pages/BookingsIndex').then(m => ({ default: m.BookingsIndex })));
const ComplaintsIndex     = lazy(() => import('@/features/complaints/pages/ComplaintsIndex').then(m => ({ default: m.ComplaintsIndex })));
const SalesLeadsIndex     = lazy(() => import('@/features/sales-leads/pages/SalesLeadsIndex').then(m => ({ default: m.SalesLeadsIndex })));
const ClinicsIndex        = lazy(() => import('@/features/clinics/pages/ClinicsIndex').then(m => ({ default: m.ClinicsIndex })));
const ClinicStatsPage     = lazy(() => import('@/features/clinics/pages/ClinicStatsPage').then(m => ({ default: m.ClinicStatsPage })));
const ClinicStructurePage = lazy(() => import('@/features/clinics/pages/ClinicStructurePage').then(m => ({ default: m.ClinicStructurePage })));
const AnalyticsPage       = lazy(() => import('@/features/analytics/pages/AnalyticsPage').then(m => ({ default: m.AnalyticsPage })));
const SubscriptionsIndex  = lazy(() => import('@/features/subscriptions/pages/SubscriptionsIndex').then(m => ({ default: m.SubscriptionsIndex })));
const PackagesIndex       = lazy(() => import('@/features/subscription-packages/pages/PackagesIndex').then(m => ({ default: m.PackagesIndex })));
const PriceQuotesIndex    = lazy(() => import('@/features/price-quotes/pages/PriceQuotesIndex').then(m => ({ default: m.PriceQuotesIndex })));
const AuditLogsIndex      = lazy(() => import('@/features/audit-logs/pages/AuditLogsIndex').then(m => ({ default: m.AuditLogsIndex })));
const SystemSettingsIndex = lazy(() => import('@/features/system-settings/pages/SystemSettingsIndex').then(m => ({ default: m.SystemSettingsIndex })));
const WhatsAppSendersIndex = lazy(() => import('@/features/whatsapp-senders/pages/WhatsAppSendersIndex').then(m => ({ default: m.WhatsAppSendersIndex })));
const MassNotifyPage      = lazy(() => import('@/features/mass-notify/pages/MassNotifyPage').then(m => ({ default: m.MassNotifyPage })));
const ArticlesIndex       = lazy(() => import('@/features/articles/pages/ArticlesIndex').then(m => ({ default: m.ArticlesIndex })));
const CategoryRequestsIndex = lazy(() => import('@/features/category-requests/pages/CategoryRequestsIndex').then(m => ({ default: m.CategoryRequestsIndex })));
const CatalogServicesIndex = lazy(() => import('@/features/catalog-services/pages/CatalogServicesIndex').then(m => ({ default: m.CatalogServicesIndex })));
const TrackingPage        = lazy(() => import('@/features/tracking/pages/TrackingPage').then(m => ({ default: m.TrackingPage })));

const ClinicDashboardPage = lazy(() => import('@/features/clinic/dashboard/pages/ClinicDashboardPage').then(m => ({ default: m.ClinicDashboardPage })));
const ClinicMyStatsPage   = lazy(() => import('@/features/clinic/stats/pages/ClinicMyStatsPage').then(m => ({ default: m.ClinicMyStatsPage })));
const ClinicServicesIndex = lazy(() => import('@/features/clinic/services/pages/ClinicServicesIndex').then(m => ({ default: m.ClinicServicesIndex })));
const ClinicSubClinicsIndex = lazy(() => import('@/features/clinic/sub-clinics/pages/ClinicSubClinicsIndex').then(m => ({ default: m.ClinicSubClinicsIndex })));
const ClinicDoctorsIndex  = lazy(() => import('@/features/clinic/doctors/pages/ClinicDoctorsIndex').then(m => ({ default: m.ClinicDoctorsIndex })));
const ClinicPackagesIndex = lazy(() => import('@/features/clinic/packages/pages/ClinicPackagesIndex').then(m => ({ default: m.ClinicPackagesIndex })));
const ClinicComplaintsIndex = lazy(() => import('@/features/clinic/complaints/pages/ClinicComplaintsIndex').then(m => ({ default: m.ClinicComplaintsIndex })));
const ClinicReportsIndex = lazy(() => import('@/features/clinic/reports/pages/ClinicReportsIndex').then(m => ({ default: m.ClinicReportsIndex })));
const AdminClinicReportsIndex = lazy(() => import('@/features/clinic-reports/pages/AdminClinicReportsIndex').then(m => ({ default: m.AdminClinicReportsIndex })));
const AdminCustomerReportsIndex = lazy(() => import('@/features/customer-reports/pages/AdminCustomerReportsIndex').then(m => ({ default: m.AdminCustomerReportsIndex })));
const ClinicBeforeAfterIndex = lazy(() => import('@/features/clinic/before-after/pages/ClinicBeforeAfterIndex').then(m => ({ default: m.ClinicBeforeAfterIndex })));
const ClinicCategoryRequestsIndex = lazy(() => import('@/features/clinic/category-requests/pages/ClinicCategoryRequestsIndex').then(m => ({ default: m.ClinicCategoryRequestsIndex })));
const ClinicBookingsKanban = lazy(() => import('@/features/clinic/bookings/kanban/pages/ClinicBookingsKanban').then(m => ({ default: m.ClinicBookingsKanban })));
const ClinicQuotesIndex   = lazy(() => import('@/features/clinic/price-quotes/pages/ClinicQuotesIndex').then(m => ({ default: m.ClinicQuotesIndex })));
const ClinicArticlesIndex = lazy(() => import('@/features/clinic/articles/pages/ClinicArticlesIndex').then(m => ({ default: m.ClinicArticlesIndex })));
const ClinicProfilePage   = lazy(() => import('@/features/clinic/profile/pages/ClinicProfilePage').then(m => ({ default: m.ClinicProfilePage })));
const ClinicSubscriptionPage = lazy(() => import('@/features/clinic/subscription/pages/ClinicSubscriptionPage').then(m => ({ default: m.ClinicSubscriptionPage })));
const ImportServicesPage  = lazy(() => import('@/features/clinic/import-services/pages/ImportServicesPage').then(m => ({ default: m.ImportServicesPage })));
const ClinicPageBuilderIndex = lazy(() => import('@/features/clinic/page-builder/pages/ClinicPageBuilderIndex').then(m => ({ default: m.ClinicPageBuilderIndex })));
const ClinicOffersIndex = lazy(() => import('@/features/clinic/offers/pages/ClinicOffersIndex').then(m => ({ default: m.ClinicOffersIndex })));
const ClinicTeamIndex = lazy(() => import('@/features/clinic/team/pages/ClinicTeamIndex').then(m => ({ default: m.ClinicTeamIndex })));
const ClinicTeamActivityPage = lazy(() => import('@/features/clinic/team/pages/ClinicTeamActivityPage').then(m => ({ default: m.ClinicTeamActivityPage })));
const ClinicCustomersIndex = lazy(() => import('@/features/clinic/customers/pages/ClinicCustomersIndex').then(m => ({ default: m.ClinicCustomersIndex })));
const ClinicCustomerProfile = lazy(() => import('@/features/clinic/customers/pages/ClinicCustomerProfile').then(m => ({ default: m.ClinicCustomerProfile })));
const ClinicRemindersIndex = lazy(() => import('@/features/clinic/reminders/pages/ClinicRemindersIndex').then(m => ({ default: m.ClinicRemindersIndex })));
const ClinicCampaignsIndex = lazy(() => import('@/features/clinic/campaigns/pages/ClinicCampaignsIndex').then(m => ({ default: m.ClinicCampaignsIndex })));
const ClinicCampaignRunner = lazy(() => import('@/features/clinic/campaigns/pages/ClinicCampaignRunner').then(m => ({ default: m.ClinicCampaignRunner })));
const ClinicTrackingPage = lazy(() => import('@/features/clinic/tracking/pages/ClinicTrackingPage').then(m => ({ default: m.ClinicTrackingPage })));

function PageFallback() {
  return (
    <div className="flex h-full min-h-[24rem] items-center justify-center text-sm text-[var(--color-muted-foreground)]">
      <div className="h-6 w-6 animate-spin rounded-full border-2 border-[var(--color-primary)] border-t-transparent" />
    </div>
  );
}

export function AppRoutes() {
  const { isAuthenticated, isLoading, user } = useAuth();

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center text-sm text-[var(--color-muted-foreground)]">
        ...
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <Suspense fallback={<PageFallback />}>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </Suspense>
    );
  }

  if (user?.guard === 'admin') {
    return (
      <Suspense fallback={<PageFallback />}>
        <Routes>
          <Route path="/" element={<AdminLayout />}>
            <Route index element={<Navigate to="/admin/dashboard" replace />} />
            <Route path="admin">
              <Route index element={<Navigate to="dashboard" replace />} />
              <Route path="dashboard" element={<DashboardPage />} />
              <Route path="analytics" element={<AnalyticsPage />} />
              <Route path="clinics" element={<ClinicsIndex />} />
              <Route path="clinics/:id/stats" element={<ClinicStatsPage />} />
              <Route path="clinics/:id/structure" element={<ClinicStructurePage />} />
              <Route path="bookings" element={<BookingsIndex />} />
              <Route path="complaints" element={<ComplaintsIndex />} />
              <Route path="sales-leads" element={<SalesLeadsIndex />} />
              <Route path="users" element={<UsersIndex />} />
              <Route path="users/:id" element={<UserProfilePage />} />
              <Route path="services" element={<ServicesIndex />} />
              <Route path="articles" element={<ArticlesIndex />} />
              <Route path="cities" element={<CitiesIndex />} />
              <Route path="categories" element={<CategoriesIndex />} />
              <Route path="homepage-sections" element={<HomepageSectionsIndex />} />
              <Route path="static-pages" element={<StaticPagesIndex />} />
              <Route path="navigation-links" element={<NavigationLinksIndex />} />
              <Route path="admins" element={<AdminsIndex />} />
              <Route path="subscriptions" element={<SubscriptionsIndex />} />
              <Route path="subscription-packages" element={<PackagesIndex />} />
              <Route path="price-quotes" element={<PriceQuotesIndex />} />
              <Route path="audit-logs" element={<AuditLogsIndex />} />
              <Route path="system-settings" element={<SystemSettingsIndex />} />
              <Route path="tracking" element={<TrackingPage />} />
              <Route path="whatsapp-senders" element={<WhatsAppSendersIndex />} />
              <Route path="ai-center" element={<AiCenterPage />} />
              <Route path="mass-notify" element={<MassNotifyPage />} />
              <Route path="category-requests" element={<CategoryRequestsIndex />} />
              <Route path="catalog-services" element={<CatalogServicesIndex />} />
              <Route path="clinic-reports" element={<AdminClinicReportsIndex />} />
              <Route path="customer-reports" element={<AdminCustomerReportsIndex />} />
            </Route>
          </Route>
          <Route path="/login" element={<Navigate to="/admin/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/admin/dashboard" replace />} />
        </Routes>
      </Suspense>
    );
  }

  if (user?.guard === 'clinic') {
    return (
      <Suspense fallback={<PageFallback />}>
        <Routes>
          <Route path="/" element={<ClinicLayout />}>
            <Route index element={<Navigate to="/clinic/dashboard" replace />} />
            <Route path="clinic">
              <Route index element={<Navigate to="dashboard" replace />} />
              <Route path="dashboard" element={<ClinicDashboardPage />} />
              <Route path="stats" element={<RoleGuard ability="stats.view"><ClinicMyStatsPage /></RoleGuard>} />
              <Route path="services" element={<RoleGuard ability="services.view"><ClinicServicesIndex /></RoleGuard>} />
              <Route path="sub-clinics" element={<RoleGuard ability="sub_clinics.view"><ClinicSubClinicsIndex /></RoleGuard>} />
              <Route path="doctors" element={<RoleGuard ability="doctors.view"><ClinicDoctorsIndex /></RoleGuard>} />
              <Route path="packages" element={<RoleGuard ability="packages.view"><ClinicPackagesIndex /></RoleGuard>} />
              <Route path="offers" element={<RoleGuard ability="offers.view"><ClinicOffersIndex /></RoleGuard>} />
              <Route path="before-after" element={<RoleGuard ability="before_after.view"><ClinicBeforeAfterIndex /></RoleGuard>} />
              <Route path="category-requests" element={<RoleGuard ability="category_requests.view"><ClinicCategoryRequestsIndex /></RoleGuard>} />
              <Route path="bookings" element={<RoleGuard ability="bookings.view"><ClinicBookingsKanban /></RoleGuard>} />
              <Route path="customers" element={<RoleGuard ability="customers.view"><ClinicCustomersIndex /></RoleGuard>} />
              <Route path="customers/:id" element={<RoleGuard ability="customers.view"><ClinicCustomerProfile /></RoleGuard>} />
              <Route path="reminders" element={<RoleGuard ability="reminders.view"><ClinicRemindersIndex /></RoleGuard>} />
              <Route path="campaigns" element={<RoleGuard ability="campaigns.view"><ClinicCampaignsIndex /></RoleGuard>} />
              <Route path="campaigns/:id" element={<RoleGuard ability="campaigns.view"><ClinicCampaignRunner /></RoleGuard>} />
              <Route path="price-quotes" element={<RoleGuard ability="price_quotes.view"><ClinicQuotesIndex /></RoleGuard>} />
              <Route path="complaints" element={<RoleGuard ability="complaints.view"><ClinicComplaintsIndex /></RoleGuard>} />
              <Route path="reports" element={<RoleGuard ability="complaints.view"><ClinicReportsIndex /></RoleGuard>} />
              <Route path="articles" element={<RoleGuard ability="articles.view"><ClinicArticlesIndex /></RoleGuard>} />
              <Route path="import-services" element={<RoleGuard ability="services.manage"><ImportServicesPage /></RoleGuard>} />
              <Route path="page-builder" element={<RoleGuard ability="page_builder.view"><ClinicPageBuilderIndex /></RoleGuard>} />
              <Route path="subscription" element={<RoleGuard ability="subscription.view"><ClinicSubscriptionPage /></RoleGuard>} />
              <Route path="profile" element={<ClinicProfilePage />} />
              <Route path="tracking" element={<RoleGuard ability="tracking.view"><ClinicTrackingPage /></RoleGuard>} />
              <Route path="team" element={<RoleGuard ability="team.view"><ClinicTeamIndex /></RoleGuard>} />
              <Route path="team-activity" element={<RoleGuard ability="team_activity.view"><ClinicTeamActivityPage /></RoleGuard>} />
            </Route>
          </Route>
          <Route path="/login" element={<Navigate to="/clinic/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/clinic/dashboard" replace />} />
        </Routes>
      </Suspense>
    );
  }

  // Authenticated, but the active session belongs to a guard with no React
  // panel (e.g. a leftover customer/web session). Offer a way back to login
  // instead of dead-ending.
  const switchAccount = async () => {
    try {
      await apiClient.post('/auth/logout');
    } catch {
      // ignore — we redirect regardless
    }
    queryClient.clear();
    window.location.href = '/app/login';
  };

  return (
    <div className="flex h-screen flex-col items-center justify-center gap-4 p-8 text-center">
      <p className="text-sm text-[var(--color-muted-foreground)]">
        هذا الحساب لا يملك لوحة تحكم. سجّل الدخول بحساب مدير أو مجمع.
        <br />
        This account has no panel. Please sign in as an admin or complex.
      </p>
      <button
        type="button"
        onClick={switchAccount}
        className="inline-flex h-9 items-center rounded-md bg-[var(--color-primary)] px-4 text-sm font-medium text-white hover:opacity-90"
      >
        تسجيل الدخول / Sign in
      </button>
    </div>
  );
}
