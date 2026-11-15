<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Gamification\Models\Review;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Operations\Models\Appointment;
use App\Modules\Operations\Models\Intervention;
use App\Modules\Operations\Models\Invoice;
use App\Modules\Operations\Models\Quote;
use App\Modules\Operations\Models\TechnicianJob;
use App\Modules\Profile\Models\Location;
use App\Modules\Profile\Models\Skill;
use App\Modules\Schedule\Models\Schedule;
use App\Modules\Schedule\Models\TimeSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration : des techniciens et des clients dans chaque catégorie,
 * et entre eux toute la vie de la plateforme — demandes, propositions,
 * rendez-vous, interventions, devis, factures, paiements, mouvements de
 * portefeuille et avis.
 *
 *     php artisan db:seed --class=DemonstrationSeeder
 *
 * Relançable sans dommage : les comptes sont reconnus à leur adresse, les
 * dossiers à leur intitulé, les paiements à une référence dérivée de la
 * facture. Un second passage ne crée rien — vérifié par comparaison des
 * comptages et de la somme des soldes avant et après.
 *
 * Les valeurs ne sont pas tirées au sort mais dérivées de clés stables, par
 * « alea() ». C'est ce qui rend le second passage inoffensif : un vrai tirage
 * choisirait d'autres techniciens et d'autres prix, et le semeur ne
 * reconnaîtrait plus ce qu'il a lui-même écrit.
 *
 * Deux bases différentes, en revanche, ne donnent pas un jeu identique : les
 * clés dérivent d'identifiants UUID, propres à chaque base.
 *
 * Tout l'argent est en francs CFA, et les paiements portent le préfixe « SIM- »
 * du simulateur : rien de ce qui est ici n'est passé par NotchPay.
 */
class DemonstrationSeeder extends Seeder
{
    /** Techniciens et clients créés par catégorie. */
    private const TECHNICIENS_PAR_CATEGORIE = 3;
    private const CLIENTS_PAR_CATEGORIE = 3;

    /** Mot de passe commun à tous les comptes de démonstration. */
    private const MOT_DE_PASSE = 'demo1234';

    /**
     * Villes du périmètre, avec leur centre. Le rayon sert à disperser les
     * adresses : sans cela, tous les comptes partageraient un même point et le
     * rapprochement géographique n'aurait plus rien à départager.
     */
    private const VILLES = [
        'Douala' => [4.0511, 9.7679],
        'Yaoundé' => [3.8480, 11.5021],
        'Bafoussam' => [5.4781, 10.4179],
        'Garoua' => [9.3017, 13.3921],
    ];

    private const PRENOMS = [
        'Jean-Marc', 'Samuel', 'Fiona', 'Arnaud', 'Brice', 'Célestine', 'Didier',
        'Estelle', 'Franck', 'Ghislaine', 'Hervé', 'Irène', 'Joseph', 'Karine',
        'Landry', 'Mireille', 'Nathan', 'Odile', 'Patrice', 'Rosine', 'Serge',
        'Thérèse', 'Ulrich', 'Viviane',
    ];

    private const NOMS = [
        'Tchoupo', 'Ondoa', 'Kamga', 'Mbarga', 'Nkodo', 'Essomba', 'Fotso',
        'Ngassa', 'Biya', 'Manga', 'Tagne', 'Owona', 'Atangana', 'Njoya',
        'Bello', 'Eyenga', 'Mvondo', 'Sadjo', 'Toukam', 'Zambo',
    ];

