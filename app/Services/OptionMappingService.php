<?php
// app/Services/OptionMappingService.php

namespace App\Services;

use App\Config\QuickAssessmentQuestions;
use App\Config\ModerateAssessmentQuestions;
use App\Config\ComprehensiveAssessmentQuestions;
use Illuminate\Support\Facades\Log;

class OptionMappingService
{
    /**
     * Maps from ID values to human-readable labels for all configurable options
     * 
     * @return array
     */
    public static function getMappings()
    {
        // Get all question configurations
        $quickQuestions = QuickAssessmentQuestions::getQuestions();
        $moderateQuestions = ModerateAssessmentQuestions::getQuestions();
        $comprehensiveQuestions = ComprehensiveAssessmentQuestions::getQuestions();

        // Combine all questions into a single array
        $allQuestions = array_merge($quickQuestions, $moderateQuestions, $comprehensiveQuestions);

        // Create the mappings
        $mappings = [];

        foreach ($allQuestions as $key => $questionConfig) {
            // Only process questions with options
            if (isset($questionConfig['options'])) {
                $optionMappings = [];

                foreach ($questionConfig['options'] as $option) {
                    $optionMappings[$option['id']] = $option['title'];
                }

                // Add to mappings if we found options
                if (!empty($optionMappings)) {
                    $mappings[$key] = $optionMappings;
                }
            }
        }

        return $mappings;
    }

    /**
     * Transforms a profile or preferences array by replacing IDs with their labels
     * 
     * @param array $data The data to transform
     * @return array The transformed data
     */
    public static function transformData($data)
    {
        $mappings = self::getMappings();
        $result = [];

        foreach ($data as $key => $value) {
            // Skip null values
            if (is_null($value)) {
                continue;
            }

            // Check if this field has mappings
            if (isset($mappings[$key])) {
                // Handle single values
                if (is_string($value) || is_numeric($value)) {
                    // Check if the value is a JSON string
                    if (is_string($value) && self::isJson($value)) {
                        $decodedValue = json_decode($value, true);

                        // Handle array of IDs in JSON
                        if (is_array($decodedValue)) {
                            $labels = [];
                            foreach ($decodedValue as $id) {
                                $labels[] = $mappings[$key][$id] ?? $id;
                            }
                            $result[$key] = $labels;
                        } else {
                            // Single value in JSON
                            $result[$key] = $mappings[$key][$decodedValue] ?? $decodedValue;
                        }
                    } else {
                        // Regular value
                        $result[$key] = $mappings[$key][$value] ?? $value;
                    }
                }
                // Handle array values
                elseif (is_array($value)) {
                    $labels = [];
                    foreach ($value as $id) {
                        $labels[] = $mappings[$key][$id] ?? $id;
                    }
                    $result[$key] = $labels;
                }
            } else {
                // No mapping found, use original value
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Checks if a string is a valid JSON
     * 
     * @param string $string The string to check
     * @return bool Whether the string is valid JSON
     */
    private static function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}