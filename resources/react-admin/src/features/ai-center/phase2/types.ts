export interface AiAnalyticsKpis {
  conversations: number;
  turns: number;
  unique_users: number;
  avg_length_turns: number;
  conversion_rate: number;
  tokens_in: number;
  tokens_out: number;
}

export interface AiAnalyticsTrendPoint {
  date: string;
  count: number;
}

export interface AiAnalyticsTop {
  id?: number;
  name?: string;
  topic?: string;
  count: number;
}

export interface AiKindBreakdown {
  kind: 'normal' | 'blocked' | 'emergency';
  count: number;
}

export interface AiProviderPerf {
  provider: string;
  count: number;
  avg_ms: number;
}

export interface AiAnalyticsPayload {
  range_days: number;
  computed_at: string;
  kpis: AiAnalyticsKpis;
  trend: AiAnalyticsTrendPoint[];
  top_topics: { topic: string; count: number }[];
  top_clinics: { id: number; name: string; count: number }[];
  top_categories: { id: number; name: string; count: number }[];
  kind_breakdown: AiKindBreakdown[];
  provider_perf: AiProviderPerf[];
}

export interface AiConversationStatus {
  status: 'normal' | 'blocked' | 'emergency';
}

export interface AiConversationRow {
  conversation_id: string;
  started_at: string;
  ended_at: string;
  turn_count: number;
  total_tokens: number;
  status: 'normal' | 'blocked' | 'emergency';
  user: { id: number; name: string | null } | null;
  visitor_id: string | null;
  summary: string;
  clinics: { id: number; name: string }[];
  categories: { id: number; name: string }[];
}

export interface AiConversationsPage {
  data: AiConversationRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface AiConversationTurn {
  id: number;
  conversation_id: string;
  user: { id: number; name: string | null; phone: string | null } | null;
  visitor_id: string | null;
  guard: string | null;
  query: string;
  reply: string;
  kind: string;
  provider: string | null;
  model: string | null;
  tokens_in: number;
  tokens_out: number;
  response_ms: number | null;
  locale: string | null;
  was_blocked: boolean;
  was_emergency: boolean;
  clinics: { id: number; name: string }[];
  categories: { id: number; name: string }[];
  created_at: string;
}

export interface AiDashboardWidget {
  today_count: number;
  yesterday_count: number;
  top_topic: { text: string; count: number } | null;
  alert: { kind: 'emergency' | 'block_rate_spike'; ratio?: number; created_at?: string } | null;
}

export type Seriousness = 'exploration' | 'comparing' | 'near_decision';

export interface UserAiInterestsTimelineRow {
  id: number;
  topic: string;
  seriousness: Seriousness;
  generated_at: string | null;
  conversation_id: string | null;
  categories: { id: number; name: string; emoji: string | null }[];
  clinics: { id: number; name: string; slug: string }[];
}

export interface UserAiInterestsPayload {
  has_history: boolean;
  conversation_count: number;
  last_interaction_at: string | null;
  top_specialty: { id: number; name: string; count: number } | null;
  top_clinics: { id: number; name: string; slug: string; count: number }[];
  timeline: UserAiInterestsTimelineRow[];
}
