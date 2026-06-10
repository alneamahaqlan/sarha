import type { BookingStatus } from '@/features/bookings/types';

/**
 * The 4 fixed semantic "kinds" a stage can belong to. These drive all
 * status-based business logic and the drag-and-drop dialogs; clinics can
 * add as many named stages as they like on top of these kinds.
 */
export type StageKind = 'new' | 'confirmed' | 'completed' | 'cancelled';

/** @deprecated kept as the kind type — use StageKind. */
export type KanbanColumn = StageKind;

export const STAGE_KINDS: StageKind[] = ['new', 'confirmed', 'completed', 'cancelled'];

export type StageColor = 'rose' | 'amber' | 'emerald' | 'sky' | 'violet' | 'slate';
export const STAGE_COLORS: StageColor[] = ['rose', 'amber', 'emerald', 'sky', 'violet', 'slate'];

/** A custom Kanban column owned by the clinic. */
export interface BookingStage {
  id: number;
  name: string;
  kind: StageKind;
  color: StageColor;
  sort_order: number;
  /** The 4 base stages (one per kind) — renamable but not deletable. */
  is_default: boolean;
}

export type SubBadge =
  | 'awaiting_contact'
  | 'contacted'
  | 'today'
  | 'within_48h'
  | 'within_week'
  | 'scheduled'
  | 'attended'
  | 'no_show'
  | 'cancelled';

export type Heat = 'red' | 'yellow' | 'green';

export type SuggestionKey =
  | 'confirm_urgent'
  | 'first_contact'
  | 'retry_call'
  | 'reminder_soon'
  | 'cancel_risk';

/** Per-suggestion config: always has `enabled`; threshold field varies. */
export interface SuggestionSetting {
  enabled: boolean;
  hours?: number;
  count?: number;
}

export type SuggestionSettings = Record<SuggestionKey, SuggestionSetting>;

export type AssigneeKind = 'Clinic' | 'ClinicTeamMember';

export interface AssigneePayload {
  type: AssigneeKind;
  id: number;
  name: string;
  role: string;
}

export interface AutoTags {
  is_vip: boolean;
  is_repeat: boolean;
  is_new: boolean;
  has_open_complaint: boolean;
  cancel_risk: boolean;
}

export interface TagDto {
  id: number;
  label: string;
  color: TagColor;
  scope: 'booking' | 'customer';
}

export type TagColor = 'rose' | 'amber' | 'emerald' | 'sky' | 'violet' | 'slate';
export const TAG_COLORS: TagColor[] = ['rose', 'amber', 'emerald', 'sky', 'violet', 'slate'];

/**
 * Acquisition channels — where the patient came from. Kept in sync with
 * Booking::ACQUISITION_SOURCES on the backend. 'other' is the default
 * when the team leaves the field blank on a manual booking.
 */
export type AcquisitionSource =
  | 'returning_customer'
  | 'walk_in'
  | 'phone_call'
  | 'whatsapp'
  | 'referral'
  | 'instagram'
  | 'snapchat'
  | 'tiktok'
  | 'twitter'
  | 'facebook'
  | 'google_maps'
  | 'cart'
  | 'other';

export const ACQUISITION_SOURCES: AcquisitionSource[] = [
  'returning_customer',
  'walk_in',
  'phone_call',
  'whatsapp',
  'referral',
  'instagram',
  'snapchat',
  'tiktok',
  'twitter',
  'facebook',
  'google_maps',
  'cart',
  'other',
];

export interface KanbanCard {
  id: number;
  customer_id: number | null;
  reference_code: string;
  customer_name: string;
  customer_phone: string;
  service: { id: number; name: string } | null;
  status: BookingStatus;
  kanban_column: KanbanColumn;
  sub_badge: SubBadge | null;
  appointment_at: string | null;
  created_at: string;
  acquisition_source: AcquisitionSource;
  stage_id: number | null;
  follow_up_priority: number;
  is_for_relative: boolean;
  auto_tags: AutoTags;
  suggestions: SuggestionKey[];
  heat: Heat;
  assignee: AssigneePayload | null;
  tags: TagDto[];
  customer_tags: TagDto[];
}

export interface KanbanColumnPayload {
  items: KanbanCard[];
  next_cursor: string | null;
  has_more: boolean;
  total: number;
}

