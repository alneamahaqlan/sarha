export interface DateRange {
  date_from?: string;
  date_to?: string;
}

export interface BestSellingRow {
  service_id: number;
  service_name: string | null;
  purchases: number;
  value: number;
}

export interface MostInterestedRow {
  service_id: number;
  service_name: string | null;
  interested: number;
}

export interface InterestedCustomerRow {
  customer_id: number;
  name: string | null;
  phone: string | null;
  created_at: string | null;
}

export interface ServiceBuyerRow {
  customer_id: number;
  name: string | null;
  phone: string | null;
  purchases: number;
  value: number;
}
