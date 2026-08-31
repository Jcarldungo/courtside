<?php

namespace App\Http\Requests;

use App\Support\SlotSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', Rule::exists('courts', 'id')],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('starts_at')) {
                    return;
                }

                if (! app(SlotSchedule::class)->isValidSlot($this->slotStartsAt())) {
                    $validator->errors()->add('starts_at', 'That is not one of this venue\'s slots.');
                }
            },
        ];
    }

    public function slotStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->string('starts_at')->toString());
    }
}
