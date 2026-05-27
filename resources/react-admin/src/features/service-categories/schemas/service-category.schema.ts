import { z } from 'zod';

// Mirrors StoreServiceCategoryRequest::rules() in Laravel. Server stays authoritative.
export const serviceCategoryFormSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  name_en: z.string().max(255).nullish(),
  slug: z
    .string()
    .min(1, 'required')
    .max(255)
    .regex(/^[a-z0-9-]+$/, 'must be lowercase letters, digits and dashes'),
  emoji: z.string().max(8).nullish(),
  icon: z.string().max(100).nullish(),
  description: z.string().max(1000).nullish(),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});

export type ServiceCategoryFormSchema = z.infer<typeof serviceCategoryFormSchema>;

// Same Laravel Str::slug equivalent the categories form uses for the
// live auto-fill on the name field.
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
