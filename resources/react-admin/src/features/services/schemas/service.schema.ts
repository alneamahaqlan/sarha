import { z } from 'zod';

export const serviceFormSchema = z.object({
  clinic_id: z.number().int().positive(),
  name: z.string().min(1).max(255),
  description: z.string().nullish(),
  price: z.number().min(0),
  image: z.string().nullish(),
  is_active: z.boolean(),
});

export type ServiceFormSchema = z.infer<typeof serviceFormSchema>;
