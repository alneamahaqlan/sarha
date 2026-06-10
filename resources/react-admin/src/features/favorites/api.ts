import { apiClient } from '@/lib/api-client';

export interface FavoriteRow {
  id: number;
  type: 'service' | 'offer' | 'item';
  name: string;
  deleted: boolean;
  user: { id: number; name: string; phone: string } | null;
  saved_at: string | null;
}

export interface TopSaved {
  type: 'service' | 'offer' | 'item';
  name: string;
  saves: number;
}

export interface FavoritesResponse {
  data: FavoriteRow[];
  meta: { current_page: number; last_page: number; total: number };
  top_saved: TopSaved[];
}

export const adminFavoritesApi = {
  index: async (page: number) =>
    (await apiClient.get<FavoritesResponse>('/admin/favorites', { params: { page } })).data,
};
