<?php

namespace App\Http\Middleware;

use App\Domains\Platform\Models\PlatformAuditLog;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every state-changing request in the platform console (anything but
 * GET/HEAD/OPTIONS) to platform_audit_logs, so no admin screen can forget to.
 * Secrets in the payload are masked; failures to write never break the request.
 */
class RecordPlatformAudit
{
    private const MASKED = ['password', 'password_confirmation', 'secret', 'key_secret', 'webhook_secret', 'token', '_token', 'api_key'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || $request->user() === null) {
            return $response;
        }

        try {
            PlatformAuditLog::query()->create([
                'user_id' => $request->user()->id,
                'tenant_id' => $this->targetTenantId($request),
                'action' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'url' => Str::limit($request->fullUrl(), 495, ''),
                'payload' => $this->mask(Arr::except($request->all(), ['_method'])),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function targetTenantId(Request $request): ?int
    {
        $tenant = $request->route('tenant');

        return match (true) {
            $tenant instanceof Tenant => $tenant->id,
            is_numeric($tenant) => (int) $tenant,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mask($value);
            } elseif (is_string($key) && Str::contains(strtolower($key), self::MASKED)) {
                $data[$key] = '••••••';
            } elseif ($value instanceof \Illuminate\Http\UploadedFile) {
                $data[$key] = $value->getClientOriginalName();
            }
        }

        return $data;
    }
}
