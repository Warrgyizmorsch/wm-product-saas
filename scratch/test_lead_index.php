<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Domains\CRM\Controllers\Api\LeadApiController;

$user = User::first();
auth()->login($user);

$controller = app(LeadApiController::class);

$request = Request::create('/api/crm/leads', 'GET', [
    'search' => 'Vikram',
    'priority' => 'high',
    'status_id' => 1,
    'per_page' => 15
]);

$response = $controller->index($request);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response Body:\n" . $response->getContent() . "\n";
