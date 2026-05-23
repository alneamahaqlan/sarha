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

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const url = error.config?.url ?? '';
    const isAuthProbe = url.includes('/auth/me') || url.includes('/auth/login');
    if (error.response?.status === 401 && !isAuthProbe) {
      const path = window.location.pathname;
      if (!path.startsWith('/app/login')) {
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
