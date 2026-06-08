import { createContext, useContext, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/lib/api-client';
import type { ClinicActing, CurrentUserResponse } from '@/types/api';

interface AuthContextValue {
  user: CurrentUserResponse['data'] | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  can: (perm: string) => boolean;
  /**
   * Subscription feature check (clinic-side). Defaults to TRUE when the
   * flag is absent so we never hide a surface the server still allows.
   */
  hasFeature: (feature: string) => boolean;
  /** Acting overlay (clinic-side only); null for non-clinic sessions. */
  acting: ClinicActing | null;
  refetch: () => Promise<unknown>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

async function fetchMe(): Promise<CurrentUserResponse['data'] | null> {
  try {
    const res = await apiClient.get<CurrentUserResponse>('/auth/me');
    return res.data.data;
  } catch (err: any) {
    if (err?.response?.status === 401) return null;
    throw err;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: fetchMe,
    staleTime: 60_000,
  });

  const value: AuthContextValue = {
    user: data ?? null,
    isLoading,
    isAuthenticated: !!data,
    can: (perm) => !!data?.permissions?.[perm],
    hasFeature: (feature) => data?.features?.[feature] ?? true,
    acting: data?.acting ?? null,
    refetch,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

/**
 * Convenience hook — checks an ability against the current user's
 * permissions map. Equivalent to `useAuth().can(ability)` but reads
 * cleaner at call sites that only need the check.
 */
export function useCan(ability: string): boolean {
  return useAuth().can(ability);
}
