<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retour au cahier des charges : un seul portefeuille par utilisateur, en XAF.
 *
 * La migration 2026_11_13_000001 avait ouvert un portefeuille par devise (XAF, EUR, USD),
 * avec conversion entre eux — fonctionnalité qu'aucun document de cadrage ne prévoit
 * (schéma de référence : wallets.user_id UNIQUE ; cahier §4.4 : achat de crédit au taux
 * de l'administrateur, crédité au portefeuille). Les équivalents EUR/USD restent affichés,
 * calculés à partir des taux.
 *
 * Les portefeuilles EUR/USD sont supprimés, mais seulement s'ils sont vides : leurs
 * mouvements et fonds bloqués partiraient en cascade, et un solde serait de l'argent
 * effacé sans trace. Dans ce cas la migration refuse et nomme les comptes concernés.
 */
return new class extends Migration
{
    public function up(): void
    {
        $etrangers = DB::table('wallets')->where('currency', '<>', 'XAF');

        $engages = (clone $etrangers)
            ->where(function ($q) {
                $q->where('balance', '<>', 0)
                    ->orWhereExists(fn ($s) => $s->from('transactions')->whereColumn('transactions.wallet_id', 'wallets.id'))
                    ->orWhereExists(fn ($s) => $s->from('holds')->whereColumn('holds.wallet_id', 'wallets.id'));
            })
            ->get(['id', 'user_id', 'currency', 'balance']);

        if ($engages->isNotEmpty()) {
            $detail = $engages->map(fn ($w) => "{$w->user_id} ({$w->currency}, solde {$w->balance})")->implode(', ');
            throw new RuntimeException(
                "Portefeuilles en devise non vides : {$engages->count()} — {$detail}. "
                . 'Reconvertir ces fonds en XAF avant de migrer ; rien n\'a été modifié.'
            );
        }

        DB::transaction(function () use ($etrangers) {
            $etrangers->delete();

            Schema::table('wallets', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'currency']);
                $table->unique('user_id');
            });
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'currency']);
        });
    }
};
