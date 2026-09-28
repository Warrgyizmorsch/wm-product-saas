<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::first();
auth()->login($user);

$req = Request::create('/api/crm/leads/check-duplicate', 'GET', [
    'email' => 'vikram@example.com',
    'phone' => '+919876543210'
]);

$response = app()->handle($req);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response Body:\n" . $response->getContent() . "\n";
