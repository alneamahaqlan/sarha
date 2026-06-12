/** Small red message shown directly under a form field. Renders nothing when empty. */
export function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="text-xs text-[var(--color-destructive)]">{message}</p>;
}