    /** Travaux typiques par catégorie : titre, description, fourchette de prix. */
    private const TRAVAUX = [
        'Plomberie' => [
            ['Robinetterie de salle de bain à remplacer', 'Mitigeur de lavabo qui goutte et difficile à manœuvrer.', 18000, 55000],
            ['Fuite sous l\'évier de la cuisine', 'De l\'eau goutte en continu sous le meuble depuis deux jours. Le siphon semble fendu.', 15000, 45000],
            ['Canalisation de douche bouchée', 'L\'eau stagne et met plus d\'une heure à s\'évacuer. Déjà essayé un déboucheur du commerce.', 12000, 35000],
            ['Installation d\'un chauffe-eau 100 L', 'Remplacement d\'un ancien ballon hors service, arrivée et évacuation déjà en place.', 60000, 150000],
        ],
        'Électricité' => [
            ['Prises de courant à ajouter dans un bureau', 'Trois prises supplémentaires à tirer depuis le tableau, goulottes apparentes acceptées.', 30000, 85000],
            ['Disjoncteur qui saute chaque soir', 'La coupure survient toujours quand la climatisation et le fer démarrent ensemble.', 20000, 60000],
            ['Pose de huit points lumineux LED', 'Remplacement de l\'éclairage du salon et du couloir, faux plafond déjà posé.', 45000, 120000],
            ['Remise aux normes du tableau', 'Tableau d\'origine sans différentiel, maison de 1998, quatre circuits à reprendre.', 90000, 250000],
        ],
        'Informatique' => [
            ['Sauvegarde et réinstallation d\'un ordinateur', 'Système à réinstaller en conservant les documents et les photos.', 20000, 50000],
            ['Réseau Wi-Fi instable sur deux étages', 'Coupures fréquentes à l\'étage, besoin d\'un répéteur ou d\'un second point d\'accès.', 25000, 70000],
            ['Poste de travail très lent', 'Démarrage en plus de cinq minutes, disque probablement saturé.', 15000, 40000],
            ['Mise en réseau de quatre postes', 'Petit bureau, partage d\'imprimante et de dossiers à mettre en place.', 50000, 130000],
        ],
        'Électronique' => [
            ['Télécommande et capteur infrarouge hors service', 'Le téléviseur ne répond plus à aucune télécommande, y compris neuve.', 15000, 45000],
            ['Écran de téléviseur sans image', 'Le son fonctionne, l\'écran reste noir. Téléviseur de 55 pouces, trois ans d\'âge.', 25000, 80000],
            ['Smartphone : vitre cassée', 'Écran fendu après une chute, tactile encore fonctionnel.', 18000, 55000],
            ['Alimentation d\'un ampli en panne', 'Ne s\'allume plus du tout, aucun voyant.', 20000, 60000],
        ],
        'Sécurité' => [
            ['Interphone vidéo à installer', 'Portail d\'entrée, deux postes intérieurs souhaités.', 80000, 220000],
            ['Quatre caméras à poser sur une cour', 'Surveillance d\'un portail et d\'une cour, enregistreur à prévoir.', 120000, 350000],
            ['Alarme qui se déclenche sans raison', 'Déclenchements nocturnes répétés, sans intrusion constatée.', 20000, 65000],
            ['Contrôle d\'accès pour un immeuble', 'Badges pour douze logements, portail principal.', 150000, 400000],
        ],
        'Mécanique' => [
            ['Plaquettes et disques de frein avant', 'Grincement au freinage, véhicule de huit ans.', 35000, 110000],
            ['Climatisation de voiture inefficace', 'Souffle tiède même au maximum, recharge probablement nécessaire.', 25000, 75000],
            ['Démarrage difficile le matin', 'Plusieurs tentatives nécessaires à froid, batterie changée il y a six mois.', 20000, 90000],
            ['Révision complète et vidange', 'Véhicule à 120 000 km, révision des 120 000 à effectuer.', 45000, 120000],
        ],
        'Climatisation' => [
            ['Fuite de gaz sur un split', 'Perte de froid progressive sur trois semaines, traces d\'huile au raccord.', 30000, 90000],
            ['Split qui ne refroidit plus', 'Appareil de 12 000 BTU, souffle de l\'air ambiant depuis une semaine.', 25000, 70000],
            ['Installation de deux splits', 'Deux chambres à équiper, alimentation électrique à tirer.', 150000, 400000],
            ['Entretien annuel de trois appareils', 'Nettoyage des filtres et contrôle du gaz pour un petit commerce.', 30000, 85000],
        ],
        'Bâtiment' => [
            ['Porte d\'entrée qui ferme mal', 'Le bois a gonflé, la serrure force et le battant frotte en haut.', 25000, 75000],
            ['Infiltration au plafond d\'une chambre', 'Auréole qui grandit après chaque pluie, étanchéité de la terrasse à reprendre.', 60000, 200000],
            ['Peinture d\'un salon de 30 m²', 'Deux couches, murs et plafond, petites reprises d\'enduit.', 55000, 160000],
            ['Pose de carrelage sur 20 m²', 'Sol d\'une terrasse couverte, carrelage déjà acheté.', 70000, 190000],
        ],
    ];

    private array $compteurs = [
        'techniciens' => 0, 'clients' => 0, 'demandes' => 0, 'propositions' => 0,
        'rendez_vous' => 0, 'missions' => 0, 'interventions' => 0, 'devis' => 0, 'factures' => 0,
        'paiements' => 0, 'mouvements' => 0, 'avis' => 0,
    ];

