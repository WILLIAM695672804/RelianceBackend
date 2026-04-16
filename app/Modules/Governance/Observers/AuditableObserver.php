<?php

namespace App\Modules\Governance\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Governance\Models\AuditLog;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->logAction($model, 'created', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $original = collect($model->getOriginal())
            ->only(array_keys($changes))
            ->toArray();

        if (empty($changes)) {
            return;
        }

        $this->logAction($model, 'updated', [
            'old' => $original,
            'new' => $changes,
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->logAction($model, 'deleted', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    protected function logAction(Model $model, string $action, array $details): void
    {
        AuditLog::create([
            'actor_id'    => auth()->id(),
            'object_type' => class_basename($model),
            'object_id'   => $model->getKey(),
            'action'      => $action,
            'details'     => $details,
        ]);
    }
}
