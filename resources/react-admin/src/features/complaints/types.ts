export type ComplaintType = 'quality' | 'pricing' | 'misleading_info' | 'other';
export type ComplaintStatus = 'new' | 'in_review' | 'resolved' | 'rejected';
export type ComplaintPriority = 'low' | 'medium' | 'high';

export const COMPLAINT_TYPES: ComplaintType[] = ['quality', 'pricing', 'misleading_info', 'other'];
export const COMPLAINT_STATUSES: ComplaintStatus[] = ['new', 'in_review', 'resolved', 'rejected'];
export const COMPLAINT_PRIORITIES: ComplaintPriority[] = ['low', 'medium', 'high'];

export interface Complaint {
  id: number;
  reference_code: string;
  clinic_id: number | null;
  user_id: number | null;
  booking_id: number | null;
  customer_name: string;
  customer_phone: string;
  customer_email: string | null;
  type: ComplaintType;
  status: ComplaintStatus;
  priority: ComplaintPriority;
  subject: string;
  description: string;
  admin_notes: string | null;
  resolution: string | null;
  assigned_admin_id: number | null;
  resolved_at: string | null;
  clinic_notified: boolean;
  clinic?: { id: number; name: string } | null;
  assigned_admin?: { id: number; name: string } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ComplaintFormValues {
  clinic_id?: number | null;
  customer_name: string;
  customer_phone: string;
  customer_email?: string | null;
  type: ComplaintType;
  priority: ComplaintPriority;
  status: ComplaintStatus;
  assigned_admin_id?: number | null;
  subject: string;
  description: string;
  admin_notes?: string | null;
  resolution?: string | null;
}
