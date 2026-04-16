<?php

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\SlaRule;
use App\Modules\Governance\Models\SlaEvent;
use App\Modules\Gamification\Models\ReputationChange;
use App\Modules\Matching\Models\ClientRequest;

class SlaEnforcementService
{
    public function evaluate(SlaEvent $event): array
    {
        $rules = SlaRule::active()
            ->forEventKey($event->event_key)
            ->get();

        $applied = [];

        foreach ($rules as $rule) {
            $this->applyPenalty($rule, $event);
            $applied[] = [
                'rule_name'     => $rule->name,
                'penalty_type'  => $rule->penalty_type,
                'penalty_value' => $rule->penalty_value,
            ];
        }

        return $applied;
    }

    protected function applyPenalty(SlaRule $rule, SlaEvent $event): void
    {
        $request = $event->request;
        if (!$request) {
            return;
        }

        $targetUserId = $this->resolveTargetUser($request);
        if (!$targetUserId) {
            return;
        }

        if ($rule->penalty_type === 'reputation') {
            ReputationChange::create([
                'user_id' => $targetUserId,
                'change'  => -(int) $rule->penalty_value,
                'reason'  => "SLA penalty: {$rule->name} (event: {$event->event_key})",
            ]);
        }

        AuditService::log(
            'sla_penalty_applied',
            'SlaEvent',
            $event->id,
            [
                'rule_id'       => $rule->id,
                'rule_name'     => $rule->name,
                'penalty_type'  => $rule->penalty_type,
                'penalty_value' => $rule->penalty_value,
                'target_user'   => $targetUserId,
            ]
        );
    }

    protected function resolveTargetUser(ClientRequest $request): ?string
    {
        $proposal = $request->proposals()
            ->where('status', 'accepted')
            ->first();

        if ($proposal) {
            return $proposal->technician_id;
        }

        $appointment = $request->appointments()->first();

        return $appointment?->technician_id;
    }
}
