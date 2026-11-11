export interface Industry {
  id: number;
  key: string;
  name: string;
  description?: string | null;
  /** Morph aliases of what customers bring to be serviced (may be empty). */
  subjects: string[];
  /** Morph aliases price rules can target. */
  targets: string[];
}

export interface User {
  id: number;
  name: string;
  email: string;
  roles?: string[];
  industry?: Industry | null;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: User;
  token: string;
}
