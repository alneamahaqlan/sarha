import { z } from 'zod';

// Mirrors StoreStaticPageRequest::rules() in Laravel. Server stays authoritative.
export const staticPageFormSchema = z.object({
  slug: z
    .string()
    .min(1, 'required')
    .max(255)
    .regex(/^[a-z0-9-]+$/, 'must be lowercase letters, digits and dashes'),
  title_ar: z.string().min(1, 'required').max(255),
  title_en: z.string().max(255).nullish(),
  body_ar: z.string().nullish(),
  body_en: z.string().nullish(),
  meta_description_ar: z.string().max(300).nullish(),
  meta_description_en: z.string().max(300).nullish(),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});

export type StaticPageFormSchema = z.infer<typeof staticPageFormSchema>;

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
