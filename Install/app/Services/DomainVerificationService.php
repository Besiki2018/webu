<?php

namespace App\Services;

use App\Models\Project;

class DomainVerificationService
{
    protected DomainSettingService $settingService;

    protected NotificationService $notificationService;

    public function __construct(DomainSettingService $settingService, NotificationService $notificationService)
    {
        $this->settingService = $settingService;
        $this->notificationService = $notificationService;
    }

    /**
     * Generate a unique verification token.
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get verification instructions for a project's custom domain.
     * Only CNAME verification is supported.
     */
    public function getVerificationInstructions(Project $project): array
    {
        $baseDomain = $this->settingService->getBaseDomain();
        $apexDomain = $this->isLikelyApexDomain($project->custom_domain ?? '');
        $baseIpv4 = $baseDomain ? $this->resolveBaseDomainIpv4($baseDomain) : null;

        if ($apexDomain) {
            $records = [
                [
                    'record_type' => 'A',
                    'host' => '@',
                    'value' => $baseIpv4 ?? 'YOUR_SERVER_IPV4',
                ],
                [
                    'record_type' => 'CNAME',
                    'host' => 'www',
                    'value' => $project->custom_domain,
                ],
            ];

            return [
                'method' => 'dns',
                'record_type' => 'A',
                'host' => '@',
                'value' => $baseIpv4 ?? 'YOUR_SERVER_IPV4',
                'records' => $records,
                'instructions' => "Add an A record for {$project->custom_domain} and a CNAME record for www.",
                'note' => $baseIpv4
                    ? 'After propagation, verification accepts A record match to platform server IP.'
                    : 'Set the A record to your platform server public IPv4. Verification will complete once DNS propagates.',
            ];
        }

        return [
            'method' => 'cname',
            'record_type' => 'CNAME',
            'host' => $project->custom_domain,
            'value' => $baseDomain ?? 'your-server-domain.com',
            'records' => [
                [
                    'record_type' => 'CNAME',
                    'host' => $project->custom_domain,
                    'value' => $baseDomain ?? 'your-server-domain.com',
                ],
            ],
            'instructions' => "Add a CNAME record pointing your domain to {$baseDomain}. This handles both verification and routing.",
            'note' => 'CNAME records cannot be used on apex/root domains (e.g., example.com). Use a subdomain (e.g., www.example.com) or check if your DNS provider supports CNAME flattening.',
        ];
    }

    /**
     * Verify a project's custom domain.
     * Only CNAME verification is supported.
     *
     * @return array{success: bool, error: ?string}
     */
    public function verify(Project $project): array
    {
        if (! $project->custom_domain) {
            return [
                'success' => false,
                'error' => 'No custom domain configured for this project.',
            ];
        }

        if ($this->isLikelyApexDomain($project->custom_domain)) {
            return $this->verifyApexDomain($project);
        }

        return $this->verifyCname($project);
    }

    /**
     * Verify apex domain using A record match.
     */
    protected function verifyApexDomain(Project $project): array
    {
        $baseDomain = $this->settingService->getBaseDomain();

        if (! $baseDomain) {
            return [
                'success' => false,
                'error' => 'Base domain not configured. Please contact the administrator.',
            ];
        }

        if ($this->checkDnsARecordToBaseDomain($project->custom_domain, $baseDomain)) {
            $this->markVerificationSuccess($project);

            return [
                'success' => true,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'error' => "A record not found. Please point {$project->custom_domain} to the platform server IP.",
        ];
    }

    /**
     * Verify domain using CNAME record.
     */
    protected function verifyCname(Project $project): array
    {
        $baseDomain = $this->settingService->getBaseDomain();

        if (! $baseDomain) {
            return [
                'success' => false,
                'error' => 'Base domain not configured. Please contact the administrator.',
            ];
        }

        if (
            $this->checkDnsCnameRecord($project->custom_domain, $baseDomain)
            || $this->checkDnsARecordToBaseDomain($project->custom_domain, $baseDomain)
        ) {
            $this->markVerificationSuccess($project);

            return [
                'success' => true,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'error' => "CNAME record not found. Please add a CNAME record pointing {$project->custom_domain} to {$baseDomain}.",
        ];
    }

    /**
     * Check for CNAME record pointing to target.
     */
    protected function checkDnsCnameRecord(string $domain, string $target): bool
    {
        try {
            $records = dns_get_record($domain, DNS_CNAME);

            if (! $records) {
                return false;
            }

            $target = rtrim(strtolower($target), '.');

            foreach ($records as $record) {
                if (isset($record['target'])) {
                    $recordTarget = rtrim(strtolower($record['target']), '.');
                    if ($recordTarget === $target) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if domain A record points to same IPv4 as base domain.
     */
    protected function checkDnsARecordToBaseDomain(string $domain, string $baseDomain): bool
    {
        $baseIps = $this->resolveDomainIpv4s($baseDomain);
        $domainIps = $this->resolveDomainIpv4s($domain);

        if ($baseIps === [] || $domainIps === []) {
            return false;
        }

        return count(array_intersect($domainIps, $baseIps)) > 0;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveDomainIpv4s(string $domain): array
    {
        try {
            $records = dns_get_record($domain, DNS_A);
            if (! is_array($records)) {
                return [];
            }

            $ips = [];
            foreach ($records as $record) {
                $ip = trim((string) ($record['ip'] ?? ''));
                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }

            return array_values(array_unique($ips));
        } catch (\Throwable) {
            return [];
        }
    }

    protected function resolveBaseDomainIpv4(string $baseDomain): ?string
    {
        $ips = $this->resolveDomainIpv4s($baseDomain);

        return $ips[0] ?? null;
    }

    protected function isLikelyApexDomain(string $domain): bool
    {
        $host = trim($domain);
        if ($host === '') {
            return false;
        }

        // Conservative heuristic: "example.com" is apex-like, "www.example.com" is subdomain.
        return substr_count($host, '.') <= 1;
    }

    protected function markVerificationSuccess(Project $project): void
    {
        $project->update([
            'custom_domain_verified' => true,
            'custom_domain_verified_at' => now(),
            'custom_domain_ssl_status' => $this->settingService->usesLetsEncrypt() ? 'pending' : null,
            'custom_domain_ssl_attempts' => 0,
            'custom_domain_ssl_next_retry_at' => null,
            'custom_domain_ssl_last_error' => null,
        ]);

        // Notify user about successful domain verification.
        if ($project->user) {
            $this->notificationService->notifyDomainVerified($project->user, $project);
        }
    }
}
