import { useEffect, useState } from 'react';

/**
 * Returns a value that updates only after `delay` ms of no further changes.
 * Used to keep list-page server queries from firing on every keystroke.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(id);
  }, [value, delay]);

  return debounced;
}
