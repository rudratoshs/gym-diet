<?php

namespace App;

use App\Models\AssessmentSession;
use App\Models\DietPlan;

interface AIServiceInterface
{
    public function generateDietPlan(AssessmentSession $session): DietPlan;
    public function generateMealPlans(DietPlan $dietPlan, array $responses, array $preferences): bool;
    public function testConnection(): bool;
}