/** Board payload keyed by stage id (string). */
export type KanbanBoard = Record<string, KanbanColumnPayload>;

export interface KanbanStats {
  today_count: number;
  yesterday_no_show: number;
  needs_urgent_confirm: number;
  weekly_confirm_rate: number | null;
}

export interface KanbanFilters {
  search?: string;
  service_id?: number;
  sub_clinic_id?: number;
  assignee_id?: number;
  assignee_type?: AssigneeKind;
  date_from?: string;
  date_to?: string;
  auto_tag?: 'urgent_confirm' | 'vip' | 'repeat' | 'new_customer' | 'has_complaint';
  /** Custom tag label (matches both booking + customer scope) */
  custom_tag?: string;
  /** Acquisition channel the patient came from */
  acquisition_source?: AcquisitionSource;
  mine_only?: boolean;
}

export interface CreateBookingInput {
  customer_name: string;
  customer_phone: string;
  service_id?: number | null;
  appointment_at?: string | null;
  notes?: string | null;
  clinic_notes?: string | null;
  status?: 'new' | 'contacted' | 'appointment_set';
  acquisition_source?: AcquisitionSource | null;
  assignee_type?: AssigneeKind | null;
  assignee_id?: number | null;
}

export interface UpdateBookingInput {
  status?: string;
  appointment_at?: string | null;
  clinic_notes?: string | null;
  acquisition_source?: AcquisitionSource;
}

export interface TagLabelOption {
  label: string;
  color: TagColor;
}

export interface BookingActivity {
  id: number;
  action: string;
  actor_name: string;
  actor_role: string;
  summary: Record<string, unknown> | null;
  created_at: string;
}

export type QuickAction =
  | 'call_attempted'
  | 'whatsapped'
  | 'reminder_sent'
  | 'patient_confirmed_verbally'
  | 'patient_cancelled_verbally'
  | 'note_added';

export interface CustomerProfile {
  customer_id: number | null;
  phone: string;
  name: string;
  /** True when `name` is the customer's own OTP-verified platform name. */
  name_verified?: boolean;
  /** Other names THIS clinic recorded for the same number. */
  alternative_names?: string[];
  email?: string | null;
  pinned_notes?: Array<{
    id: number;
    body: string;
    is_pinned: boolean;
    created_by_name: string;
    created_at: string;
  }>;
  summary: {
    total_bookings: number;
    completed_count: number;
    first_seen: string | null;
    is_vip: boolean;
    is_repeat: boolean;
    is_new: boolean;
    has_open_complaint: boolean;
    cancel_risk: boolean;
  };
  bookings: Array<{
    id: number;
    reference_code: string;
    service_name: string | null;
    status: BookingStatus;
    appointment_at: string | null;
    created_at: string;
  }>;
  complaints: Array<{
    id: number;
    reference_code: string;
    subject: string;
    status: string;
    priority: string;
    created_at: string;
  }>;
  price_quotes: Array<{
    id: number;
    service_name: string;
    status: string;
    created_at: string;
  }>;
  last_activity: {
    action: string;
    actor_name: string;
    created_at: string;
  } | null;
}

export interface BookingDetail {
  id: number;
  customer_id: number | null;
  reference_code: string;
  customer_name: string;
  customer_phone: string;
  notes: string | null;
  clinic_notes: string | null;
  status: BookingStatus;
  kanban_column: KanbanColumn;
  appointment_at: string | null;
  source: string;
  acquisition_source: AcquisitionSource;
  is_for_relative: boolean;
  service: { id: number; name: string; price: number | null } | null;
  booker: { id: number; name: string; phone: string } | null;
  relative: { id: number; name: string; relationship_type: string; relationship_label: string | null; phone: string } | null;
  assignee: AssigneePayload | null;
  auto_tags: AutoTags & {
    completed_count: number;
    total_bookings: number;
    first_seen: string | null;
  };
  suggestions: SuggestionKey[];
  heat: Heat;
  tags: TagDto[];
  customer_tags: TagDto[];
  created_at: string;
  updated_at: string;
  created_by_name: string | null;
  follow_up_priority: number;
}

export interface AssigneeOption {
  type: AssigneeKind;
  id: number;
  name: string;
  role: string;
}
