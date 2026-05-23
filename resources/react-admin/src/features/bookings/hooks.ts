import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { bookingsApi, type BookingListParams } from './api/bookings.api';
import type { BookingFormValues } from './types';

const KEY = ['admin', 'bookings'] as const;

export function useBookings(params: BookingListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => bookingsApi.list(params),
  });
}

export function useCreateBooking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: BookingFormValues) => bookingsApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateBooking(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<BookingFormValues>) => bookingsApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteBooking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => bookingsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRestoreBooking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => bookingsApi.restore(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useForceDeleteBooking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => bookingsApi.forceDelete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
