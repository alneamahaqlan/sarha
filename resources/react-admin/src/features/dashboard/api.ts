import { apiClient } from '@/lib/api-client';

export interface AdminStats {
  active_clinics: number;
  pending_clinics: number;
  today_bookings: number;
  month_bookings: number;
  active_subscriptions: number;
  month_revenue: number;
  total_users: number;
}

export interface LatestBooking {
  id: number;
  reference_code: string;
  clinic: { id: number; name: string } | null;
  customer_name: string;
  customer_phone: string;
  service: { id: number; name: string } | null;
  status: string;
  created_at: string | null;
}

export interface BookingsTrendPoint {
  date: string;
  count: number;
}

export const adminDashboardApi = {
  stats: async () => {
    const res = await apiClient.get<{ data: AdminStats }>('/admin/dashboard/stats');
    return res.data.data;
  },
  latestBookings: async () => {
    const res = await apiClient.get<{ data: LatestBooking[] }>('/admin/dashboard/latest-bookings');
    return res.data.data;
  },
  bookingsTrend: async () => {
    const res = await apiClient.get<{ data: BookingsTrendPoint[] }>('/admin/dashboard/bookings-trend');
    return res.data.data;
  },
};
