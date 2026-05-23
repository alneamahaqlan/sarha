import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { complaintsApi, type ComplaintListParams } from './api/complaints.api';
import type { ComplaintFormValues } from './types';

const KEY = ['admin', 'complaints'] as const;

export function useComplaints(params: ComplaintListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => complaintsApi.list(params),
  });
}

function invalidator(qc: ReturnType<typeof useQueryClient>) {
  return () => qc.invalidateQueries({ queryKey: KEY });
}

export function useCreateComplaint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: ComplaintFormValues) => complaintsApi.create(values),
    onSuccess: invalidator(qc),
  });
}

export function useMarkComplaintInReview() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => complaintsApi.markInReview(id), onSuccess: invalidator(qc) });
}

export function useResolveComplaint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, resolution }: { id: number; resolution: string }) => complaintsApi.resolve(id, resolution),
    onSuccess: invalidator(qc),
  });
}

export function useRejectComplaint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, admin_notes }: { id: number; admin_notes: string }) => complaintsApi.reject(id, admin_notes),
    onSuccess: invalidator(qc),
  });
}

export function useNotifyClinic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => complaintsApi.notifyClinic(id), onSuccess: invalidator(qc) });
}

export function useDeleteComplaint() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => complaintsApi.delete(id), onSuccess: invalidator(qc) });
}
