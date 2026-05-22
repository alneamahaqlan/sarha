import { z } from 'zod';

// Mirrors StoreCategoryRequest::rules() in Laravel. Server stays authoritative.
export const categoryFormSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  name_en: z.string().max(255).nullish(),
  slug: z
    .string()
    .min(1, 'required')
    .max(255)
    .regex(/^[a-z0-9-]+$/, 'must be lowercase letters, digits and dashes'),
  emoji: z.string().max(5).nullish(),
  icon: z.string().max(100).nullish(),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});

export type CategoryFormSchema = z.infer<typeof categoryFormSchema>;

// Lightweight Laravel Str::slug equivalent for the live auto-fill behavior
// that Filament's afterStateUpdated(...) implements. Server still re-validates.
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
