import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { toApiError } from '@/lib/api-error'

/**
 * Move a failed request onto the form.
 *
 * Laravel's 422 payload is keyed by field name, so each message lands beside
 * the input it belongs to; anything else becomes a form-level message. The
 * server's validation is the one that decides (PROJECT_SPEC.md §21).
 *
 * @returns the message for errors that belong to no field, if any
 */
export function applyApiErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly Path<T>[],
): string | null {
  const apiError = toApiError(error)
  let matched = false

  for (const [field, messages] of Object.entries(apiError.errors)) {
    if ((fields as readonly string[]).includes(field)) {
      setError(field as Path<T>, { message: messages[0] })
      matched = true
    }
  }

  return matched ? null : apiError.message
}
