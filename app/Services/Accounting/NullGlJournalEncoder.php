<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryTransaction;
use App\Models\User;

class NullGlJournalEncoder implements GlJournalEncoder
{
    public function encodeIfEnabled(AccountingInventoryTransaction $transaction, User $user): void
    {
        // GL integration deferred — inventory-only encode in phase 1.
    }
}
