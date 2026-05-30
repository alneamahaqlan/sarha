import type { BookingStatus } from '@/features/bookings/types';

export type KanbanColumn = 'new' | 'confirmed' | 'completed' | 'cancelled';

export const KANBAN_COLUMNS: KanbanColumn[] = ['new', 'confirmed', 'completed', 'cancelled'];

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

export type KanbanBoard = Record<KanbanColumn, KanbanColumnPayload>;

export interface KanbanStats {
  today_count: number;
  yesterday_no_show: number;
  needs_urgent_confirm: number;
  weekly_confirm_rate: number | null;
}

export interface KanbanFilters {
  search?: string;
  service_id?: number;
  assignee_id?: number;
  assignee_type?: AssigneeKind;
  date_from?: string;
  date_to?: string;
  auto_tag?: 'urgent_confirm' | 'vip' | 'repeat' | 'new_customer' | 'has_complaint';
  /** Custom tag label (matches both booking + customer scope) */
  custom_tag?: string;
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
  assignee_type?: AssigneeKind | null;
  assignee_id?: number | null;
}

export interface UpdateBookingInput {
  status?: string;
  appointment_at?: string | null;
  clinic_notes?: string | null;
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
  email?: string | null;
  notes?: string | null;
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
  customer_notes?: string | null;
  reference_code: string;
  customer_name: string;
  customer_phone: string;
  notes: string | null;
  clinic_notes: string | null;
  status: BookingStatus;
  kanban_column: KanbanColumn;
  appointment_at: string | null;
  source: string;
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
}

export interface AssigneeOption {
  type: AssigneeKind;
  id: number;
  name: string;
  role: string;
}
