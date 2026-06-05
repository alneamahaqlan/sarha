export type CustomerSegment =
  | 'all'
  | 'vip'
  | 'repeat'
  | 'new'
  | 'new_month'
  | 'has_complaint'
  | 'inactive_90d';

export type LastInteractionWindow = '30d' | '90d' | '180d_plus';
export type BookingRange = '1' | '2-4' | '5-9' | '10+';
export type InteractionKind = 'booking' | 'complaint' | 'quote_request' | null;

export type TagColor = 'rose' | 'amber' | 'emerald' | 'sky' | 'violet' | 'slate';

export interface CustomerTagDto {
  id: number;
  label: string;
  color: TagColor;
}

export interface CustomerListRow {
  id: number;
  name: string;
  phone: string;
  email: string | null;
  total_bookings: number;
  completed_bookings: number;
  total_complaints: number;
  total_quote_requests: number;
  is_vip: boolean;
  is_repeat: boolean;
  is_new: boolean;
  has_prior_complaint: boolean;
  first_seen_at: string | null;
  last_seen_at: string | null;
  last_interaction_at: string | null;
  last_interaction_type: InteractionKind;
  tags: CustomerTagDto[];
  has_notes?: boolean | null;
}

export interface CustomerStats {
  total: number;
  vip: number;
  repeat: number;
  new_month: number;
  has_complaint: number;
  inactive_90d: number;
  avg_bookings: number;
}

export interface CustomerProfile {
  id: number;
  clinic_id: number;
  name: string;
  phone: string;
  email: string | null;
  user_id: number | null;
  first_seen_at: string | null;
  last_seen_at: string | null;
  last_interaction_at: string | null;
  last_interaction_type: InteractionKind;
  auto_tags: {
    is_vip: boolean;
    is_repeat: boolean;
    is_new: boolean;
    has_prior_complaint: boolean;
  };
  totals: {
    bookings: number;
    completed_bookings: number;
    cancelled_bookings: number;
    complaints: number;
    quote_requests: number;
    service_value: number;
  };
  last_booking: {
    id: number;
    reference_code: string;
    service_name: string | null;
    status: string;
    appointment_at: string | null;
    assignee_name: string | null;
  } | null;
  tags: CustomerTagDto[];
}

export interface CustomerNote {
  id: number;
  customer_id: number;
  body: string;
  is_pinned: boolean;
  created_by_name: string;
  created_by_role: string | null;
  is_author: boolean;
  can_edit: boolean;
  can_delete: boolean;
  can_pin: boolean;
  created_at: string;
  updated_at: string;
}

export interface CustomerBookingRow {
  id: number;
  reference_code: string;
  service_name: string | null;
  status: string;
  appointment_at: string | null;
  created_at: string;
  assignee_name: string | null;
}

export interface CustomerComplaintRow {
  id: number;
  reference_code: string;
  subject: string;
  status: string;
  priority: string;
  created_at: string;
}

export interface CustomerQuoteRow {
  id: number;
  service_name: string;
  status: string;
  created_at: string;
}

export type TimelineEvent =
  | {
      kind: 'booking' | 'complaint' | 'quote_request' | 'reminder';
      at: string;
      title_key: string;
      reference: string | null;
      status: string;
      detail: string | null;
      link_to: string | null;
    }
  | {
      kind: 'team_action';
      at: string;
      title_key: string;
      action_slug: string;
      actor_name: string;
      actor_role: string;
      summary: Record<string, unknown> | null;
    };

export interface ListFilters {
  search?: string;
  segment?: Exclude<CustomerSegment, 'all'>;
  booking_range?: BookingRange;
  has_notes?: boolean;
  last_interaction?: LastInteractionWindow;
  page?: number;
  per_page?: number;
}
