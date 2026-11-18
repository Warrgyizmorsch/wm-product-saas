<?php

namespace App\Services;

/** Thrown when a Meta webhook POST is not signed with a known app secret. */
class MetaWebhookSignatureException extends \RuntimeException
{
}
