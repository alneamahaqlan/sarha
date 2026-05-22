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
            <Route path="users" element={<UsersIndex />} />
            <Route path="services" element={<ServicesIndex />} />
            <Route path="cities" element={<CitiesIndex />} />
            <Route path="categories" element={<CategoriesIndex />} />
            <Route path="admins" element={<AdminsIndex />} />
          </Route>
        </Route>
        <Route path="/login" element={<Navigate to="/admin/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/admin/dashboard" replace />} />
      </Routes>
    );
  }

  // Clinic guard: layout wiring will follow in a later phase.
  return (
    <Routes>
      <Route
        path="*"
        element={
          <div className="flex h-screen items-center justify-center p-8 text-center text-sm text-[var(--color-muted-foreground)]">
            Clinic React panel is not yet wired. Use Filament at /clinic-dashboard.
          </div>
        }
      />
    </Routes>
  );
}
