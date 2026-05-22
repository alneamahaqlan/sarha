import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ShieldAlert } from 'lucide-react';

import { apiClient, extractMessage } from '@/lib/api-client';
import { useAuth } from '@/app/providers/AuthProvider';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { queryClient } from '@/lib/query-client';

/**
 * Visible on every page while an admin is impersonating a clinic.
 * Mirrors the Filament impersonation banner Blade view.
 */
export function ImpersonationBanner() {
  const { t } = useTranslation();
  const { user } = useAuth();

  const stop = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post<{ data: { redirect: string } }>('/impersonation/stop');
      return res.data.data.redirect;
    },
    onSuccess: (redirect) => {
      queryClient.clear();
      window.location.href = redirect;
    },
    onError: (err) => toast.error(extractMessage(err, t('errors.generic'))),
  });

  if (!user?.impersonating) return null;

  return (
    <div className="flex items-center justify-between gap-3 bg-red-600 px-4 py-2 text-sm text-white">
      <div className="flex items-center gap-2">
        <ShieldAlert className="h-4 w-4" />
        <span>{t('impersonation.banner', { clinic: user.user.name ?? '' })}</span>
      </div>
      <button
        type="button"
        onClick={() => stop.mutate()}
        disabled={stop.isPending}
        className="rounded bg-white/15 px-3 py-1 text-xs font-medium hover:bg-white/25"
      >
        {stop.isPending ? t('common.loading') : t('impersonation.stop')}
      </button>
    </div>
  );
}
