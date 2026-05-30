<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Default user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat conversations
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return \App\Modules\Social\Models\ConversationMember::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)->exists();
});

// Proposals (for both technicians receiving proposals and clients receiving status updates)
Broadcast::channel('proposals.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Job tracking (technician or client of the related request)
Broadcast::channel('jobs.{jobId}', function ($user, $jobId) {
    $job = \App\Modules\Operations\Models\TechnicianJob::with('request')->find($jobId);
    if (!$job) return false;
    return $user->id === $job->technician_id || $user->id === $job->request->client_id;
});

// Live geolocation tracking
Broadcast::channel('tracking.{jobId}', function ($user, $jobId) {
    $job = \App\Modules\Operations\Models\TechnicianJob::with('request')->find($jobId);
    if (!$job) return false;
    return $user->id === $job->technician_id || $user->id === $job->request->client_id;
});

// Wallet balance & payments
Broadcast::channel('wallet.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Quotes & invoices
Broadcast::channel('quotes.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Disputes
Broadcast::channel('disputes.{disputeId}', function ($user, $disputeId) {
    $dispute = \App\Modules\Governance\Models\Dispute::find($disputeId);
    if (!$dispute) return false;
    return $user->id === $dispute->opened_by || $user->id === $dispute->against;
});

// User-specific events (KYC, role upgrades)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Admin dashboard (admin-only)
Broadcast::channel('admin.dashboard', function ($user) {
    return $user->role === 'admin';
});
