export type BadgePlacement = 'header' | 'cards' | 'both';
export type BadgeMode = 'manual' | 'auto';

export interface Badge {
  id: number;
  key: string;
  label_ar: string;
  label_en: string;
  icon: string;
  color: string;
  placement: BadgePlacement;
  mode: BadgeMode;
  rule_key: string | null;
  rule_params: Record<string, number>;
  is_active: boolean;
  sort_order: number;
  assigned_count: number;
  /** Only present on show() — manual assignments for the editor. */
  manual_clinics?: ClinicLite[];
}

export interface BadgeFormValues {
  key: string;
  label_ar: string;
  label_en: string;
  icon: string;
  color: string;
  placement: BadgePlacement;
  mode: BadgeMode;
  rule_key: string | null;
  rule_params: Record<string, number>;
  is_active: boolean;
  sort_order: number;
}

export interface BadgeRuleMeta {
  key: string;
  label_ar: string;
  label_en: string;
  default_params: Record<string, number>;
}

export interface BadgeMeta {
  rules: BadgeRuleMeta[];
  icons: string[];
  colors: string[];
  placements: BadgePlacement[];
}

export interface ClinicLite {
  id: number;
  name: string;
}
