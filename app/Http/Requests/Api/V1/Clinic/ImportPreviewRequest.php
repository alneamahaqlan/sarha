<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dry-run read of a public Google Sheet: validates the URL, the column
 * mapping (spreadsheet letters), and the row range. Writes nothing.
 */
class ImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('create', Booking::class);
    }

    public function rules(): array
    {
        return [
            'sheet_url'                  => ['required', 'string', 'max:2048'],
            'row_from'                   => ['required', 'integer', 'min:1'],
            'row_to'                     => ['required', 'integer', 'gte:row_from'],
            'column_map'                 => ['required', 'array'],
            // customer_name + customer_phone are the two required columns; the
            // rest are optional. Each value is a spreadsheet column letter.
            'column_map.customer_name'   => ['required', 'string', 'max:4'],
            'column_map.customer_phone'  => ['required', 'string', 'max:4'],
            'column_map.service'         => ['nullable', 'string', 'max:4'],
            'column_map.appointment_at'  => ['nullable', 'string', 'max:4'],
            'column_map.notes'           => ['nullable', 'string', 'max:4'],
            // When re-pulling a saved source, lets the preview warn about
            // row ranges already imported before.
            'import_source_id'           => ['nullable', 'integer'],
        ];
    }
}
