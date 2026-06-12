import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicStoriesApi, type StoryFormValues } from './api';

const KEY = ['clinic', 'stories'] as const;

export function useClinicStories() {
  return useQuery({ queryKey: KEY, queryFn: () => clinicStoriesApi.list() });
}

export function useCreateStory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: StoryFormValues) => clinicStoriesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateStory(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<StoryFormValues>) => clinicStoriesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteStory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicStoriesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
