export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface SingleResponse<T> {
  data: T;
}

export interface ErrorResponse {
  message: string;
  errors?: Record<string, string[]>;
}

export type Guard = 'admin' | 'clinic' | 'web';

export interface CurrentUserResponse {
  data: {
    guard: Guard;
    user: {
      id: number;
      name: string | null;
      email: string | null;
      phone: string | null;
      role: string | null;
    };
    permissions: Record<string, boolean>;
    impersonating: boolean;
  };
}
