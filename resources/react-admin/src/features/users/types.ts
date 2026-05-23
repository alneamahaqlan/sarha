export interface User {
  id: number;
  name: string;
  phone: string;
  email: string | null;
  is_active: boolean;
  bookings_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface UserFormValues {
  name: string;
  phone: string;
  email?: string | null;
  is_active: boolean;
}
