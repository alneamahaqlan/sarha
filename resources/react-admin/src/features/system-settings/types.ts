export type SettingType = 'string' | 'integer' | 'decimal' | 'boolean' | 'json';

export const SETTING_TYPES: SettingType[] = ['string', 'integer', 'decimal', 'boolean', 'json'];

export interface SystemSetting {
  id: number;
  key: string;
  label: string;
  type: SettingType;
  group: string | null;
  value: string | null;
  description: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface SystemSettingFormValues {
  label: string;
  type: SettingType;
  group?: string | null;
  value?: string | null;
  description?: string | null;
}
