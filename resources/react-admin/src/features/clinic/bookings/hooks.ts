import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicBookingsApi, type ClinicBookingUpdateValues, type ListParams } from './api';

const KEY = ['clinic', 'bookings'] as const;

export function useClinicBookings(params: ListParams = {}) {
  return useQuery({ queryKey: [...KEY, 'list', params], queryFn: () => clinicBookingsApi.list(params) });
}

export function useUpdateClinicBooking(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicBookingUpdateValues) => clinicBookingsApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
