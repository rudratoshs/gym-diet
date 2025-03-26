<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Load the mapping file
        $mapping = json_decode(file_get_contents(storage_path('app/config/clientProfileMapping.json')), true);

        // Initialize the result structure with basic identifiers
        $result = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'categories' => [],
        ];

        // Get all attributes from the model
        $attributes = array_merge(
            parent::toArray($request),
            [
                'bmi' => $this->bmi,
                'bmr' => $this->bmr,
                'daily_calories' => $this->daily_calories,
                'weight_goal_type' => $this->weight_goal_type,
                'weight_difference' => $this->weight_difference,
                'activity_level_display' => $this->activity_level_display,
                'diet_type_display' => $this->diet_type_display,
                'meal_timing_display' => $this->meal_timing_display,
                'stress_sleep_display' => $this->stress_sleep_display,
                'primary_goal_display' => $this->primary_goal_display,
                'plan_type_display' => $this->plan_type_display,
            ]
        );

        // Create a field-to-category mapping for lookup
        $fieldToCategory = [];
        foreach ($mapping as $category => $categoryData) {
            if (isset($categoryData['fields'])) {
                foreach ($categoryData['fields'] as $field => $fieldData) {
                    $fieldToCategory[$field] = $category;
                }
            }
        }

        // Initialize the categories with metadata
        foreach ($mapping as $category => $categoryData) {
            $result['categories'][$category] = [
                'name' => $categoryData['name'],
                'description' => $categoryData['description'],
                'data' => [],
                'fields' => $categoryData['fields']
            ];
        }

        // Add a category for unmapped fields
        $result['categories']['other'] = [
            'name' => 'Other Information',
            'description' => 'Additional client information',
            'data' => [],
            'fields' => []
        ];

        // Assign attributes to their categories
        foreach ($attributes as $key => $value) {
            // Skip the basic identifiers and timestamps
            if (in_array($key, ['id', 'user_id', 'created_at', 'updated_at'])) {
                continue;
            }

            // Find which category this field belongs to
            if (isset($fieldToCategory[$key])) {
                $category = $fieldToCategory[$key];
                $result['categories'][$category]['data'][$key] = $value;
            }
            // If it's a display field, place it with its base field
            else if (str_ends_with($key, '_display')) {
                $baseKey = str_replace('_display', '', $key);
                if (isset($fieldToCategory[$baseKey])) {
                    $category = $fieldToCategory[$baseKey];
                    $result['categories'][$category]['data'][$key] = $value;
                } else {
                    $result['categories']['other']['data'][$key] = $value;
                }
            }
            // If no category is found, add to "other"
            else {
                $result['categories']['other']['data'][$key] = $value;
            }
        }

        // Add timestamps to the root level
        $result['created_at'] = $this->created_at;
        $result['updated_at'] = $this->updated_at;

        // Remove categories with no data
        foreach ($result['categories'] as $category => $categoryData) {
            if (empty($categoryData['data'])) {
                unset($result['categories'][$category]);
            }
        }

        return $result;
    }
}