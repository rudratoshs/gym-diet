<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'question_id' => $this['question_id'],
            'prompt' => $this['prompt'],

            // Core data validation properties
            'validation' => $this['validation'] ?? null,
            'options' => $this['options'] ?? null,
            'multiple' => $this['multiple'] ?? false,

            // Progress tracking information
            'phase' => $this['phase'] ?? 1,

            // Additional context for the question that may help frontend
            // but doesn't dictate UI design
            'header' => $this['header'] ?? null,
            'body' => $this['body'] ?? null,
        ];
    }
}