export interface AdminReviewRow {
  id: number;
  reference_code: string;
  clinic: { id: number; name: string; slug: string } | null;
  clinic_rating: number | null;
  doctor_rating: number | null;
  comment: string | null;
  customer_name: string | null;
  is_visible: boolean;
  submitted_at: string | null;
  report: { reason: string; note: string | null; by_name: string | null; at: string } | null;
  moderation: { action: string; reason: string | null; at: string; by: string | null } | null;
}

export interface ModFilters {
  scope?: 'pending' | 'decided';
  page?: number;
  per_page?: number;
}