    /**
     * Tirage déterministe : la même clé rend toujours la même valeur.
     *
     * Une graine fixe ne suffit pas. Au second passage, les dossiers déjà en
     * place court-circuitent des appels, la séquence dérive, et le semeur
     * choisit d'autres techniciens, d'autres prix, d'autres dates : il crée
     * des doublons au lieu de reconnaître ce qui existe. Tout ce qui décide
     * de l'identité d'une ligne passe donc par ici.
     */
    private function alea(string $cle, int $min, int $max): int
    {
        return $min + (crc32('reliance:' . $cle) % max(1, $max - $min + 1));
    }

    public function run(): void
    {

        $categories = Skill::query()->distinct()->pluck('category')->filter()->values();
        if ($categories->isEmpty()) {
            $this->command->error('Aucune compétence en base. Lancez d\'abord SkillsSeeder.');
            return;
        }

        $this->command->info('Catégories : ' . $categories->implode(', '));

        foreach ($categories as $categorie) {
            $this->command->line("  — {$categorie}");
            $techniciens = $this->creerTechniciens($categorie);
            $clients = $this->creerClients($categorie);

            foreach ($clients as $client) {
                $this->creerDossiers($client, $techniciens, $categorie);
            }
        }

        $this->afficherBilan();
    }

    // ------------------------------------------------------------- les acteurs

    /** @return User[] */
    private function creerTechniciens(string $categorie): array
    {
        $slugs = Skill::where('category', $categorie)->pluck('slug', 'id');
        $techniciens = [];

        for ($n = 1; $n <= self::TECHNICIENS_PAR_CATEGORIE; $n++) {
            $utilisateur = $this->creerCompte($categorie, 'tech', $n, 'technicien');
            $ville = $this->villeDe($categorie, $n);

            $this->donnerUneAdresse($utilisateur, $ville, 'Zone d\'intervention');
            $this->donnerDesCompetences($utilisateur, $slugs->keys()->all());
            $this->donnerUnPlanning($utilisateur);
            $this->ouvrirUnPortefeuille($utilisateur);

            $techniciens[] = $utilisateur;
            $this->compteurs['techniciens']++;
        }

        return $techniciens;
    }

    /** @return User[] */
    private function creerClients(string $categorie): array
    {
        $clients = [];

        for ($n = 1; $n <= self::CLIENTS_PAR_CATEGORIE; $n++) {
            $utilisateur = $this->creerCompte($categorie, 'client', $n, 'client');
            $this->donnerUneAdresse($utilisateur, $this->villeDe($categorie, $n), 'Domicile');
            $this->ouvrirUnPortefeuille($utilisateur);

            $clients[] = $utilisateur;
            $this->compteurs['clients']++;
        }

        return $clients;
    }

    private function creerCompte(string $categorie, string $genre, int $n, string $role): User
    {
        $cle = Str::slug($categorie);
        $courriel = "demo.{$genre}.{$cle}{$n}@reliance.cm";

        $attributs = [
            'name' => $this->unNom($categorie, $genre, $n),
            'phone' => '+237 6' . $this->alea("$courriel:a", 50, 99)
                . ' ' . $this->alea("$courriel:b", 100, 999)
                . ' ' . $this->alea("$courriel:c", 100, 999),
            'password' => Hash::make(self::MOT_DE_PASSE),
            'email_verified_at' => now(),
        ];

        // Colonnes ajoutées par les migrations d'évolution : présentes sur le
        // serveur, pas forcément sur une base partiellement migrée.
        $facultatives = [
            'is_active' => true,
            'verified' => $genre === 'tech',
            'rating' => $genre === 'tech' ? round($this->alea("$courriel:note", 38, 50) / 10, 1) : null,
            'num_reviews' => $genre === 'tech' ? $this->alea("$courriel:avis", 5, 60) : 0,
            'meta' => ['demonstration' => true, 'categorie' => $categorie],
        ];
        foreach ($facultatives as $colonne => $valeur) {
            if (Schema::hasColumn('users', $colonne) && $valeur !== null) {
                $attributs[$colonne] = $valeur;
            }
        }

        $utilisateur = User::firstOrCreate(['email' => $courriel], $attributs);

        if (!$utilisateur->hasRole($role)) {
            $utilisateur->assignRole($role);
        }

        return $utilisateur;
    }

