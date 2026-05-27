import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  bannerSlidesApi,
  homepageSectionsApi,
  type ReorderPayload,
} from './api/homepage-sections.api';
import type { BannerSlideFormValues, HomepageSectionFormValues } from './types';

const KEY = ['admin', 'homepage-sections'] as const;
const SLIDES_KEY = (sectionId: number) => ['admin', 'homepage-sections', sectionId, 'slides'] as const;

export function useHomepageSections() {
  return useQuery({
    queryKey: [...KEY, 'list'],
    queryFn: () => homepageSectionsApi.list(),
  });
}

export function useHomepageSection(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => homepageSectionsApi.get(id as number),
    enabled: id !== null,
  });
}

export function useUpdateHomepageSection(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: HomepageSectionFormValues) => homepageSectionsApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderHomepageSections() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => homepageSectionsApi.reorder(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

// ── Banner slides ────────────────────────────────────────────────────────────

export function useBannerSlides(sectionId: number | null) {
  return useQuery({
    queryKey: sectionId ? SLIDES_KEY(sectionId) : ['admin', 'homepage-sections', 'slides', 'noop'],
    queryFn: () => bannerSlidesApi.list(sectionId as number),
    enabled: sectionId !== null,
  });
}

export function useCreateBannerSlide(sectionId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: BannerSlideFormValues) => bannerSlidesApi.create(sectionId, values),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: SLIDES_KEY(sectionId) });
      qc.invalidateQueries({ queryKey: KEY });
    },
  });
}

export function useUpdateBannerSlide(sectionId: number, slideId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<BannerSlideFormValues>) =>
      bannerSlidesApi.update(sectionId, slideId, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: SLIDES_KEY(sectionId) }),
  });
}

export function useDeleteBannerSlide(sectionId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (slideId: number) => bannerSlidesApi.delete(sectionId, slideId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: SLIDES_KEY(sectionId) });
      qc.invalidateQueries({ queryKey: KEY });
    },
  });
}

export function useReorderBannerSlides(sectionId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => bannerSlidesApi.reorder(sectionId, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: SLIDES_KEY(sectionId) }),
  });
}
