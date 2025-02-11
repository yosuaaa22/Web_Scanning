<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIRecommendationService
{
    private const RISK_WEIGHTS = [
        'Kritis' => 5,
        'Tinggi' => 4,
        'Sedang' => 3,
        'Rendah' => 2,
        'Minimal' => 1
    ];


    public function generateRecommendation($backdoorResult, $gamblingResult, $url)
    {
        try {
            // Validasi input
            if (!is_array($backdoorResult) || !is_array($gamblingResult)) {
                throw new \InvalidArgumentException('Invalid detection results format');
            }

            // Extract risk levels and confidence scores
            $analysis = $this->analyzeResults($backdoorResult, $gamblingResult);

            // Generate comprehensive recommendations
            $recommendations = $this->generateComprehensiveRecommendations($analysis, $backdoorResult, $gamblingResult);

            // Format final response
            return $this->formatRecommendations($recommendations, $analysis);
        } catch (\Exception $e) {
            Log::error('AI Recommendation Service error', [
                'error' => $e->getMessage(),
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menganalisis hasil deteksi.',
                'error' => $e->getMessage(),
                'recommendations' => [
                    "⚠️ PERINGATAN: Terjadi kesalahan dalam analisis. Mohon:
                    - Periksa kembali URL yang diberikan
                    - Pastikan situs dapat diakses
                    - Coba scan ulang dalam beberapa saat"
                ]
            ];
        }
    }

    private function analyzeResults($backdoorResult, $gamblingResult)
    {
        $analysis = [
            'backdoor' => [
                'risk_level' => $backdoorResult['risk_level'] ?? 'Rendah',
                'risk_score' => $backdoorResult['risk_score'] ?? 0,
                'confidence' => $backdoorResult['confidence_level'] ?? 0,
                'detected' => $backdoorResult['detected'] ?? false
            ],
            'gambling' => [
                'risk_level' => $gamblingResult['risk_level'] ?? 'Rendah',
                'risk_score' => $gamblingResult['risk_score'] ?? 0,
                'confidence' => $gamblingResult['confidence_score'] ?? 0,
                'detected' => $gamblingResult['detected'] ?? false
            ]
        ];

        // Calculate combined risk score
        $analysis['combined_risk_score'] = $this->calculateCombinedRiskScore($analysis);
        $analysis['overall_risk_level'] = $this->determineOverallRiskLevel($analysis['combined_risk_score']);

        return $analysis;
    }

    private function calculateCombinedRiskScore($analysis)
    {
        $backdoorWeight = self::RISK_WEIGHTS[$analysis['backdoor']['risk_level']] ?? 1;
        $gamblingWeight = self::RISK_WEIGHTS[$analysis['gambling']['risk_level']] ?? 1;

        $backdoorScore = ($analysis['backdoor']['risk_score'] * $backdoorWeight * ($analysis['backdoor']['confidence'] / 100));
        $gamblingScore = ($analysis['gambling']['risk_score'] * $gamblingWeight * ($analysis['gambling']['confidence'] / 100));

        return round(($backdoorScore + $gamblingScore) / 2);
    }

    private function determineOverallRiskLevel($combinedScore)
    {
        if ($combinedScore >= 80) return 'Kritis';
        if ($combinedScore >= 60) return 'Tinggi';
        if ($combinedScore >= 40) return 'Sedang';
        if ($combinedScore >= 20) return 'Rendah';
        return 'Minimal';
    }

    private function generateComprehensiveRecommendations($analysis, $backdoorResult, $gamblingResult)
    {
        $recommendations = [];

        // Analisis Backdoor
        if ($analysis['backdoor']['detected']) {
            $recommendations = array_merge(
                $recommendations,
                $this->generateBackdoorRecommendations($backdoorResult)
            );
        }

        // Analisis Gambling
        if ($analysis['gambling']['detected']) {
            $recommendations = array_merge(
                $recommendations,
                $this->generateGamblingRecommendations($gamblingResult)
            );
        }

        // Rekomendasi umum jika risiko rendah
        if (empty($recommendations)) {
            $recommendations[] = $this->generateLowRiskRecommendations();
        }

        // Tambahkan rekomendasi prioritas berdasarkan tingkat risiko
        $recommendations = $this->addPriorityRecommendations($recommendations, $analysis);

        return $recommendations;
    }

    private function generateBackdoorRecommendations($backdoorResult)
    {
        $recommendations = [];
        $details = $backdoorResult['details'] ?? [];

        // Process potential RCE vulnerabilities
        if (!empty($details['risks']['potential_rce'])) {
            $recommendations[] = "🚨 CRITICAL: Terdeteksi potensi Remote Code Execution (RCE):
            - Segera nonaktifkan fungsi berbahaya (eval, system, exec)
            - Terapkan whitelist untuk input yang diizinkan
            - Implementasikan WAF (Web Application Firewall)
            - Lakukan audit keamanan menyeluruh";
        }

        // Process file manipulation risks
        if (!empty($details['risks']['file_manipulation'])) {
            $recommendations[] = "⚠️ PERINGATAN: Terdeteksi manipulasi file mencurigakan:
            - Periksa dan batasi permission file/direktori
            - Implementasikan validasi file yang ketat
            - Monitor perubahan file secara real-time
            - Backup data secara regular";
        }

        // Process obfuscation detection
        if (!empty($details['risks']['obfuscation'])) {
            $recommendations[] = "🔍 Terdeteksi kode terenkripsi/obfuscated:
            - Analisis dan dekode konten mencurigakan
            - Implementasikan deteksi malware
            - Perbarui sistem keamanan secara berkala";
        }

        return $recommendations;
    }

    private function generateGamblingRecommendations($gamblingResult)
    {
        $recommendations = [];
        $analysis = $gamblingResult['analysis'] ?? [];

        // Process content-based risks
        if (!empty($analysis['content'])) {
            $recommendations[] = "🎲 Terdeteksi konten perjudian:
            - Blokir akses ke domain terkait
            - Implementasikan filter konten
            - Dokumentasikan temuan untuk pelaporan
            - Terapkan kebijakan penggunaan yang sesuai";
        }

        // Process technical risks
        if (!empty($analysis['technical'])) {
            $recommendations[] = "🔒 Terdeteksi elemen teknis mencurigakan:
            - Periksa dan hapus script mencurigakan
            - Monitor traffic mencurigakan
            - Implementasikan pembatasan akses
            - Perbarui mekanisme keamanan";
        }

        return $recommendations;
    }

    private function generateLowRiskRecommendations()
    {
        return "✅ Risiko terdeteksi minimal. Rekomendasi umum:
        - Pertahankan monitoring keamanan
        - Perbarui sistem secara berkala
        - Backup data secara teratur
        - Terapkan best practice keamanan";
    }

    private function addPriorityRecommendations($recommendations, $analysis)
    {
        if ($analysis['overall_risk_level'] === 'Kritis' || $analysis['overall_risk_level'] === 'Tinggi') {
            array_unshift($recommendations, "🚨 PRIORITAS TINGGI: Diperlukan tindakan segera:
            - Isolasi sistem yang terinfeksi
            - Backup data penting
            - Konsultasi dengan tim keamanan
            - Dokumentasi semua tindakan");
        }

        return $recommendations;
    }

    private function formatRecommendations($recommendations, $analysis)
    {
        return [
            'status' => 'success',
            'risk_summary' => [
                'overall_risk_level' => $analysis['overall_risk_level'],
                'combined_risk_score' => $analysis['combined_risk_score'],
                'backdoor_details' => [
                    'risk_level' => $analysis['backdoor']['risk_level'],
                    'confidence' => $analysis['backdoor']['confidence']
                ],
                'gambling_details' => [
                    'risk_level' => $analysis['gambling']['risk_level'],
                    'confidence' => $analysis['gambling']['confidence']
                ]
            ],
            'recommendations' => $recommendations,
            'timestamp' => now()->toIso8601String()
        ];
    }
}
