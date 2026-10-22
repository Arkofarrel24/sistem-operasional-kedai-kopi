<?php

namespace App\Http\Requests\Owner;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Report date range (Asia/Jakarta dates, end date inclusive). Defaults to this month so far.
 */
class ReportRangeRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    /**
     * An invalid range goes back to the report with its default range (never back to the invalid URL).
     */
    protected $redirectRoute = 'owner.reports.index';

    public function authorize(): bool
    {
        return true; // role is enforced by the route middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->from()->diffInDays($this->to()) + 1 > self::MAX_DAYS) {
                $validator->errors()->add('to', 'Rentang laporan maksimal '.self::MAX_DAYS.' hari.');
            }
        }];
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('from'), config('app.timezone'))->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('to'), config('app.timezone'))->startOfDay()
            : CarbonImmutable::today();
    }
}
