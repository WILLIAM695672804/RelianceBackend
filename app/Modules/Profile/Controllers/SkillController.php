<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Profile\Models\Skill;
use App\Modules\Profile\Resources\SkillResource;

class SkillController extends Controller
{
    public function index(Request $request)
    {
        $skills = Skill::all();
        
        return response()->json([
            'status' => 'success',
            'data' => SkillResource::collection($skills)
        ]);
    }
}
