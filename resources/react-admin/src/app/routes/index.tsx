import { Navigate, Route, Routes } from 'react-router-dom';

import { useAuth } from '@/app/providers/AuthProvider';
import { AdminLayout } from '@/app/layouts/AdminLayout';
import { LoginPage } from '@/features/auth/pages/LoginPage';
import { DashboardPage } from '@/features/dashboard/pages/DashboardPage';
import { CitiesIndex } from '@/features/cities/pages/CitiesIndex';
import { CategoriesIndex } from '@/features/categories/pages/CategoriesIndex';
import { UsersIndex } from '@/features/users/pages/UsersIndex';
import { AdminsIndex } from '@/features/admins/pages/AdminsIndex';
import { ServicesIndex } from '@/features/services/pages/ServicesIndex';
import { BookingsIndex } from '@/features/bookings/pages/BookingsIndex';
import { ComplaintsIndex } from '@/features/complaints/pages/ComplaintsIndex';
import { SalesLeadsIndex } from '@/features/sales-leads/pages/SalesLeadsIndex';
import { ClinicsIndex } from '@/features/clinics/pages/ClinicsIndex';
import { SubscriptionsIndex } from '@/features/subscriptions/pages/SubscriptionsIndex';
import { PriceQuotesIndex } from '@/features/price-quotes/pages/PriceQuotesIndex';
import { AuditLogsIndex } from '@/features/audit-logs/pages/AuditLogsIndex';
import { SystemSettingsIndex } from '@/features/system-settings/pages/SystemSettingsIndex';
import { MassNotifyPage } from '@/features/mass-notify/pages/MassNotifyPage';
import { ClinicLayout } from '@/app/layouts/ClinicLayout';
import { ClinicDashboardPage } from '@/features/clinic/dashboard/pages/ClinicDashboardPage';
import { ClinicServicesIndex } from '@/features/clinic/services/pages/ClinicServicesIndex';
import { ClinicCategoriesIndex } from '@/features/clinic/categories/pages/ClinicCategoriesIndex';
import { ClinicBookingsIndex } from '@/features/clinic/bookings/pages/ClinicBookingsIndex';
import { ClinicQuotesIndex } from '@/features/clinic/price-quotes/pages/ClinicQuotesIndex';
import { ClinicArticlesIndex } from '@/features/clinic/articles/pages/ClinicArticlesIndex';
import { ClinicProfilePage } from '@/features/clinic/profile/pages/ClinicProfilePage';
import { ImportServicesPage } from '@/features/clinic/import-services/pages/ImportServicesPage';

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
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    );
  }

  if (user?.guard === 'admin') {
    return (
      <Routes>
        <Route path="/" element={<AdminLayout />}>
          <Route index element={<Navigate to="/admin/dashboard" replace />} />
          <Route path="admin">
            <Route index element={<Navigate to="dashboard" replace />} />
            <Route path="dashboard" element={<DashboardPage />} />
            <Route path="clinics" element={<ClinicsIndex />} />
            <Route path="bookings" element={<BookingsIndex />} />
            <Route path="complaints" element={<ComplaintsIndex />} />
            <Route path="sales-leads" element={<SalesLeadsIndex />} />
            <Route path="users" element={<UsersIndex />} />
            <Route path="services" element={<ServicesIndex />} />
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
    );
  }

  if (user?.guard === 'clinic') {
    return (
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
            <Route path="profile" element={<ClinicProfilePage />} />
          </Route>
        </Route>
        <Route path="/login" element={<Navigate to="/clinic/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/clinic/dashboard" replace />} />
      </Routes>
    );
  }

  // Customer (web) guard has no React panel — they use the public Blade site.
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
