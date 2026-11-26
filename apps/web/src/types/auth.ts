export interface Industry {
  id: number;
  key: string;
  name: string;
  description?: string | null;
  /** Morph aliases of what customers bring to be serviced (may be empty). */
  subjects: string[];
  /** The subjects a customer keeps themselves (their property), not shared catalogs. */
  owned_subjects?: string[];
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
  latitude?: number | null;
  longitude?: number | null;
  email_verified?: boolean;
  email_verification_required?: boolean;
  industries?: { id: number; key: string; name: string }[];
  tenant?: { id: number; name: string } | null;
  department_id?: number | null;
  is_department_head?: boolean;
  industry?: Industry | null;
  phone?: string | null;
  /** Set while a platform administrator is viewing as this person (read-only): the name of that administrator. */
  impersonated_by?: string | null;
  /** Shopping without an account: a quiet session that can become a full account. */
  is_guest?: boolean;
  market?: { code: string; currency: string } | null;
  /** The store the account belongs to: where a customer goes shopping. */
  store_slug?: string | null;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: User;
}
