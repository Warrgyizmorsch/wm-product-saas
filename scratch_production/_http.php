<?php
/**
 * _http.php — shared helper for the scratch_production scripts.
 *
 * Simulates the front-end: every write goes through the real web route (HTTP kernel → middleware →
 * FormRequest validation → controller currency conversion → service → policies → countdown hooks),
 * exactly as if a user filled the form in the browser while viewing the app in INR.
 *
 * Usage inside a script:
 *     require __DIR__ . '/_http.php';
 *     $http = scratch_http();                       // logs in, sets tenant + INR
 *     $r = $http->send('POST', route('production.pay-grades.store'), [...]);
 *     $r->ok / $r->message / $r->errors / $r->json / $r->status
 *
 * Money typed into the payloads is in the ACTIVE currency (INR), like the forms.
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

const SCRATCH_TENANT_ID = 1;
const SCRATCH_USER_EMAIL = 'owner@warrg.com';   // tenant_owner of tenant 1
const SCRATCH_CURRENCY = 'INR';

final class ScratchResult
{
    public function __construct(
        public readonly int $status,
        public readonly bool $ok,
        public readonly ?string $message,
        public readonly array $errors,
        public readonly ?array $json,
        public readonly ?string $location,
        public readonly ?\Throwable $exception,
    ) {}

    /** One line for the console. */
    public function summary(): string
    {
        if ($this->ok) {
            return 'OK' . ($this->message ? " — {$this->message}" : '');
        }
        $parts = [];
        if ($this->message) {
            $parts[] = $this->message;
        }
        foreach ($this->errors as $field => $msgs) {
            foreach ((array) $msgs as $m) {
                $parts[] = "{$field}: {$m}";
            }
        }
        if ($this->exception && !$parts) {
            $parts[] = get_class($this->exception) . ': ' . $this->exception->getMessage();
        }

        return "FAILED (HTTP {$this->status}) — " . ($parts ? implode(' | ', $parts) : 'no message');
    }
}

final class ScratchHttp
{
    use MakesHttpRequests, InteractsWithAuthentication, InteractsWithSession;

    public $app;
    private string $csrf;

    public function __construct($app, User $user, Tenant $tenant)
    {
        $this->app = $app;
        $this->csrf = bin2hex(random_bytes(20));
        $this->actingAs($user);
        $this->withHeader(config('tenancy.header', 'X-Tenant'), $tenant->slug);
        $this->withHeader('Accept', 'text/html');
        $this->withSession(['active_currency' => SCRATCH_CURRENCY, '_token' => $this->csrf]);
    }

    /**
     * Send a form request like the browser does. $asJson = true sends an AJAX/JSON request
     * (for endpoints the page calls with fetch/$.ajax).
     */
    public function send(string $method, string $url, array $data = [], bool $asJson = false): ScratchResult
    {
        $store = $this->app['session.store'];
        $store->put('active_currency', SCRATCH_CURRENCY);
        $store->put('_token', $this->csrf);
        $store->forget(['success', 'error', 'errors', 'warning']);

        $method = strtoupper($method);
        if ($method !== 'GET') {
            $data['_token'] = $this->csrf;
        }

        $response = $asJson
            ? $this->json($method, $url, $data, ['X-Requested-With' => 'XMLHttpRequest', 'X-CSRF-TOKEN' => $this->csrf])
            : $this->call($method, $url, self::asFormStrings($data));

        $status = $response->getStatusCode();
        $json = null;
        if (str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $json = json_decode($response->getContent(), true);
        }

        $errors = [];
        $bag = $store->get('errors');
        if ($bag instanceof \Illuminate\Support\ViewErrorBag) {
            $errors = $bag->getBag('default')->toArray();
        }
        if ($json && isset($json['errors']) && is_array($json['errors'])) {
            $errors = $json['errors'];
        }

        $success = $store->get('success');
        $error = $store->get('error');
        $message = $success ?? $error ?? ($json['message'] ?? null);

        $ok = $status < 400
            && $error === null
            && $errors === []
            && !($json !== null && array_key_exists('success', $json) && $json['success'] === false);

        return new ScratchResult(
            status: (int) $status,
            ok: $ok,
            message: is_string($message) ? $message : null,
            errors: $errors,
            json: $json,
            location: $response->headers->get('Location'),
            exception: $response->exception ?? null,
        );
    }

    /** A browser form posts every value as text: ints/floats/bools become strings, null becomes ''. */
    private static function asFormStrings(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::asFormStrings($v);
            } elseif (is_bool($v)) {
                $data[$k] = $v ? '1' : '0';
            } elseif ($v === null) {
                $data[$k] = '';
            } elseif (is_int($v) || is_float($v)) {
                $data[$k] = (string) $v;
            }
        }

        return $data;
    }
}

function scratch_http(): ScratchHttp
{
    $tenant = Tenant::query()->findOrFail(SCRATCH_TENANT_ID);
    $user = User::query()->where('tenant_id', SCRATCH_TENANT_ID)->where('email', SCRATCH_USER_EMAIL)->firstOrFail();
    app(\App\Core\Tenant\TenantContext::class)->set($tenant);

    return new ScratchHttp(app(), $user, $tenant);
}

/** The INR → base factor the forms use (base = typed ÷ rate). */
function scratch_rate(): float
{
    return (float) config('currency.currencies.' . SCRATCH_CURRENCY . '.rate', 1);
}

function scratch_line(string $tag, string $text): void
{
    echo "  [{$tag}] {$text}" . PHP_EOL;
}

function scratch_header(string $title): void
{
    echo str_repeat('=', 64) . PHP_EOL . "  {$title} (Tenant " . SCRATCH_TENANT_ID . ', via web routes, ' . SCRATCH_CURRENCY . ')' . PHP_EOL . str_repeat('=', 64) . PHP_EOL . PHP_EOL;
}
