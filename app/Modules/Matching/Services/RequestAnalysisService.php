<?php

namespace App\Modules\Matching\Services;

use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\RequestAnalysis;
use App\Modules\Profile\Models\Skill;

class RequestAnalysisService
{
    private array $keywordMap = [
        'plomberie'    => ['fuite', 'robinet', 'tuyau', 'canalisation', 'wc', 'toilette', 'douche', 'lavabo', 'évier', 'chauffe-eau', 'plombier'],
        'electricite'  => ['prise', 'ampoule', 'disjoncteur', 'courant', 'tableau', 'câble', 'éclairage', 'électrique', 'court-circuit', 'panne électrique'],
        'informatique' => ['ordinateur', 'pc', 'wifi', 'réseau', 'imprimante', 'virus', 'lent', 'écran bleu', 'internet'],
        'electronique' => ['téléphone', 'smartphone', 'écran', 'tv', 'télévision', 'batterie', 'charge'],
        'securite'     => ['caméra', 'surveillance', 'alarme', 'gps', 'tracking', 'serrure', 'accès'],
        'mecanique'    => ['voiture', 'moteur', 'frein', 'vidange', 'pneu', 'climatisation auto', 'batterie auto'],
        'climatisation' => ['climatiseur', 'clim', 'froid', 'ventilation', 'chauffage', 'split'],
        'batiment'     => ['peinture', 'carrelage', 'mur', 'plafond', 'porte', 'fenêtre', 'soudure', 'menuiserie'],
    ];

    private array $difficultyKeywords = [
        'simple'   => ['ampoule', 'prise', 'robinet', 'wifi'],
        'moderate' => ['fuite', 'disjoncteur', 'écran', 'climatiseur', 'serrure'],
        'complex'  => ['tableau', 'canalisation', 'moteur', 'réseau', 'caméra', 'soudure'],
    ];

    public function analyze(ClientRequest $clientRequest): RequestAnalysis
    {
        $text = mb_strtolower($clientRequest->title . ' ' . ($clientRequest->description ?? ''));

        $detectedSkills = $this->detectSkills($text);
        $difficulty     = $this->estimateDifficulty($text);
        $calibration    = app(PriceCalibrationService::class);
        $suggestedPrice = $calibration->estimate($difficulty);

        // Auto-set detected_category if not already set
        if (!$clientRequest->detected_category && !empty($detectedSkills)) {
            $firstSkill = Skill::where('slug', $detectedSkills[0])->first();
            if ($firstSkill) {
                $clientRequest->detected_category = $firstSkill->id;
                $clientRequest->status = 'analyzed';
                $clientRequest->save();
            }
        }

        return RequestAnalysis::create([
            'request_id'           => $clientRequest->id,
            'analyzed_at'          => now(),
            'estimated_skills'     => $detectedSkills,
            'estimated_difficulty' => $difficulty,
            'suggested_price'      => $suggestedPrice,
            'payload'              => [
                'method'             => 'keyword_matching_v1',
                'price_model'        => $calibration->deployedVersion() ?? 'default',
                'text_length'        => mb_strlen($text),
                'detected_categories' => $this->detectCategories($text),
            ],
        ]);
    }

    private function detectSkills(string $text): array
    {
        $matched = [];

        foreach ($this->keywordMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $matched[$category] = true;
                    break;
                }
            }
        }

        $categoryToSlug = [
            'plomberie'    => 'plomberie-generale',
            'electricite'  => 'electricite-generale',
            'informatique' => 'maintenance-informatique',
            'electronique' => 'reparation-smartphone',
            'securite'     => 'camera-surveillance',
            'mecanique'    => 'mecanique-auto',
            'climatisation' => 'climatisation',
            'batiment'     => 'peinture',
        ];

        return array_values(array_map(
            fn($cat) => $categoryToSlug[$cat] ?? $cat,
            array_keys($matched)
        ));
    }

    private function detectCategories(string $text): array
    {
        $categories = [];
        foreach ($this->keywordMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $categories[] = $category;
                    break;
                }
            }
        }
        return $categories;
    }

    private function estimateDifficulty(string $text): string
    {
        foreach (['complex', 'moderate', 'simple'] as $level) {
            foreach ($this->difficultyKeywords[$level] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $level;
                }
            }
        }
        return 'moderate';
    }
}
