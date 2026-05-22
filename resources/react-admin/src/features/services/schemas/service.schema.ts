import { z } from 'zod';

export const serviceFormSchema = z
  .object({
    clinic_id: z.number().int().positive(),
    name: z.string().min(1).max(255),
    description: z.string().nullish(),
    price: z.number().min(0),
    old_price: z
      .union([z.number().min(0), z.literal('')])
      .optional()
      .nullable()
      .transform((v) => (v === '' || v === undefined ? null : (v as number))),
    offer_expires_at: z.string().nullish(),
    is_active: z.boolean(),
  })
  .superRefine((data, ctx) => {
    if (data.old_price !== null && data.old_price !== undefined) {
      if (data.old_price <= data.price) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['old_price'], message: 'must_be_greater_than_price' });
      }
      if (!data.offer_expires_at) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['offer_expires_at'], message: 'required' });
      }
    }
  });

export type ServiceFormSchema = z.infer<typeof serviceFormSchema>;
