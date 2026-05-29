import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { systemSettingsApi } from './api/system-settings.api';
import type { SystemSettingFormValues } from './types';

const KEY = ['admin', 'system-settings'] as const;

export function useSystemSettings(search?: string, group?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', search ?? '', group ?? ''],
    queryFn: () => systemSettingsApi.list(search, group),
  });
}

export function useUpdateSystemSetting(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<SystemSettingFormValues>) => systemSettingsApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
