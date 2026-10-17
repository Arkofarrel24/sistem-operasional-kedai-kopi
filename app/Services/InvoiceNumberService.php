<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hands out INV-YYYYMMDD-0001 numbers from a per-day counter row.
 *
 * Concurrency (verified by tests/Concurrency):
 * - prepareDay() creates the day's row before the checkout transaction starts, so inserting a new
 *   row never happens while a checkout holds other locks (concurrent inserts of the same new key
 *   can deadlock in InnoDB).
 * - next() runs inside the checkout transaction and only bumps that existing row with a single
 *   UPDATE, which takes an exclusive row lock straight away. Concurrent checkouts are serialised
 *   on it until commit, so they never get the same number; a rolled-back checkout also rolls back
 *   its increment, so numbers stay gap-free. sales.invoice_no is unique as a last safeguard.
 */
class InvoiceNumberService
{
    public function prepareDay(CarbonInterface $at): void
    {
        retry(3, fn () => DB::table('invoice_sequences')->insertOrIgnore([
            'date' => $this->localDate($at),
            'last_number' => 0,
        ]), 25);
    }

    public function next(CarbonInterface $at): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Invoice numbers can only be issued inside a database transaction.');
        }

        $date = $this->localDate($at);

        $updated = DB::table('invoice_sequences')->where('date', $date)->increment('last_number');

        if ($updated === 0) {
            // prepareDay() was not called: fall back to an atomic insert-or-increment.
            DB::table('invoice_sequences')->upsert(
                ['date' => $date, 'last_number' => 1],
                ['date'],
                ['last_number' => DB::raw('last_number + 1')],
            );
        }

        $number = DB::table('invoice_sequences')->where('date', $date)->value('last_number');

        return sprintf('INV-%s-%04d', str_replace('-', '', $date), $number);
    }

    private function localDate(CarbonInterface $at): string
    {
        return $at->copy()->setTimezone(config('app.timezone'))->toDateString();
    }
}
