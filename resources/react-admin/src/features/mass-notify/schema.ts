import { z } from 'zod';

// Mirrors MassNotifyRequest::rules() in Laravel.
export const massNotifySchema = z.object({
  audience: z.enum(['all', 'premium', 'basic', 'expiring']),
  priority: z.enum(['low', 'normal', 'high', 'urgent']),
  channel: z.enum(['in_app', 'email', 'both']),
  title: z.string().min(1).max(255),
  body: z.string().min(1).max(2000),
});

export type MassNotifySchema = z.infer<typeof massNotifySchema>;
