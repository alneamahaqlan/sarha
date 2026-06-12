import axios, { type AxiosError, type AxiosInstance } from 'axios';

const apiClient: AxiosInstance = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

let csrfFetched = false;

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfFetched) return;
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
  csrfFetched = true;
}

apiClient.interceptors.request.use(async (config) => {
  if (['post', 'put', 'patch', 'delete'].includes((config.method ?? '').toLowerCase())) {
    await ensureCsrfCookie();
  }
  return config;
});

// A single 401 is NOT proof the session is gone. Under a burst of concurrent
// requests (e.g. the refetch storm after a booking mutation) one request can
// transiently fail its session read and 401 while the session is actually
// still alive — confirmed by /auth/me returning 200 a moment later. Hard-
// redirecting on that false positive yanks the user back to the dashboard
// mid-work ("the page suddenly reloads"). So we CONFIRM with /auth/me before
// nuking the page, and only redirect when the session is genuinely gone.
let sessionGoneProbe: Promise<boolean> | null = null;

function confirmSessionGone(): Promise<boolean> {
  // De-dupe: a storm of 401s triggers a single probe, not one per request.
  if (!sessionGoneProbe) {
    sessionGoneProbe = axios
      .get('/api/v1/auth/me', { withCredentials: true, headers: { Accept: 'application/json' } })
      .then(() => false) // 200 → still authenticated → it was a transient 401
      .catch((e: AxiosError) => e.response?.status === 401) // 401 → genuinely logged out
      .finally(() => { sessionGoneProbe = null; });
  }
  return sessionGoneProbe;
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const url = error.config?.url ?? '';
    const isAuthProbe = url.includes('/auth/me') || url.includes('/auth/login');
    if (error.response?.status === 401 && !isAuthProbe) {
      const path = window.location.pathname;
      if (!path.startsWith('/app/login') && (await confirmSessionGone())) {
        window.location.href = '/app/login';
      }
    }
    return Promise.reject(error);
  },
);

export type ApiError = AxiosError<{ message?: string; errors?: Record<string, string[]> }>;

export function isApiError(err: unknown): err is ApiError {
  return axios.isAxiosError(err);
}

export function extractValidationErrors(err: unknown): Record<string, string[]> | undefined {
  if (isApiError(err)) return err.response?.data?.errors;
  return undefined;
}

export function extractMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (isApiError(err)) return err.response?.data?.message ?? fallback;
  return fallback;
}

export { apiClient };
