export interface Service {
  id: number;
  clinic_id: number;
  sub_clinic_id: number | null;
  sub_clinic?: { id: number; name: string } | null;
  name: string;
  description: string | null;
  price: number;
  old_price: number | null;
  offer_expires_at: string | null;
  has_active_offer: boolean;
  discount_percentage: number;
  image: string | null;
  is_active: boolean;
  sort_order: number;
  clinic?: { id: number; name: string };
  created_at: string | null;
  updated_at: string | null;
}

export interface ServiceFormValues {
  clinic_id: number;
  name: string;
  description?: string | null;
  price: number;
  old_price?: number | null;
  offer_expires_at?: string | null;
  is_active: boolean;
}
