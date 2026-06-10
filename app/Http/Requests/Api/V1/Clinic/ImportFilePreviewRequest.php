<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dry-run read of an uploaded CSV/XLSX file: validates the file, the column
 * mapping (spreadsheet letters), and the row range. The file is stored for
 * the matching commit; nothing else is written.
 */
class ImportFilePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('create', Booking::class);
    }

    public function rules(): array
    {
        return [
            // CSV or XLSX, up to 5 MB. mimes is best-effort; the reader
            // validates the real structure on read.
            'file'                       => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'row_from'                   => ['required', 'integer', 'min:1'],
            'row_to'                     => ['required', 'integer', 'gte:row_from'],
            'column_map'                 => ['required', 'array'],
            'column_map.customer_name'   => ['required', 'string', 'max:4'],
            'column_map.customer_phone'  => ['required', 'string', 'max:4'],
            'column_map.service'         => ['nullable', 'string', 'max:4'],
            'column_map.appointment_at'  => ['nullable', 'string', 'max:4'],
            'column_map.notes'           => ['nullable', 'string', 'max:4'],
        ];
    }
}
