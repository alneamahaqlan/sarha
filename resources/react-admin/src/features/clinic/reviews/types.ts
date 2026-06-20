export interface ReviewReply {
  text: string;
  by_name: string | null;
  by_role: string | null;
  at: string | null;
}

export interface VerifiedReviewRow {
  id: number;
  reference_code: string;
  clinic_rating: number | null;
  doctor_rating: number | null;
  doctor: { id: number; name: string } | null;
  comment: string | null;
  customer_name: string | null;
  status: string;
  is_visible: boolean;
  submitted_at: string | null;
  reply: ReviewReply | null;
  report: { reason: string; at: string; decided: boolean; action: string | null } | null;
  created_at: string | null;
}

export type ReportReason = 'spam' | 'abuse' | 'fake' | 'other';

export interface ReviewFilters {
  rating?: number;
  replied?: 'yes' | 'no' | '';
  page?: number;
  per_page?: number;
}
