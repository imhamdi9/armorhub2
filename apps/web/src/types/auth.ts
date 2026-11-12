export interface Industry {
  id: number;
  key: string;
  name: string;
  description?: string | null;
  /** Morph aliases of what customers bring to be serviced (may be empty). */
  subjects: string[];
  /** Morph aliases price rules can target. */
  targets: string[];
  /** Shared lists admins manage (targets, makes/brands, catalog-style subjects). */
  catalogs?: string[];
}

export interface User {
  id: number;
  name: string;
  email: string;
  roles?: string[];
  permissions?: string[];
  company_id?: number | null;
  email_verified?: boolean;
  email_verification_required?: boolean;
  industry?: Industry | null;
  phone?: string | null;
  market?: { code: string; currency: string } | null;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: User;
}
