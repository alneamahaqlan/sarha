import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  aiResponseTemplatesApi,
  aiRestrictionsApi,
} from './api';
import type {
  AiResponseTemplateFormValues,
  AiRestrictionFormValues,
  AiRestrictionType,
} from './types';

const REST_KEY = ['admin', 'ai-restrictions'] as const;
const TPL_KEY  = ['admin', 'ai-response-templates'] as const;

// ── Restrictions ─────────────────────────────────────────────────────────

export function useAiRestrictions(type?: AiRestrictionType) {
  return useQuery({
    queryKey: [...REST_KEY, 'list', type ?? 'all'],
    queryFn: () => aiRestrictionsApi.list(type),
  });
}

export function useCreateAiRestriction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: AiRestrictionFormValues) => aiRestrictionsApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: REST_KEY }),
  });
}

export function useUpdateAiRestriction(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<AiRestrictionFormValues>) => aiRestrictionsApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: REST_KEY }),
  });
}

export function useDeleteAiRestriction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => aiRestrictionsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: REST_KEY }),
  });
}

// ── Response templates ───────────────────────────────────────────────────

export function useAiResponseTemplates() {
  return useQuery({
    queryKey: [...TPL_KEY, 'list'],
    queryFn: () => aiResponseTemplatesApi.list(),
  });
}

export function useCreateAiResponseTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: AiResponseTemplateFormValues) => aiResponseTemplatesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: TPL_KEY }),
  });
}

export function useUpdateAiResponseTemplate(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<AiResponseTemplateFormValues>) => aiResponseTemplatesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: TPL_KEY }),
  });
}

export function useDeleteAiResponseTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => aiResponseTemplatesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: TPL_KEY }),
  });
}
