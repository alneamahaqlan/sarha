export type ReminderStatus = 'pending' | 'done' | 'cancelled';

export interface CustomerReminder {
  id: number;
  title: string | null;
  customer_id: number | null;
  customer_name: string | null;
  customer_phone: string | null;
  booking_id: number | null;
  assignee_member_id: number | null;
  assignee_name: string | null;
  remind_at: string | null;
  note: string | null;
  status: ReminderStatus;
  is_overdue: boolean;
  created_by_name: string | null;
  notified_at: string | null;
  completed_at: string | null;
  created_at: string | null;
}

export interface CreateReminderInput {
  customer_id?: number | null;
  title?: string | null;
  booking_id?: number | null;
  assignee_member_id?: number | null;
  remind_at: string; // ISO 8601
  note?: string | null;
}

/** index filter — mirrors the controller's ?status= switch. */
export type ReminderListStatus = 'open' | 'upcoming' | 'overdue' | 'done' | 'all';
