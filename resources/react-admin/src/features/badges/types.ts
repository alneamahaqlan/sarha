export type BadgePlacement = 'header' | 'cards' | 'both';
export type BadgeMode = 'manual' | 'auto';

/** Target entity aliases — mirror App\Support\BadgeTargets. */
export type BadgeTargetType = 'clinic' | 'offer' | 'service' | 'doctor';

export interface Badge {
  id: number;
  key: string;
  target_types: BadgeTargetType[];
  label_ar: string;
  label_en: string;
  description_ar: string | null;
  description_en: string | null;
  icon: string;
  color: string;
  placement: BadgePlacement;
  mode: BadgeMode;
  rule_key: string | null;
  rule_params: Record<string, number>;
  is_active: boolean;
  sort_order: number;
  assigned_count: number;
  /** Only present on show() — manual assignments grouped by target type. */
  manual_targets?: Record<BadgeTargetType, TargetLite[]>;
}

export interface BadgeFormValues {
  key: string;
  target_types: BadgeTargetType[];
  label_ar: string;
  label_en: string;
  description_ar: string | null;
  description_en: string | null;
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
  target_type: BadgeTargetType;
  label_ar: string;
  label_en: string;
  default_params: Record<string, number>;
}

export interface BadgeIconMeta {
  key: string;
  label_ar: string;
}

export interface BadgeTargetMeta {
  key: BadgeTargetType;
  label_ar: string;
  label_en: string;
}

export interface BadgeMeta {
  rules: BadgeRuleMeta[];
  icons: BadgeIconMeta[];
  colors: string[];
  placements: BadgePlacement[];
  targets: BadgeTargetMeta[];
}

export interface TargetLite {
  id: number;
  name: string;
}
