import { getBootstrap } from '@/lib/bootstrap';

export class ApiError extends Error {
  status: number;
  data?: unknown;

  constructor(message: string, status: number, data?: unknown) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const { restUrl, nonce } = getBootstrap();
  const url = `${restUrl.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;

  const headers: HeadersInit = {
    'X-WP-Nonce': nonce,
    ...(options.headers || {}),
  };

  if (!(options.body instanceof FormData)) {
    (headers as Record<string, string>)['Content-Type'] = 'application/json';
  }

  const response = await fetch(url, {
    ...options,
    headers,
    credentials: 'same-origin',
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message =
      (payload as { message?: string }).message ||
      `Request failed (${response.status})`;
    throw new ApiError(message, response.status, payload);
  }

  return payload as T;
}
