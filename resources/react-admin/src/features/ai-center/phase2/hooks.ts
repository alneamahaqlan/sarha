import { useQuery } from '@tanstack/react-query';
import { aiCenterPhase2Api, type ConversationsListParams } from './api';

const KEY = ['admin', 'ai-center', 'phase2'] as const;

export function useAiAnalytics(days: 7 | 30 | 90) {
  return useQuery({
    queryKey: [...KEY, 'analytics', days],
    queryFn: () => aiCenterPhase2Api.analytics(days),
    staleTime: 5 * 60_000,
  });
}

export function useAiConversations(params: ConversationsListParams) {
  return useQuery({
    queryKey: [...KEY, 'conversations', params],
    queryFn: () => aiCenterPhase2Api.conversations(params),
    placeholderData: (prev) => prev,
  });
}

export function useAiConversation(id: string | null, reveal: boolean) {
  return useQuery({
    queryKey: [...KEY, 'conversation', id, reveal],
    queryFn: () => aiCenterPhase2Api.conversation(id as string, reveal),
    enabled: !!id,
  });
}

export function useAiDashboardWidget() {
  return useQuery({
    queryKey: [...KEY, 'dashboard-widget'],
    queryFn: () => aiCenterPhase2Api.dashboardWidget(),
    staleTime: 60_000,
  });
}

export function useUserAiInterests(userId: number | null) {
  return useQuery({
    queryKey: [...KEY, 'user-interests', userId],
    queryFn: () => aiCenterPhase2Api.userInterests(userId as number),
    enabled: userId !== null,
  });
}
