import { apiClient } from '@/lib/api-client';
import type {
  BestSellingRow,
  DateRange,
  InterestedCustomerRow,
  MostInterestedRow,
  ServiceBuyerRow,
} from './types';

function rangeParams(r: DateRange): Record<string, string> {
  const out: Record<string, string> = {};
  if (r.date_from) out.date_from = r.date_from;
  if (r.date_to) out.date_to = r.date_to;
  return out;
}

export const serviceReportsApi = {
  bestSelling: async (range: DateRange = {}): Promise<BestSellingRow[]> => {
    const res = await apiClient.get<{ data: BestSellingRow[] }>('/clinic/service-reports/best-selling', {
      params: rangeParams(range),
    });
    return res.data.data;
  },

  mostInterested: async (range: DateRange = {}): Promise<MostInterestedRow[]> => {
    const res = await apiClient.get<{ data: MostInterestedRow[] }>('/clinic/service-reports/most-interested', {
      params: rangeParams(range),
    });
    return res.data.data;
  },

  interestedCustomers: async (serviceId: number): Promise<InterestedCustomerRow[]> => {
    const res = await apiClient.get<{ data: InterestedCustomerRow[] }>('/clinic/service-reports/interested-customers', {
      params: { service_id: serviceId },
    });
    return res.data.data;
  },

  buyers: async (serviceId: number, range: DateRange = {}): Promise<ServiceBuyerRow[]> => {
    const res = await apiClient.get<{ data: ServiceBuyerRow[] }>('/clinic/service-reports/buyers', {
      params: { service_id: serviceId, ...rangeParams(range) },
    });
    return res.data.data;
  },
};
