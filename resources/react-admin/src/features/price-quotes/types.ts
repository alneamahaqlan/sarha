export type PriceQuoteStatus = 'new' | 'replied' | 'closed';

export const PRICE_QUOTE_STATUSES: PriceQuoteStatus[] = ['new', 'replied', 'closed'];

export interface PriceQuote {
  id: number;
  clinic_id: number;
  user_id: number | null;
  customer_name: string;
  customer_phone: string;
  service_name: string;
  description: string | null;
  status: PriceQuoteStatus;
  clinic_reply: string | null;
  clinic?: { id: number; name: string } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PriceQuoteFormValues {
  clinic_id: number;
  customer_name: string;
  customer_phone: string;
  service_name: string;
  description?: string | null;
  status: PriceQuoteStatus;
  clinic_reply?: string | null;
}
