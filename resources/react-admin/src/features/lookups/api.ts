import { apiClient } from '@/lib/api-client';

export interface ClinicLookup { id: number; name: string }
export interface CityLookup { id: number; name: string; name_en: string | null }
export interface CategoryLookup { id: number; name: string; name_en: string | null; slug: string; emoji: string | null }
export interface AdminLookup { id: number; name: string; role: string | null }
export interface SubClinicLookup { id: number; name: string }
export interface ServiceLookup { id: number; name: string }
export interface NamedLookup { id: number; name: string }

export const lookupsApi = {
  clinics: async (search?: string): Promise<ClinicLookup[]> => {
    const res = await apiClient.get<{ data: ClinicLookup[] }>('/lookups/clinics', { params: search ? { search } : {} });
    return res.data.data;
  },
  cities: async (search?: string): Promise<CityLookup[]> => {
    const res = await apiClient.get<{ data: CityLookup[] }>('/lookups/cities', { params: search ? { search } : {} });
    return res.data.data;
  },
  categories: async (search?: string): Promise<CategoryLookup[]> => {
    const res = await apiClient.get<{ data: CategoryLookup[] }>('/lookups/categories', { params: search ? { search } : {} });
    return res.data.data;
  },
  admins: async (role?: string, search?: string): Promise<AdminLookup[]> => {
    const params: Record<string, string> = {};
    if (role) params.role = role;
    if (search) params.search = search;
    const res = await apiClient.get<{ data: AdminLookup[] }>('/lookups/admins', { params });
    return res.data.data;
  },
  subClinics: async (clinicId: number): Promise<SubClinicLookup[]> => {
    const res = await apiClient.get<{ data: SubClinicLookup[] }>('/lookups/sub-clinics', { params: { clinic_id: clinicId } });
    return res.data.data;
  },
  services: async (clinicId: number): Promise<ServiceLookup[]> => {
    const res = await apiClient.get<{ data: ServiceLookup[] }>('/lookups/services', { params: { clinic_id: clinicId } });
    return res.data.data;
  },
  offers: async (clinicId: number): Promise<NamedLookup[]> => {
    const res = await apiClient.get<{ data: NamedLookup[] }>('/lookups/offers', { params: { clinic_id: clinicId } });
    return res.data.data;
  },
  doctors: async (clinicId: number): Promise<NamedLookup[]> => {
    const res = await apiClient.get<{ data: NamedLookup[] }>('/lookups/doctors', { params: { clinic_id: clinicId } });
    return res.data.data;
  },
  beforeAfter: async (clinicId: number): Promise<NamedLookup[]> => {
    const res = await apiClient.get<{ data: NamedLookup[] }>('/lookups/before-after', { params: { clinic_id: clinicId } });
    return res.data.data;
  },
};
