import { createContext, useContext, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/lib/api-client';
import type { CurrentUserResponse } from '@/types/api';

interface AuthContextValue {
  user: CurrentUserResponse['data'] | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  can: (perm: string) => boolean;
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
    refetch,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
