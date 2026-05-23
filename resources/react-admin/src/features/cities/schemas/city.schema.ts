import { z } from 'zod';

// Mirrors StoreCityRequest::rules() / UpdateCityRequest::rules() in Laravel.
// Source of truth lives server-side; this is only UX-level guardrails.
export const cityFormSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  name_en: z.string().max(255).nullish(),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});

export type CityFormSchema = z.infer<typeof cityFormSchema>;
