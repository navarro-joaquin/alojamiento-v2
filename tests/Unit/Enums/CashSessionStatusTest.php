<?php

use App\Enums\CashSessionStatus;

test('only an open session accepts new movements', function (CashSessionStatus $status, bool $acceptsMovements) {
    expect($status->aceptaMovimientos())->toBe($acceptsMovements);
})->with([
    'open' => [CashSessionStatus::Open, true],
    'closing' => [CashSessionStatus::Closing, false],
    'pending review' => [CashSessionStatus::PendingReview, false],
    'closed' => [CashSessionStatus::Closed, false],
]);
