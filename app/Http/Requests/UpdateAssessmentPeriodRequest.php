<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_name' => 'required|string|max:255',
            'fiscal_month' => 'required|numeric|min:1|max:12',
            'fiscal_year' => 'required|numeric|min:2000',
            'assessment_date' => 'required|date',
        ];
    }
}
