export interface ServiceCategoryRef {
  id: number;
  name: string;
  name_en: string | null;
  slug: string | null;
  emoji: string | null;
}

export interface Service {
  id: number;
  clinic_id: number;
  sub_clinic_id: number | null;
  sub_clinic?: { id: number; name: string } | null;
  /** 1–5 specialties this service belongs to (many-to-many). */
  categories?: ServiceCategoryRef[];
  /** Convenience array of just the ids — used to seed edit form defaults. */
  category_ids?: number[];
  /** Doctors who provide this service (many-to-many). */
  doctors?: { id: number; name: string }[];
  /** Convenience array of just the doctor ids — seeds edit form defaults. */
  doctor_ids?: number[];
  name: string;
  description: string | null;
  price: number;
  /** Inline discounted price (the service's own offer), when set. */
  offer_price?: number | null;
  /** ISO date (YYYY-MM-DD) the inline offer ends. */
  offer_ends_at?: string | null;
  /** When true the price is a "starting from" minimum. */
  price_from?: boolean;
  price_includes?: string | null;
  price_excludes?: string | null;
  image: string | null;
  is_active: boolean;
  /** System-managed "خدمات أخرى" catch-all — locked from edit/delete. */
  is_catchall?: boolean;
  /** 'approved' shows publicly; 'pending' is hidden until an admin approves
   *  the catalog request this service belongs to. */
  approval_status?: 'approved' | 'pending';
  catalog_service_id?: number | null;
  catalog_service?: { id: number; name: string; status: string } | null;
  sort_order: number;
  clinic?: { id: number; name: string };
  created_at: string | null;
  updated_at: string | null;
}

export interface ServiceFormValues {
  clinic_id: number;
  category_ids: number[];
  name: string;
  description?: string | null;
  price: number;
  price_from?: boolean;
  price_includes?: string | null;
  price_excludes?: string | null;
  image?: string | null;
  is_active: boolean;
}
