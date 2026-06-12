export type NavLocation = 'header' | 'footer';

export interface NavigationLink {
  id: number;
  location: NavLocation;
  footer_column: number | null;
  label_ar: string;
  label_en: string | null;
  url: string | null;
  static_page_id: number | null;
  route_name: string | null;
  open_new_tab: boolean;
  is_active: boolean;
  sort_order: number;
  resolved_url: string;
  static_page?: { id: number; slug: string; title_ar: string } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface NavigationLinkFormValues {
  location: NavLocation;
  footer_column?: number | null;
  label_ar: string;
  label_en?: string | null;
  url?: string | null;
  static_page_id?: number | null;
  route_name?: string | null;
  open_new_tab: boolean;
  is_active: boolean;
  sort_order: number;
}
