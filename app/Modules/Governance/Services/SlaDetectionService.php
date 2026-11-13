<?php

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\SlaEvent;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Operations\Models\Appointment;
use App\Modules\Operations\Models\TechnicianJob;

/**
 * Détection automatique des manquements au SLA (retard, annulation, absence) et application des
 * pénalités définies par l'administrateur (sla_rules). Chaque manquement n'est enregistré qu'une fois.
 */
class SlaDetectionService
{
    public const TOLERANCE_RETARD_MINUTES = 15;
    public const DELAI_ABSENCE_MINUTES = 60;

    public function __construct(private SlaEnforcementService $enforcement, private NotificationService $notifications)
    {
    }

    /** À appeler à chaque changement de statut d'une mission par le technicien. */
    public function onJobStatusChanged(TechnicianJob $job, string $previous, string $new): void
    {
        if ($new === 'arrived' && $previous !== 'arrived') {
            $rendezVous = $job->appointment;
            if ($rendezVous && $rendezVous->scheduled_start) {
                $retard = (int) $rendezVous->scheduled_start->diffInMinutes(now(), false);
                if ($retard > self::TOLERANCE_RETARD_MINUTES) {
                    $this->enregistrer($job, 'late_arrival', ['minutes_late' => $retard, 'scheduled_start' => $rendezVous->scheduled_start->toIso8601String()]);
                }
            }
        }

        if ($new === 'cancelled' && !in_array($previous, ['completed', 'cancelled'], true)) {
            $this->enregistrer($job, 'technician_cancellation', ['previous_status' => $previous]);
        }
    }

    /**
     * Rendez-vous confirmés dont l'heure est dépassée de plus d'une heure sans que la mission ait démarré.
     *
     * @return int nombre d'absences constatées
     */
    public function detectNoShows(): int
    {
        $n = 0;
        $limite = now()->subMinutes(self::DELAI_ABSENCE_MINUTES);

        Appointment::where('status', 'confirmed')->where('scheduled_start', '<', $limite)->get()->each(function (Appointment $rdv) use (&$n) {
            $job = TechnicianJob::where('request_id', $rdv->request_id)->where('technician_id', $rdv->technician_id)->first();
            if ($job && !in_array($job->status, ['pending'], true)) {
                return; // le technicien s'est mis en route ou a terminé : pas d'absence
            }
            $event = $this->enregistrerPourRendezVous($rdv, $job);
            if ($event) {
                $rdv->update(['status' => 'no_show']);
                $n++;
            }
        });

        return $n;
    }

    private function enregistrerPourRendezVous(Appointment $rdv, ?TechnicianJob $job): ?SlaEvent
    {
        $cle = 'appointment_id';
        if (SlaEvent::where('request_id', $rdv->request_id)->where('event_key', 'no_show')->where("payload->{$cle}", $rdv->id)->exists()) {
            return null;
        }

        return $this->creer($rdv->request_id, $rdv->technician_id, 'no_show', ['appointment_id' => $rdv->id, 'scheduled_start' => $rdv->scheduled_start?->toIso8601String()]);
    }

    private function enregistrer(TechnicianJob $job, string $cle, array $payload): ?SlaEvent
    {
        // Idempotence : un manquement par mission et par type.
        if (SlaEvent::where('request_id', $job->request_id)->where('event_key', $cle)->where('payload->job_id', $job->id)->exists()) {
            return null;
        }

        return $this->creer($job->request_id, $job->technician_id, $cle, ['job_id' => $job->id] + $payload);
    }

    private function creer(string $requestId, string $technicianId, string $cle, array $payload): SlaEvent
    {
        $event = SlaEvent::create(['request_id' => $requestId, 'event_key' => $cle, 'occurred_at' => now(), 'payload' => $payload]);
        $penalites = $this->enforcement->evaluate($event);

        if ($penalites) {
            $points = collect($penalites)->where('penalty_type', 'reputation')->sum('penalty_value');
            $this->notifications->send(
                $technicianId, 'Pénalité de service',
                'Un manquement au service (' . $cle . ') a été constaté' . ($points ? " : -{$points} points de réputation." : '.'),
                ['type' => 'sla_penalty', 'request_id' => $requestId, 'event_key' => $cle], ['in_app', 'push']
            );
        }

        return $event;
    }
}
