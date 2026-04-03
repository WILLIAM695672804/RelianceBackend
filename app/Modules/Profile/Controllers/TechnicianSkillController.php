<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Profile\Requests\StoreTechnicianSkillRequest;
use App\Modules\Profile\Models\TechnicianSkill;
use App\Modules\Profile\Resources\TechnicianSkillResource;

class TechnicianSkillController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Assume User has a generic belongsToMany relation to skills or we query pivot
        $skills = $user->skills()->get(); 
        
        return response()->json([
            'status' => 'success',
            'data' => TechnicianSkillResource::collection($skills)
        ]);
    }

    public function store(StoreTechnicianSkillRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        
        $meta = [
            'experience_years' => $validated['experience_years']
        ];

        // Ensure User has a "skills" belongsToMany relationship defined soon
        $user->skills()->syncWithoutDetaching([
            $validated['skill_id'] => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'proficiency' => $validated['proficiency'],
                'hourly_rate' => $validated['hourly_rate'] ?? 0,
                'travel_fee' => $validated['travel_fee'] ?? 0,
                'meta' => json_encode($meta)
            ]
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Skill added to profile'
        ]);
    }

    public function destroy(Request $request, $skillId)
    {
        $user = $request->user();
        $user->skills()->detach($skillId);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Skill removed from profile'
        ]);
    }
}
