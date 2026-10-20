<?php

require_once __DIR__ . '/../app/helpers.php';

use App\Http\Middleware\EnsureTenantModuleAccess;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\ResolveBranch;
use App\Http\Middleware\ResolveCompany;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetCurrency;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', SetCurrency::class);

        $middleware->alias([
            'identify.tenant' => IdentifyTenant::class,
            'tenant' => ResolveTenant::class,
            'tenant.required' => TenantMiddleware::class,
            'company' => ResolveCompany::class,
            'branch' => ResolveBranch::class,
            'module.access' => EnsureTenantModuleAccess::class,
            'billing.active' => \App\Http\Middleware\EnsureBillingActive::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
            'production.api.secret' => \App\Http\Middleware\ProductionApiSecretMiddleware::class,
            'production.api.tenant' => \App\Http\Middleware\ProductionTenantEnforcementMiddleware::class,
            'production.api.idempotency' => \App\Http\Middleware\ProductionIdempotencyMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'purchase/rfq-portal/*/submit',
            'crm/whatsapp/webhook',
            'platform/whatsapp/webhook',
            'webhooks/razorpay',
            'webhooks/meta-leadgen',
            'api/webhooks/meta-leadgen',
            'forms/lead-capture',
            'forms/lead-capture/*',
            'api/crm/public/lead-capture',
            'api/*',
        ]);

            $middleware->prependToPriorityList(
                before: ResolveTenant::class,
                prepend: \App\Http\Middleware\ProductionApiSecretMiddleware::class,
            );

            // ResolveTenant must run before auth resolves the user (TenantAwareUserProvider
            // needs the tenant context to decide whether the session user is accessible),
            // but Laravel's default priority list otherwise runs Authenticate first since
            // ResolveTenant isn't in it.
            $middleware->prependToPriorityList(
                before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
                prepend: ResolveTenant::class,
            );

            // Ensure company and branch are resolved before route model binding runs
            $middleware->prependToPriorityList(
                before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
                prepend: ResolveBranch::class,
            );
            $middleware->prependToPriorityList(
                before: ResolveBranch::class,
                prepend: ResolveCompany::class,
            );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A plan usage cap (UsageLimitService) hit from any create path: send the
        // user back to the form with the message instead of a 500.
        $exceptions->render(function (\App\Domains\Platform\Exceptions\UsageLimitExceededException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'plan_limit' => true], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        // A 403 caused by the tenant's plan (PlanFeatureGate) says so and points
        // at the upgrade, instead of a bare "This action is unauthorized."
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            $gate = app(\App\Domains\Platform\Services\PlanFeatureGate::class);
            $feature = $e->getStatusCode() === 403 ? $gate->lastBlocked() : null;

            if ($feature === null) {
                return null;
            }

            $label = $gate->label($feature) ?? 'This feature';
            $message = "{$label} isn't included in your plan. You can still view existing records — upgrade to create or change them.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'plan_feature' => $feature], 403);
            }

            return response()->view('errors.plan-feature', [
                'label' => $label,
                'message' => $message,
                'canUpgrade' => tenant() !== null && ($request->user()?->can('updateSubscription', tenant()) ?? false),
            ], 403);
        });
    })->create();
