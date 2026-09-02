<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryTransaction;
use App\Models\User;

interface GlJournalEncoder
{
    public function encodeIfEnabled(AccountingInventoryTransaction $transaction, User $user): void;
}