    private function unNom(string $categorie, string $genre, int $n): string
    {
        $graine = crc32($categorie . $genre . $n);

        return self::PRENOMS[$graine % count(self::PRENOMS)]
            . ' ' . self::NOMS[intdiv($graine, 7) % count(self::NOMS)];
    }

    private function villeDe(string $categorie, int $n): string
    {
        $villes = array_keys(self::VILLES);

        return $villes[(crc32($categorie) + $n) % count($villes)];
    }

    // ------------------------------------------------------- adresses, agenda

    private function donnerUneAdresse(User $utilisateur, string $ville, string $libelle): void
    {
        if (Location::where('user_id', $utilisateur->id)->exists()) {
            return;
        }

        [$lat, $lng] = self::VILLES[$ville];
        // Dispersion d'environ trois kilomètres : les points doivent différer
        // pour que le rapprochement par distance ait un sens.
        $lat += $this->alea($utilisateur->id . ':lat', -280, 280) / 10000;
        $lng += $this->alea($utilisateur->id . ':lng', -280, 280) / 10000;

        $identifiant = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $identifiant,
            'user_id' => $utilisateur->id,
            'label' => $libelle,
            'address' => $this->uneAdresse($ville, $utilisateur->id),
            'city' => $ville,
            'is_default' => true,
            'meta' => json_encode(['latitude' => $lat, 'longitude' => $lng, 'radius' => 25]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement(
            'UPDATE locations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
            [$lng, $lat, $identifiant]
        );
    }

    private function uneAdresse(string $ville, string $cle): string
    {
        $quartiers = [
            'Douala' => ['Akwa', 'Bonapriso', 'Bonamoussadi', 'Deïdo', 'Makepe'],
            'Yaoundé' => ['Bastos', 'Mvan', 'Nlongkak', 'Essos', 'Mvog-Mbi'],
            'Bafoussam' => ['Tamdja', 'Kamkop', 'Djeleng', 'Banengo'],
            'Garoua' => ['Plateau', 'Foulbéré', 'Roumdé Adjia', 'Djamboutou'],
        ];
        $liste = $quartiers[$ville];

        return 'Quartier ' . $liste[$this->alea($cle . ':quartier', 0, count($liste) - 1)] . ', ' . $ville;
    }

    private function donnerDesCompetences(User $utilisateur, array $identifiants): void
    {
        if ($utilisateur->skills()->exists() || empty($identifiants)) {
            return;
        }

        // Rotation déterministe plutôt qu'un brassage : le même technicien
        // garde ses compétences d'une exécution à l'autre.
        sort($identifiants);
        $depart = $this->alea($utilisateur->id . ':competences', 0, count($identifiants) - 1);
        $combien = max(2, min(4, count($identifiants)));
        $retenus = [];
        for ($i = 0; $i < $combien; $i++) {
            $retenus[] = $identifiants[($depart + $i) % count($identifiants)];
        }
        foreach ($retenus as $identifiant) {
            $cle = $utilisateur->id . ':' . $identifiant;
            $utilisateur->skills()->attach($identifiant, [
                'id' => (string) Str::uuid(),
                'proficiency' => $this->alea($cle . ':niveau', 3, 5),
                'hourly_rate' => $this->alea($cle . ':taux', 3, 15) * 1000,
                'travel_fee' => $this->alea($cle . ':deplacement', 1, 6) * 500,
            ]);
        }
    }

    private function donnerUnPlanning(User $utilisateur): void
    {
        if ($utilisateur->schedule) {
            return;
        }

        $planning = Schedule::create(['user_id' => $utilisateur->id, 'tz' => 'Africa/Douala']);

        for ($jour = 0; $jour <= 6; $jour++) {
            if ($jour === 0 && $this->alea($utilisateur->id . ':dimanche', 0, 1) === 0) {
                continue;   // un technicien sur deux ne travaille pas le dimanche
            }
            TimeSlot::create([
                'schedule_id' => $planning->id,
                'day_of_week' => $jour,
                'start_time' => $jour === 0 ? '09:00:00' : '07:30:00',
                'end_time' => $jour === 0 ? '13:00:00' : '19:00:00',
                'repeat_weekly' => true,
            ]);
        }
    }

    /**
     * Le portefeuille s'ouvre à zéro, et son solde ne vient que des
     * mouvements : c'est ce que fait l'application, et c'est la seule façon
     * que les écrans de solde et d'historique racontent la même chose.
     */
    private function ouvrirUnPortefeuille(User $utilisateur): Wallet
    {
        // Un seul portefeuille par utilisateur, en XAF (unicité de user_id en base) ;
        // la devise reste explicite, comme dans les contrôleurs.
        return Wallet::firstOrCreate(
            ['user_id' => $utilisateur->id, 'currency' => 'XAF'],
            ['balance' => 0]
        );
    }

    // --------------------------------------------------------------- dossiers

    /**
     * Quatre dossiers par client, à quatre stades différents : un terminé et
     * payé, un en cours d'intervention, un en attente de réponse du client, un
     * tout juste déposé. Une démonstration n'a d'intérêt que si chaque écran
     * de l'application a quelque chose à montrer.
     *
     * @param User[] $techniciens
     */
    private function creerDossiers(User $client, array $techniciens, string $categorie): void
    {
        $travaux = self::TRAVAUX[$categorie] ?? self::TRAVAUX['Bâtiment'];

        $cle = $client->id;
        $stades = [
            ['etat' => 'termine', 'jours' => $this->alea("$cle:termine", 60, 150)],
            ['etat' => 'en_cours', 'jours' => $this->alea("$cle:encours", 2, 10)],
            ['etat' => 'propose', 'jours' => $this->alea("$cle:propose", 1, 4)],
            ['etat' => 'depose', 'jours' => 0],
        ];

        foreach ($stades as $index => $stade) {
            $travail = $travaux[$index % count($travaux)];
            $this->creerUnDossier($client, $techniciens, $categorie, $travail, $stade);
        }
    }

    private function creerUnDossier(
        User $client,
        array $techniciens,
        string $categorie,
        array $travail,
        array $stade
    ): void {
        [$titre, $description, $prixMin, $prixMax] = $travail;
        $cle = $client->id . ':' . $titre;
        $depot = Carbon::now()->subDays($stade['jours'])
            ->setTime($this->alea("$cle:h", 8, 18), $this->alea("$cle:m", 0, 59));

        $adresse = Location::where('user_id', $client->id)->first();
        $demande = $this->trouverOuCreerDemande($client, $titre, $description, $categorie, $adresse, $depot, $stade['etat']);

        if ($stade['etat'] === 'depose') {
            return;     // déposée, pas encore analysée : aucune proposition
        }

        // Deux ou trois propositions, dont une seule retenue : c'est ce qui
        // rend l'écran de comparaison utile.
        // Rotation déterministe sur les techniciens de la catégorie : le même
        // dossier retient toujours le même, et le second passage reconnaît ses
        // propositions au lieu d'en créer d'autres.
        $combien = min(count($techniciens), $this->alea("$cle:n", 2, 3));
        $depart = $this->alea("$cle:d", 0, count($techniciens) - 1);
        $candidats = [];
        for ($i = 0; $i < $combien; $i++) {
            $candidats[] = $techniciens[($depart + $i) % count($techniciens)];
        }
        $retenu = $candidats[0];
        $prix = $this->alea("$cle:prix", $prixMin, $prixMax);

        foreach ($candidats as $rang => $technicien) {
            $estRetenu = $rang === 0 && $stade['etat'] !== 'propose';
            $this->creerProposition(
                $demande,
                $technicien,
                $rang === 0 ? $prix : $this->alea("$cle:prix$rang", $prixMin, $prixMax),
                $estRetenu ? 'accepted' : ($stade['etat'] === 'propose' ? 'sent' : 'refused'),
                $depot
            );
        }

        if ($stade['etat'] === 'propose') {
            return;     // le client n'a pas encore tranché
        }

        $debut = $depot->copy()->addDays($this->alea("$cle:j", 1, 3))
            ->setTime($this->alea("$cle:rdv", 8, 16), 0);
        $rendezVous = $this->creerRendezVous($demande, $retenu, $adresse, $debut, $stade['etat']);
        $mission = $this->creerMission($demande, $retenu, $rendezVous, $debut, $stade['etat']);
        $this->creerIntervention($demande, $retenu, $mission, $debut, $stade['etat']);

        if ($stade['etat'] !== 'termine') {
            return;     // intervention en cours : ni facture ni paiement
        }

        $devis = $this->creerDevis($demande, $retenu, $prix, $depot);
        $facture = $this->creerFacture($devis, $client, $retenu, $prix, $debut);
        $this->creerPaiement($client, $retenu, $prix, $facture, $debut);
        $this->creerAvis($client, $retenu, $demande, $debut);
    }

    private function trouverOuCreerDemande(
        User $client,
        string $titre,
        string $description,
        string $categorie,
        ?Location $adresse,
        Carbon $depot,
        string $etat
    ): ClientRequest {
        $statuts = [
            'termine' => 'completed',
            'en_cours' => 'accepted',
            'propose' => 'proposed',
            'depose' => 'pending',
        ];

        $attributs = $this->colonnesValides('client_requests', [
            'client_id' => $client->id,
            'title' => $titre,
            'description' => $description,
            'detected_category' => Skill::where('category', $categorie)->value('id'),
            'address_id' => $adresse?->id,
            'status' => $statuts[$etat],
            'diagnostic_fee' => 5000,
            'metadata' => ['demonstration' => true],
            'created_at' => $depot,
            'updated_at' => $depot,
        ]);

        $existante = ClientRequest::where('client_id', $client->id)
            ->where('title', $titre)
            ->first();

        if ($existante) {
            return $existante;
        }

        $this->compteurs['demandes']++;
        $demande = ClientRequest::create($attributs);

        // client_requests porte sa propre colonne « geom », et c'est elle que
        // le rapprochement compare aux zones d'intervention. Sans elle, la
        // demande n'est proposée à personne.
        if ($adresse && Schema::hasColumn('client_requests', 'geom')) {
            DB::statement(
                'UPDATE client_requests SET geom = (SELECT geom::geometry FROM locations WHERE id = ?) WHERE id = ?',
                [$adresse->id, $demande->id]
            );
        }

        return $demande;
    }

    private function creerProposition(ClientRequest $demande, User $technicien, int $prix, string $statut, Carbon $depot): void
    {
        $existante = Proposal::where('request_id', $demande->id)
            ->where('technician_id', $technicien->id)->first();
        if ($existante) {
            return;
        }

        $cle = $demande->id . ':' . $technicien->id;

        Proposal::create($this->colonnesValides('proposals', [
            'request_id' => $demande->id,
            'technician_id' => $technicien->id,
            'price' => $prix,
            'message' => 'Je peux intervenir rapidement. Déplacement et diagnostic compris, '
                . 'pièces en supplément si remplacement nécessaire.',
            'status' => $statut,
            'expires_at' => $depot->copy()->addDays(7),
            'created_at' => $depot->copy()->addHours($this->alea($cle . ':depot', 1, 20)),
            'updated_at' => $depot->copy()->addHours($this->alea($cle . ':depot', 1, 20)),
        ]));

        $this->compteurs['propositions']++;
    }

    private function creerRendezVous(ClientRequest $demande, User $technicien, ?Location $adresse, Carbon $debut, string $etat): Appointment
    {
        $existant = Appointment::where('request_id', $demande->id)->first();
        if ($existant) {
            return $existant;
        }

        $this->compteurs['rendez_vous']++;

        return Appointment::create($this->colonnesValides('appointments', [
            'request_id' => $demande->id,
            'technician_id' => $technicien->id,
            'location_id' => $adresse?->id,
            'scheduled_start' => $debut,
            'scheduled_end' => $debut->copy()->addHours($this->alea('duree_rdv:' . $demande->id, 1, 4)),
            'status' => $etat === 'termine' ? 'completed' : 'confirmed',
            'notes' => 'Prévoir l\'accès au compteur et au point d\'eau.',
            'created_at' => $debut->copy()->subDays(1),
            'updated_at' => $debut,
        ]));
    }

    /**
     * La mission : ce que le technicien voit dans sa tournée. Une intervention
     * s'y rattache obligatoirement — « interventions.job_id » est NOT NULL.
     */
    private function creerMission(
        ClientRequest $demande,
        User $technicien,
        Appointment $rendezVous,
        Carbon $debut,
        string $etat
    ): TechnicianJob {
        $existante = TechnicianJob::where('request_id', $demande->id)
            ->where('technician_id', $technicien->id)->first();
        if ($existante) {
            return $existante;
        }

        $termine = $etat === 'termine';
        $this->compteurs['missions']++;

        return TechnicianJob::create($this->colonnesValides('technician_jobs', [
            'request_id' => $demande->id,
            'technician_id' => $technicien->id,
            'appointment_id' => $rendezVous->id,
            'status' => $termine ? 'completed' : 'in_progress',
            'estimated_travel_seconds' => $this->alea('trajet:' . $demande->id, 8, 45) * 60,
            'phases' => [
                ['phase' => 'trajet', 'fait' => true],
                ['phase' => 'diagnostic', 'fait' => true],
                ['phase' => 'reparation', 'fait' => $termine],
            ],
            'completion_notes' => $termine ? 'Client présent à la fin de l\'intervention, travaux acceptés.' : null,
            'started_at' => $debut,
            'completed_at' => $termine ? $debut->copy()->addHours(3) : null,
            'created_at' => $debut->copy()->subDays(1),
            'updated_at' => $debut,
        ]));
    }

    private function creerIntervention(
        ClientRequest $demande,
        User $technicien,
        TechnicianJob $mission,
        Carbon $debut,
        string $etat
    ): void {
        if (Intervention::where('request_id', $demande->id)->exists()) {
            return;
        }

        $termine = $etat === 'termine';
        $duree = $this->alea('duree:' . $demande->id, 10, 40) / 10;

        Intervention::create($this->colonnesValides('interventions', [
            'job_id' => $mission->id,
            'request_id' => $demande->id,
            'technician_id' => $technicien->id,
            'type' => 'standard',
            'status' => $termine ? 'completed' : 'in_progress',
            'description' => 'Diagnostic sur place puis réparation.',
            'work_performed' => $termine
                ? 'Pièce défectueuse remplacée, essais effectués devant le client, aucune reprise nécessaire.'
                : null,
            'materials_used' => $termine ? ['pièce de rechange', 'consommables'] : null,
            'duration_hours' => $termine ? $duree : null,
            'scheduled_at' => $debut,
            'started_at' => $debut,
            'completed_at' => $termine ? $debut->copy()->addHours($duree) : null,
            'created_at' => $debut->copy()->subDays(1),
            'updated_at' => $debut,
        ]));

        $this->compteurs['interventions']++;
    }

    private function creerDevis(ClientRequest $demande, User $technicien, int $prix, Carbon $depot): Quote
    {
        $existant = Quote::where('request_id', $demande->id)->first();
        if ($existant) {
            return $existant;
        }

        $this->compteurs['devis']++;

        // La main-d'œuvre fait les deux tiers, les pièces le reste : c'est la
        // répartition habituelle d'un dépannage à domicile.
        $mainOeuvre = (int) round($prix * 0.65);

        return Quote::create($this->colonnesValides('quotes', [
            'request_id' => $demande->id,
            'issuer_id' => $technicien->id,
            'total_amount' => $prix,
            'breakdown' => [
                ['libelle' => 'Main-d\'œuvre', 'montant' => $mainOeuvre],
                ['libelle' => 'Pièces et fournitures', 'montant' => $prix - $mainOeuvre - 5000],
                ['libelle' => 'Déplacement et diagnostic', 'montant' => 5000],
            ],
            'status' => 'accepted',
            'valid_until' => $depot->copy()->addDays(15),
            'created_at' => $depot->copy()->addDays(1),
            'updated_at' => $depot->copy()->addDays(2),
        ]));
    }

    private function creerFacture(Quote $devis, User $client, User $technicien, int $prix, Carbon $debut): Invoice
    {
        $existante = Invoice::where('quote_id', $devis->id)->first();
        if ($existante) {
            return $existante;
        }

        $this->compteurs['factures']++;

        return Invoice::create($this->colonnesValides('invoices', [
            'quote_id' => $devis->id,
            'client_id' => $client->id,
            'issuer_id' => $technicien->id,
            'amount' => $prix,
            'tax_amount' => 0,          // franchise de TVA pour les artisans
            'reference' => 'FAC-' . strtoupper(Str::random(8)),
            'status' => 'paid',
            'created_at' => $debut,
            'updated_at' => $debut,
        ]));
    }

    private function creerPaiement(User $client, User $technicien, int $prix, Invoice $facture, Carbon $debut): void
    {
        // Référence dérivée de la facture : elle identifie le paiement d'un
        // passage à l'autre. Une référence tirée au sort en créait un nouveau
        // à chaque exécution, avec ses mouvements de portefeuille.
        $reference = 'SIM-' . strtoupper(substr(md5('paiement:' . $facture->id), 0, 12));

        if (Payment::where('external_ref', $reference)->exists()) {
            return;
        }

        $paiement = Payment::create($this->colonnesValides('payments', [
            'payer_id' => $client->id,
            'payee_id' => $technicien->id,
            'method' => ['momo', 'om', 'card'][$this->alea('moyen:' . $facture->id, 0, 2)],
            'amount' => $prix,
            'currency' => 'XAF',
            'status' => 'success',
            'external_ref' => $reference,
            'metadata' => ['simulation' => true, 'facture' => $facture->reference ?? null],
            'settled_at' => $debut->copy()->addHours(2),
            'created_at' => $debut->copy()->addHours(2),
            'updated_at' => $debut->copy()->addHours(2),
        ]));
        $this->compteurs['paiements']++;

        // La commission de la plateforme, retenue sur ce qui revient au
        // technicien : le solde du portefeuille doit la refléter, sinon les
        // écrans de revenus racontent n'importe quoi.
        $commission = (int) round($prix * 0.10);

        // Une recharge précède le règlement : un portefeuille ne se débite pas
        // à découvert. Le reste laissé au client est volontaire — les écrans
        // de solde doivent montrer autre chose que zéro.
        $recharge = (int) (ceil(($prix + $this->alea('recharge:' . $facture->id, 5, 40) * 1000) / 500) * 500);
        $this->mouvementer($client, $recharge, 'credit', $paiement, $debut->copy()->subHours(3));
        $this->mouvementer($client, -$prix, 'debit', $paiement, $debut);
        $this->mouvementer($technicien, $prix - $commission, 'credit', $paiement, $debut);
        $this->mouvementer($technicien, -$commission, 'fee', $paiement, $debut);
    }

    private function mouvementer(User $utilisateur, int $montant, string $type, Payment $paiement, Carbon $quand): void
    {
        $portefeuille = $this->ouvrirUnPortefeuille($utilisateur);
        $soldeApres = (int) $portefeuille->balance + $montant;

        Transaction::create($this->colonnesValides('transactions', [
            'wallet_id' => $portefeuille->id,
            'amount' => $montant,
            'balance_after' => $soldeApres,
            'type' => $type,
            'related_payment' => $paiement->id,
            'created_at' => $quand->copy()->addHours(2),
            'updated_at' => $quand->copy()->addHours(2),
        ]));

        $portefeuille->update(['balance' => $soldeApres]);
        $this->compteurs['mouvements']++;
    }

    private function creerAvis(User $client, User $technicien, ClientRequest $demande, Carbon $debut): void
    {
        if (Review::where('reviewer_id', $client->id)->where('request_id', $demande->id)->exists()) {
            return;
        }

        $note = $this->alea('note:' . $demande->id, 3, 5);
        $commentaires = [
            3 => 'Travail correct, mais le rendez-vous a pris du retard.',
            4 => 'Intervention propre et efficace, je recommande.',
            5 => 'Très professionnel, ponctuel, et explications claires. Parfait.',
        ];

        Review::create($this->colonnesValides('reviews', [
            'reviewer_id' => $client->id,
            'target_id' => $technicien->id,
            'request_id' => $demande->id,
            'rating' => $note,
            'stage' => 'post',
            'comment' => $commentaires[$note],
            'created_at' => $debut->copy()->addDays(1),
            'updated_at' => $debut->copy()->addDays(1),
        ]));

        $this->compteurs['avis']++;
    }

    // ------------------------------------------------------------- utilitaires

    /**
     * Ne garde que les clés qui existent vraiment dans la table.
     *
     * Plusieurs tables ont été renommées ou complétées par les migrations
     * d'évolution — « subject » est devenu « title », « user_id » est devenu
     * « client_id ». Filtrer ici évite d'avoir à deviner quelle version du
     * schéma est en place, et permet au même semeur de tourner sur les deux.
     */
    private function colonnesValides(string $table, array $attributs): array
    {
        return array_filter(
            $attributs,
            fn ($cle) => Schema::hasColumn($table, $cle),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function afficherBilan(): void
    {
        $this->command->newLine();
        $this->command->info('Jeu de démonstration en place :');
        foreach ($this->compteurs as $quoi => $combien) {
            $this->command->line(sprintf('  %-14s %d', str_replace('_', ' ', $quoi), $combien));
        }
        $this->command->newLine();
        $this->command->line('  Comptes : demo.tech.<categorie><n>@reliance.cm');
        $this->command->line('            demo.client.<categorie><n>@reliance.cm');
        $this->command->line('  Mot de passe commun : ' . self::MOT_DE_PASSE);
        $this->command->line('  Les paiements portent le préfixe « SIM- » : aucun n\'est passé par NotchPay.');
    }
}
