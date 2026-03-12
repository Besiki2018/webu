/**
 * Paths that must never be modified by auto-fix (auth, tenancy, billing, identity, security).
 */
export const FORBIDDEN_PATHS = [
  '.env', '.env.', 'config/auth.php', 'config/sanctum.php',
  'app/Http/Middleware/VerifyCsrfToken.php', 'app/Http/Middleware/Authenticate.php',
  'routes/auth.php', 'database/migrations/', 'storage/app/', 'bootstrap/', 'vendor/', 'node_modules/',
  'app/Http/Controllers/AccountDeletionController', 'app/Http/Controllers/PaymentGatewayController',
  'app/Http/Controllers/ProfileController', 'app/Models/User.php', 'app/Models/Subscription',
  'app/Services/AdminStatsService', 'billing/', 'auth/', 'app/Policies/',
];
export const ALLOWED_TENANCY_PATHS = ['app/Repositories/TenantScoped/', 'database/migrations/', 'docs/tenancy/'];

export function isForbidden(filePath: string): boolean {
  const normalized = filePath.replace(/\\/g, '/');
  for (const allowed of ALLOWED_TENANCY_PATHS) {
    if (normalized.includes(allowed)) return false;
  }
  for (const forbidden of FORBIDDEN_PATHS) {
    if (normalized.includes(forbidden)) return true;
  }
  return false;
}
