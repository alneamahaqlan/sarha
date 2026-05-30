import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { bookingKanbanApi } from './api';
import type { AssigneeKind, CreateBookingInput, KanbanFilters, QuickAction, TagColor, UpdateBookingInput } from './types';

const KEY = ['clinic', 'bookings', 'kanban'] as const;

export function useKanbanBoard(filters: KanbanFilters = {}, cursors?: Record<string, string | null>) {
  return useQuery({
    queryKey: [...KEY, 'board', filters, cursors],
    queryFn: () => bookingKanbanApi.board(filters, cursors),
    refetchOnWindowFocus: true,
    staleTime: 15_000,
  });
}

export function useKanbanStats() {
  return useQuery({
    queryKey: [...KEY, 'stats'],
    queryFn: () => bookingKanbanApi.stats(),
    refetchOnWindowFocus: true,
    staleTime: 30_000,
  });
}

export function useBookingDetail(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'detail', id],
    queryFn: () => bookingKanbanApi.detail(id!),
  });
}

export function useBookingActivities(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'activities', id],
    queryFn: () => bookingKanbanApi.activities(id!),
    staleTime: 5_000,
  });
}

export function useCustomerProfile(phone: string | null) {
  return useQuery({
    enabled: !!phone,
    queryKey: [...KEY, 'customer', phone],
    queryFn: () => bookingKanbanApi.customerProfile(phone!),
  });
}

export function useAssignees() {
  return useQuery({
    queryKey: [...KEY, 'assignees'],
    queryFn: () => bookingKanbanApi.assignees(),
    staleTime: 60_000,
  });
}

function invalidateKanban(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: KEY });
  // Also invalidate the team activity log + dashboard widgets that
  // read from the same activity table.
  qc.invalidateQueries({ queryKey: ['clinic', 'team-activity'] });
  qc.invalidateQueries({ queryKey: ['clinic', 'bookings'] });
}

export function useLogActivity(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { action: QuickAction; note?: string; outcome?: 'answered' | 'no_answer' }) =>
      bookingKanbanApi.logActivity(id, input.action, { note: input.note, outcome: input.outcome }),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useAddTag(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { scope: 'booking' | 'customer'; label: string; color: TagColor }) =>
      bookingKanbanApi.addTag(id, input.scope, input.label, input.color),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useRemoveTag(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { scope: 'booking' | 'customer'; tagId: number }) =>
      bookingKanbanApi.removeTag(id, input.scope, input.tagId),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useAssign(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { type: AssigneeKind | null; id: number | null }) =>
      bookingKanbanApi.assign(id, input.type, input.id),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useUpdateBookingStatus(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: {
      status: string;
      cancel_reason?: string;
      cancel_note?: string;
      completion_note?: string;
    }) => bookingKanbanApi.updateStatus(id, payload),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useUpdateBooking(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: UpdateBookingInput) => bookingKanbanApi.update(id, payload),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useCreateBooking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateBookingInput) => bookingKanbanApi.create(payload),
    onSuccess: () => invalidateKanban(qc),
  });
}

export function useTagLabels() {
  return useQuery({
    queryKey: [...KEY, 'tag-labels'],
    queryFn: () => bookingKanbanApi.tagLabels(),
    staleTime: 30_000,
  });
}

export function useUpdateCustomerNotes(customerId: number | null | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (notes: string | null) => {
      if (!customerId) return Promise.reject(new Error('missing_customer_id'));
      return bookingKanbanApi.updateCustomerNotes(customerId, notes);
    },
    onSuccess: () => invalidateKanban(qc),
  });
}
