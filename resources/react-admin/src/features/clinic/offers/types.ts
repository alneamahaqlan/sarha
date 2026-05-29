export type OfferType = 'service' | 'general';
export type OfferStatus = 'active' | 'scheduled' | 'expired' | 'paused';

export interface OfferServiceSummary {
  id: number;
  name: string;
  price: number;
  image: string | null;
}

export interface Offer {
  id: number;
  clinic_id: number;
  service_id: number | null;
  type: OfferType;
  title: string;
  description: string | null;
  image: string | null;
  image_url: string | null;
  old_price: number | null;
  price: number | null;
  discount_percentage: number | null;
  starts_at: string | null;
  ends_at: string | null;
  is_featured: boolean;
  is_active: boolean;
  sort_order: number;
  status: OfferStatus;
  service?: OfferServiceSummary | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface OfferFormValues {
  type: OfferType;
  service_id?: number | null;
  title: string;
  description?: string | null;
  image?: string | null;
  old_price?: number | null;
  price?: number | null;
  starts_at?: string | null;
  ends_at: string;
  is_featured?: boolean;
  is_active?: boolean;
}
