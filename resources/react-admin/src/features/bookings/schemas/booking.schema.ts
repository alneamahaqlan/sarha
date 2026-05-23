import { z } from 'zod';

export const bookingFormSchema = z.object({
  clinic_id: z.number().int().positive(),
  customer_name: z.string().min(1).max(255),
  customer_phone: z.string().min(1).max(20),
  service_id: z.number().int().positive().nullish(),
  status: z.enum(['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled']),
  appointment_at: z.string().nullish(),
  notes: z.string().nullish(),
  clinic_notes: z.string().nullish(),
});

export type BookingFormSchema = z.infer<typeof bookingFormSchema>;
