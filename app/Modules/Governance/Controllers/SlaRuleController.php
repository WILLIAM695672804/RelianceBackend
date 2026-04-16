<?php

namespace App\Modules\Governance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Models\SlaRule;
use App\Modules\Governance\Requests\StoreSlaRuleRequest;
use App\Modules\Governance\Resources\SlaRuleResource;
use Illuminate\Http\Request;

class SlaRuleController extends Controller
{
    public function index(Request $request)
    {
        $query = SlaRule::query()->orderBy('created_at', 'desc');

        if ($request->has('event_key')) {
            $query->forEventKey($request->event_key);
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        return SlaRuleResource::collection($query->paginate(20));
    }

    public function store(StoreSlaRuleRequest $request)
    {
        $rule = SlaRule::create($request->validated());
        return new SlaRuleResource($rule);
    }

    public function show(string $id)
    {
        $rule = SlaRule::findOrFail($id);
        return new SlaRuleResource($rule);
    }

    public function update(StoreSlaRuleRequest $request, string $id)
    {
        $rule = SlaRule::findOrFail($id);
        $rule->update($request->validated());
        return new SlaRuleResource($rule);
    }

    public function toggle(string $id)
    {
        $rule = SlaRule::findOrFail($id);
        $rule->update(['active' => !$rule->active]);
        return new SlaRuleResource($rule);
    }
}
