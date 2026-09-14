import { z } from 'zod'

/**
 * Client-side validation exists to give fast feedback. Laravel re-validates
 * everything and its errors win (PROJECT_SPEC.md §21).
 */

export const loginSchema = z.object({
  email: z.string().min(1, 'Enter your email address.').email('Enter a valid email address.'),
  password: z.string().min(1, 'Enter your password.'),
})

export type LoginValues = z.infer<typeof loginSchema>

export const registerSchema = z
  .object({
    name: z.string().min(1, 'Enter your name.').max(255, 'Keep this under 255 characters.'),
    email: z.string().min(1, 'Enter your email address.').email('Enter a valid email address.'),
    password: z.string().min(8, 'Use at least 8 characters.'),
    password_confirmation: z.string().min(1, 'Confirm your password.'),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: "Passwords don't match.",
  })

export type RegisterValues = z.infer<typeof registerSchema>
