export type AdminRole = 'super_admin' | 'admin' | 'sales';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: AdminRole;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface AdminFormValues {
  name: string;
  email: string;
  password?: string;
  password_confirmation?: string;
  role: AdminRole;
  is_active: boolean;
}
