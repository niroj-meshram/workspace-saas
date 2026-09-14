import { z } from 'zod'

export const projectSchema = z.object({
  name: z.string().min(1, 'Give the project a name.').max(255, 'Keep this under 255 characters.'),
  description: z.string().max(5000, 'Keep this under 5000 characters.').optional(),
})

export type ProjectValues = z.infer<typeof projectSchema>
