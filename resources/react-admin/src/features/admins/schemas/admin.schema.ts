import { z } from 'zod';

const passwordRule = z.string().min(8, 'min:8');

// Two variants — create requires password, edit treats it as optional.
export const createAdminSchema = z
  .object({
    name: z.string().min(1).max(255),
    email: z.string().email().max(255),
    password: passwordRule,
    password_confirmation: z.string(),
    role: z.enum(['super_admin', 'admin', 'sales']),
    is_active: z.boolean(),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'mismatch',
    path: ['password_confirmation'],
  });

export const updateAdminSchema = z
  .object({
    name: z.string().min(1).max(255),
    email: z.string().email().max(255),
    password: z.string().optional().or(z.literal('')),
    password_confirmation: z.string().optional().or(z.literal('')),
    role: z.enum(['super_admin', 'admin', 'sales']),
    is_active: z.boolean(),
  })
  .refine((d) => !d.password || d.password.length >= 8, {
    message: 'min:8',
    path: ['password'],
  })
  .refine((d) => !d.password || d.password === d.password_confirmation, {
    message: 'mismatch',
    path: ['password_confirmation'],
  });

export type CreateAdminSchema = z.infer<typeof createAdminSchema>;
export type UpdateAdminSchema = z.infer<typeof updateAdminSchema>;
