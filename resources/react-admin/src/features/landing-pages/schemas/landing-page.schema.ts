import { z } from 'zod';

// Mirrors Store/UpdateLandingPageRequest::rules() in Laravel. Server stays
// authoritative — this is just fast client-side feedback.
export const landingPageFormSchema = z.object({
  type: z.enum(['clinic', 'offer', 'city', 'category', 'comparison', 'custom']),
  status: z.enum(['draft', 'published', 'archived']).optional(),
  slug: z
    .string()
    .min(1, 'required')
    .max(255)
    .regex(/^[a-z0-9-]+$/, 'must be lowercase letters, digits and dashes'),
  title_ar: z.string().max(255).nullish(),
  title_en: z.string().max(255).nullish(),
  internal_name: z.string().max(255).nullish(),
  clinic_id: z.number().int().positive().nullish(),
  offer_id: z.number().int().positive().nullish(),
  city_id: z.number().int().positive().nullish(),
  category_id: z.number().int().positive().nullish(),
  comparison_clinic_ids: z.array(z.number().int().positive()).optional(),
  cover_image: z.string().max(1000).nullish(),
  social_image: z.string().max(1000).nullish(),
  starts_at: z.string().nullish(),
  ends_at: z.string().nullish(),
  cta_label_ar: z.string().max(255).nullish(),
  cta_label_en: z.string().max(255).nullish(),
  cta_url: z.string().max(1000).nullish(),
  cta_style: z.enum(['book', 'link', 'scroll_booking']).nullish(),
  whatsapp_phone: z.string().max(20).nullish(),
  call_phone: z.string().max(20).nullish(),
  whatsapp_enabled: z.boolean().optional(),
  call_enabled: z.boolean().optional(),
});

export type LandingPageFormSchema = z.infer<typeof landingPageFormSchema>;

// Lightweight Str::slug equivalent for the live auto-fill on the title field.
export function slugify(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}
