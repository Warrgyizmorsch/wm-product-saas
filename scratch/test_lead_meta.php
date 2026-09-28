<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Domains\CRM\Controllers\Api\LeadApiController;

$user = User::first();
if (!$user) {
    echo "No user found in DB\n";
    exit;
}

auth()->login($user);

$controller = app(LeadApiController::class);
$response = $controller->meta(new Request());

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response Body:\n" . $response->getContent() . "\n";
