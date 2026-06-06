import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { customersApi } from './api';
import type { ListFilters } from './types';

const KEY = ['clinic', 'customers'] as const;

export function useCustomers(filters: ListFilters = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', filters],
    queryFn: () => customersApi.list(filters),
    staleTime: 15_000,
  });
}

export function useCustomersStats() {
  return useQuery({
    queryKey: [...KEY, 'stats'],
    queryFn: () => customersApi.stats(),
    staleTime: 30_000,
  });
}

export function useCustomer(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'show', id],
    queryFn: () => customersApi.show(id!),
  });
}

export function useUpdateCustomer(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { name?: string; email?: string | null; marketing_opt_out?: boolean }) => customersApi.update(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
    },
  });
}

export function useCustomerBookings(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'bookings', id],
    queryFn: () => customersApi.bookings(id!),
  });
}

export function useCustomerComplaints(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'complaints', id],
    queryFn: () => customersApi.complaints(id!),
  });
}

export function useCustomerTimeline(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'timeline', id],
    queryFn: () => customersApi.timeline(id!),
    staleTime: 30_000,
  });
}

export function useCustomerNotes(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'notes', id],
    queryFn: () => customersApi.notes(id!),
  });
}

export function useCreateCustomerNote(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { body: string; is_pinned?: boolean }) =>
      customersApi.createNote(id, input.body, input.is_pinned ?? false),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEY, 'notes', id] });
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings', 'kanban', 'customer'] });
    },
  });
}

export function useUpdateCustomerNote(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { noteId: number; body?: string; is_pinned?: boolean }) =>
      customersApi.updateNote(id, input.noteId, { body: input.body, is_pinned: input.is_pinned }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEY, 'notes', id] });
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings', 'kanban', 'customer'] });
    },
  });
}

export function useDeleteCustomerNote(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (noteId: number) => customersApi.deleteNote(id, noteId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEY, 'notes', id] });
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings', 'kanban', 'customer'] });
    },
  });
}
