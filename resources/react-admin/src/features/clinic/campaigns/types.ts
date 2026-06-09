export type CampaignStatus = 'draft' | 'active' | 'completed';
export type RecipientStatus = 'pending' | 'sent' | 'skipped';
/** Fulfilment mode: clinic sends manually vs platform runs it externally. */
export type CampaignType = 'self' | 'managed';
export type ManagedStatus = 'submitted' | 'closed';

/** Mirrors the Customers Hub audience filters (reused for campaign targeting). */
export interface CampaignAudience {
  segment?: string | null;
  booking_range?: string | null;
  last_interaction?: string | null;
  has_notes?: boolean;
  search?: string | null;
}

export interface Campaign {
  id: number;
  type: CampaignType;
  name: string;
  message_template: string;
  image_path: string | null;
  image_url: string | null;
  audience: CampaignAudience | null;
  status: CampaignStatus;
  managed_status: ManagedStatus | null;
  total_recipients: number;
  sent_count: number;
  progress: number;
  created_by_name: string | null;
  created_at: string | null;
}

export interface CampaignRecipient {
  id: number;
  customer_id: number | null;
  name: string | null;
  phone: string | null;
  status: RecipientStatus;
  sent_at: string | null;
}

export interface CreateCampaignInput {
  name: string;
  type: CampaignType;
  message_template: string;
  image_path?: string | null;
  audience: CampaignAudience;
}

// Segment options reused from the Customers Hub.
export const CAMPAIGN_SEGMENTS = ['vip', 'repeat', 'new', 'new_month', 'has_complaint', 'inactive_90d'] as const;
export const CAMPAIGN_BOOKING_RANGES = ['1', '2-4', '5-9', '10+'] as const;
export const CAMPAIGN_LAST_INTERACTION = ['30d', '90d', '180d_plus'] as const;
