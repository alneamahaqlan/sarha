import { useQuery } from '@tanstack/react-query';
import { adminDashboardApi } from './api';

const KEY = ['admin', 'dashboard'] as const;

export function useAdminStats() {
  return useQuery({ queryKey: [...KEY, 'stats'], queryFn: () => adminDashboardApi.stats(), staleTime: 60_000 });
}

export function useLatestBookings() {
  return useQuery({ queryKey: [...KEY, 'latest-bookings'], queryFn: () => adminDashboardApi.latestBookings(), staleTime: 30_000 });
}

export function useBookingsTrend() {
  return useQuery({ queryKey: [...KEY, 'bookings-trend'], queryFn: () => adminDashboardApi.bookingsTrend(), staleTime: 5 * 60_000 });
}

export function useDashboardSections() {
  return useQuery({ queryKey: [...KEY, 'sections'], queryFn: () => adminDashboardApi.sections(), staleTime: 60_000 });
}
