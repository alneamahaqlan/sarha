export type BookingStatus =
  | 'new'
  | 'contacted'
  | 'appointment_set'
  | 'completed'
  | 'no_show'
  | 'cancelled';

export const BOOKING_STATUSES: BookingStatus[] = [
  'new',
  'contacted',
  'appointment_set',
  'completed',
  'no_show',
  'cancelled',
];

export interface BookingRelative {
  id: number;
  name: string;
  /** Slug key from RELATIVE_TYPES on the server; "other" carries a free-text label. */
  relationship_type: string;
  relationship_label: string | null;
  phone: string;
}

export interface BookingBooker {
  id: number;
  name: string | null;
  phone: string | null;
}

export interface Booking {
  id: number;
  reference_code: string;
  clinic_id: number;
  user_id: number | null;
  service_id: number | null;
  customer_name: string;
  customer_phone: string;
  notes: string | null;
  clinic_notes: string | null;
  status: BookingStatus;
  appointment_at: string | null;
  source: string | null;
  clinic?: { id: number; name: string };
  service?: { id: number; name: string } | null;
  /** True when the booking was placed by an account holder on behalf of a relative. */
  is_for_relative: boolean;
  relative?: BookingRelative | null;
  booker?: BookingBooker | null;
  is_trashed: boolean;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}

export interface BookingFormValues {
  clinic_id: number;
  customer_name: string;
  customer_phone: string;
  service_id?: number | null;
  status: BookingStatus;
  appointment_at?: string | null;
  notes?: string | null;
  clinic_notes?: string | null;
}
