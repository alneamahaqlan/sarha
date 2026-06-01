import { z } from 'zod';

// Mirrors Store/UpdateWhatsAppSenderRequest::rules() server-side; this is only
// UX-level guardrails. Phone accepts a Saudi local (05XXXXXXXX) or international
// (+9665XXXXXXXX / 9665XXXXXXXX) form — the backend normalises it.
export const whatsappSenderFormSchema = z.object({
  label: z.string().max(255).nullish(),
  phone: z
    .string()
    .min(1, 'required')
    .regex(/^(\+?966|0)?5\d{8}$/, 'phone_invalid'),
  profile_id: z.string().max(255).nullish(),
  token: z.string().max(1000).nullish(),
  is_active: z.boolean(),
  priority: z.number().int().min(0).max(1000),
});

export type WhatsAppSenderFormSchema = z.infer<typeof whatsappSenderFormSchema>;
