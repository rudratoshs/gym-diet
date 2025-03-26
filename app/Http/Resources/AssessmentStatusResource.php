<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Base fields for all status types
        $data = [
            'has_active_assessment' => $this['has_active_assessment'] ?? false,
            'has_completed_assessment' => $this['has_completed_assessment'] ?? false,
        ];

        // Add fields for active assessments
        if ($this['has_active_assessment']) {
            $data = array_merge($data, [
                'session_id' => $this['session_id'] ?? null,
                'assessment_type' => $this['assessment_type'] ?? null,
                'current_phase' => $this['current_phase'] ?? null,
                'current_question' => $this['current_question'] ?? null,
                'completion_percentage' => $this['completion_percentage'] ?? 0,
                'last_updated' => $this['last_updated'] ?? null,
                'phases' => $this['phases'] ?? null,
            ]);
        }

        // Add fields for completed assessments
        if ($this['has_completed_assessment'] && !$this['has_active_assessment']) {
            $data = array_merge($data, [
                'session_id' => $this['session_id'] ?? null,
                'assessment_type' => $this['assessment_type'] ?? null,
                'completed_at' => $this['completed_at'] ?? null,
            ]);
        }

        return $data;
    }
}