<?php

namespace App\Services;

use DomainException;

/**
 * Thrown when an attempt is made to delete a customer, vendor or party
 * that is linked to financial or operational transactions (FRS 2.3:
 * "Parties linked to transactions are never hard-deleted").
 */
class PartyDeletionException extends DomainException
{
}
