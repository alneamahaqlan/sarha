import { z } from 'zod';

// Mirrors StoreNavigationLinkRequest::rules(). Server stays authoritative.
export const navigationLinkFormSchema = z
  .object({
    location: z.enum(['header', 'footer']),
    footer_column: z.number().int().min(1).max(3).nullish(),
    label_ar: z.string().min(1, 'required').max(255),
    label_en: z.string().max(255).nullish(),
    url: z.string().max(2048).nullish(),
    static_page_id: z.number().int().nullish(),
    route_name: z.string().max(255).nullish(),
    open_new_tab: z.boolean(),
    is_active: z.boolean(),
    sort_order: z.number().int().min(0),
  })
  .refine((v) => v.location !== 'footer' || (v.footer_column != null), {
    path: ['footer_column'],
    message: 'required',
  })
  .refine((v) => !!v.static_page_id || !!v.route_name || !!(v.url && v.url.trim()), {
    path: ['url'],
    message: 'required',
  });

export type NavigationLinkFormSchema = z.infer<typeof navigationLinkFormSchema>;
