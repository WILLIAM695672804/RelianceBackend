<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Profile\Models\Skill;

class SkillsSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            // Plomberie
            ['slug' => 'plomberie-generale', 'name' => 'Plomberie générale', 'category' => 'Plomberie'],
            ['slug' => 'debouchage-canalisation', 'name' => 'Débouchage canalisation', 'category' => 'Plomberie'],
            ['slug' => 'installation-sanitaire', 'name' => 'Installation sanitaire', 'category' => 'Plomberie'],
            ['slug' => 'reparation-fuite', 'name' => 'Réparation de fuite', 'category' => 'Plomberie'],
            ['slug' => 'chauffe-eau', 'name' => 'Installation/réparation chauffe-eau', 'category' => 'Plomberie'],

            // Électricité
            ['slug' => 'electricite-generale', 'name' => 'Électricité générale', 'category' => 'Électricité'],
            ['slug' => 'installation-electrique', 'name' => 'Installation électrique', 'category' => 'Électricité'],
            ['slug' => 'depannage-electrique', 'name' => 'Dépannage électrique', 'category' => 'Électricité'],
            ['slug' => 'tableau-electrique', 'name' => 'Tableau électrique', 'category' => 'Électricité'],
            ['slug' => 'eclairage', 'name' => 'Éclairage / LED', 'category' => 'Électricité'],

            // Informatique / Électronique
            ['slug' => 'maintenance-informatique', 'name' => 'Maintenance informatique', 'category' => 'Informatique'],
            ['slug' => 'reseau-wifi', 'name' => 'Réseau / Wi-Fi', 'category' => 'Informatique'],
            ['slug' => 'reparation-smartphone', 'name' => 'Réparation smartphone', 'category' => 'Électronique'],
            ['slug' => 'reparation-tv', 'name' => 'Réparation TV / écran', 'category' => 'Électronique'],

            // Sécurité
            ['slug' => 'camera-surveillance', 'name' => 'Caméra de surveillance', 'category' => 'Sécurité'],
            ['slug' => 'alarme-securite', 'name' => 'Système d\'alarme', 'category' => 'Sécurité'],
            ['slug' => 'controle-acces', 'name' => 'Contrôle d\'accès', 'category' => 'Sécurité'],
            ['slug' => 'gps-tracking', 'name' => 'GPS / Tracking véhicule', 'category' => 'Sécurité'],

            // Mécanique
            ['slug' => 'mecanique-auto', 'name' => 'Mécanique automobile', 'category' => 'Mécanique'],
            ['slug' => 'climatisation-auto', 'name' => 'Climatisation automobile', 'category' => 'Mécanique'],
            ['slug' => 'electricite-auto', 'name' => 'Électricité automobile', 'category' => 'Mécanique'],

            // Climatisation / Froid
            ['slug' => 'climatisation', 'name' => 'Climatisation', 'category' => 'Climatisation'],
            ['slug' => 'refrigeration', 'name' => 'Réfrigération', 'category' => 'Climatisation'],
            ['slug' => 'ventilation', 'name' => 'Ventilation / VMC', 'category' => 'Climatisation'],

            // Bâtiment
            ['slug' => 'peinture', 'name' => 'Peinture', 'category' => 'Bâtiment'],
            ['slug' => 'carrelage', 'name' => 'Carrelage', 'category' => 'Bâtiment'],
            ['slug' => 'menuiserie', 'name' => 'Menuiserie', 'category' => 'Bâtiment'],
            ['slug' => 'soudure', 'name' => 'Soudure / Métallerie', 'category' => 'Bâtiment'],
        ];

        foreach ($skills as $skill) {
            Skill::firstOrCreate(['slug' => $skill['slug']], $skill);
        }
    }
}
