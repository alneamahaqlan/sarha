export type SettingType = 'string' | 'integer' | 'decimal' | 'boolean' | 'json' | 'encrypted';

export const SETTING_TYPES: SettingType[] = ['string', 'integer', 'decimal', 'boolean', 'json', 'encrypted'];

export interface SystemSetting {
  id: number;
  key: string;
  label: string;
  type: SettingType;
  group: string | null;
  /** For encrypted rows the API returns the mask string (or null) — never the plaintext. */
  value: string | null;
  /** True when an actual value (encrypted or not) is stored. Drives the "Set / Not set" badge. */
  value_set?: boolean;
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
