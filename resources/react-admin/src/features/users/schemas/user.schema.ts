import { z } from 'zod';

export const userFormSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  phone: z.string().min(1, 'required').max(20),
  email: z.string().email().nullish().or(z.literal('')),
  is_active: z.boolean(),
});

export type UserFormSchema = z.infer<typeof userFormSchema>;
