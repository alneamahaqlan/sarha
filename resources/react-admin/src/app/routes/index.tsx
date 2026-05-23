import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import { useAuth } from '@/app/providers/AuthProvider';
import { AdminLayout } from '@/app/layouts/AdminLayout';
import { ClinicLayout } from '@/app/layouts/ClinicLayout';

// All resource pages are lazy-loaded so the initial bundle stays small.
// Layouts + auth provider stay eager (they're needed for the shell).
const LoginPage           = lazy(() => import('@/features/auth/pages/LoginPage').then(m => ({ default: m.LoginPage })));
const DashboardPage       = lazy(() => import('@/features/dashboard/pages/DashboardPage').then(m => ({ default: m.DashboardPage })));
const CitiesIndex         = lazy(() => import('@/features/cities/pages/CitiesIndex').then(m => ({ default: m.CitiesIndex })));
const CategoriesIndex     = lazy(() => import('@/features/categories/pages/CategoriesIndex').then(m => ({ default: m.CategoriesIndex })));
const UsersIndex          = lazy(() => import('@/features/users/pages/UsersIndex').then(m => ({ default: m.UsersIndex })));
const AdminsIndex         = lazy(() => import('@/features/admins/pages/AdminsIndex').then(m => ({ default: m.AdminsIndex })));
const ServicesIndex       = lazy(() => import('@/features/services/pages/ServicesIndex').then(m => ({ default: m.ServicesIndex })));
const BookingsIndex       = lazy(() => import('@/features/bookings/pages/BookingsIndex').then(m => ({ default: m.BookingsIndex })));
const ComplaintsIndex     = lazy(() => import('@/features/complaints/pages/ComplaintsIndex').then(m => ({ default: m.ComplaintsIndex })));
const SalesLeadsIndex     = lazy(() => import('@/features/sales-leads/pages/SalesLeadsIndex').then(m => ({ default: m.SalesLeadsIndex })));
const ClinicsIndex        = lazy(() => import('@/features/clinics/pages/ClinicsIndex').then(m => ({ default: m.ClinicsIndex })));
const ClinicStatsPage     = lazy(() => import('@/features/clinics/pages/ClinicStatsPage').then(m => ({ default: m.ClinicStatsPage })));
const AnalyticsPage       = lazy(() => import('@/features/analytics/pages/AnalyticsPage').then(m => ({ default: m.AnalyticsPage })));
const SubscriptionsIndex  = lazy(() => import('@/features/subscriptions/pages/SubscriptionsIndex').then(m => ({ default: m.SubscriptionsIndex })));
const PriceQuotesIndex    = lazy(() => import('@/features/price-quotes/pages/PriceQuotesIndex').then(m => ({ default: m.PriceQuotesIndex })));
const AuditLogsIndex      = lazy(() => import('@/features/audit-logs/pages/AuditLogsIndex').then(m => ({ default: m.AuditLogsIndex })));
const SystemSettingsIndex = lazy(() => import('@/features/system-settings/pages/SystemSettingsIndex').then(m => ({ default: m.SystemSettingsIndex })));
const MassNotifyPage      = lazy(() => import('@/features/mass-notify/pages/MassNotifyPage').then(m => ({ default: m.MassNotifyPage })));
const ArticlesIndex       = lazy(() => import('@/features/articles/pages/ArticlesIndex').then(m => ({ default: m.ArticlesIndex })));

const ClinicDashboardPage = lazy(() => import('@/features/clinic/dashboard/pages/ClinicDashboardPage').then(m => ({ default: m.ClinicDashboardPage })));
const ClinicServicesIndex = lazy(() => import('@/features/clinic/services/pages/ClinicServicesIndex').then(m => ({ default: m.ClinicServicesIndex })));
const ClinicCategoriesIndex = lazy(() => import('@/features/clinic/categories/pages/ClinicCategoriesIndex').then(m => ({ default: m.ClinicCategoriesIndex })));
const ClinicBookingsIndex = lazy(() => import('@/features/clinic/bookings/pages/ClinicBookingsIndex').then(m => ({ default: m.ClinicBookingsIndex })));
const ClinicQuotesIndex   = lazy(() => import('@/features/clinic/price-quotes/pages/ClinicQuotesIndex').then(m => ({ default: m.ClinicQuotesIndex })));
const ClinicArticlesIndex = lazy(() => import('@/features/clinic/articles/pages/ClinicArticlesIndex').then(m => ({ default: m.ClinicArticlesIndex })));
const ClinicProfilePage   = lazy(() => import('@/features/clinic/profile/pages/ClinicProfilePage').then(m => ({ default: m.ClinicProfilePage })));
const ClinicSubscriptionPage = lazy(() => import('@/features/clinic/subscription/pages/ClinicSubscriptionPage').then(m => ({ default: m.ClinicSubscriptionPage })));
const ImportServicesPage  = lazy(() => import('@/features/clinic/import-services/pages/ImportServicesPage').then(m => ({ default: m.ImportServicesPage })));

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
              <Route path="bookings" element={<BookingsIndex />} />
              <Route path="complaints" element={<ComplaintsIndex />} />
              <Route path="sales-leads" element={<SalesLeadsIndex />} />
              <Route path="users" element={<UsersIndex />} />
              <Route path="services" element={<ServicesIndex />} />
              <Route path="articles" element={<ArticlesIndex />} />
              <Route path="cities" element={<CitiesIndex />} />
              <Route path="categories" element={<CategoriesIndex />} />
              <Route path="admins" element={<AdminsIndex />} />
              <Route path="subscriptions" element={<SubscriptionsIndex />} />
              <Route path="price-quotes" element={<PriceQuotesIndex />} />
              <Route path="audit-logs" element={<AuditLogsIndex />} />
              <Route path="system-settings" element={<SystemSettingsIndex />} />
              <Route path="mass-notify" element={<MassNotifyPage />} />
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
              <Route path="services" element={<ClinicServicesIndex />} />
              <Route path="categories" element={<ClinicCategoriesIndex />} />
              <Route path="bookings" element={<ClinicBookingsIndex />} />
              <Route path="price-quotes" element={<ClinicQuotesIndex />} />
              <Route path="articles" element={<ClinicArticlesIndex />} />
              <Route path="import-services" element={<ImportServicesPage />} />
              <Route path="subscription" element={<ClinicSubscriptionPage />} />
              <Route path="profile" element={<ClinicProfilePage />} />
            </Route>
          </Route>
          <Route path="/login" element={<Navigate to="/clinic/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/clinic/dashboard" replace />} />
        </Routes>
      </Suspense>
    );
  }

  return (
    <Routes>
      <Route
        path="*"
        element={
          <div className="flex h-screen items-center justify-center p-8 text-center text-sm text-[var(--color-muted-foreground)]">
            No React panel for this account type.
          </div>
        }
      />
    </Routes>
  );
}
