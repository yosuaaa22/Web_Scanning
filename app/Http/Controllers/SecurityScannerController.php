<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BackdoorDetectionService;
use App\Services\GamblingDetectionService;
use App\Services\AIRecommendationService;
use App\Services\EnhancedDetectionService;
use App\Models\ScanResult;
use Illuminate\Support\Facades\Log;

class SecurityScannerController extends Controller
{
    protected $backdoorService;
    protected $gamblingService;
    protected $aiRecommendationService;
    protected $enhancedService;

    public function __construct(
        BackdoorDetectionService $backdoorService,
        GamblingDetectionService $gamblingService,
        AIRecommendationService $aiRecommendationService,
        EnhancedDetectionService $enhancedService
    ) {
        $this->backdoorService = $backdoorService;
        $this->gamblingService = $gamblingService;
        $this->aiRecommendationService = $aiRecommendationService;
        $this->enhancedService = $enhancedService;
    }

    public function index()
    {
        return view('scanner.index');
    }

    public function scan(Request $request)
    {
        try {
            $url = $request->input('url');

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return back()->with('error', 'URL tidak valid');
            }

            // Backdoor detection
            try {
                $backdoorResult = $this->backdoorService->detect($url);
            } catch (\Exception $e) {
                Log::error('Backdoor detection error: ' . $e->getMessage());
                $backdoorResult = [
                    'detected' => false,
                    'risk_level' => 'Error',
                    'error' => $e->getMessage()
                ];
            }

            // Gambling detection
            try {
                $gamblingResult = $this->gamblingService->detect($url);
            } catch (\Exception $e) {
                Log::error('Gambling detection error: ' . $e->getMessage());
                $gamblingResult = [
                    'detected' => false,
                    'risk_level' => 'Error',
                    'error' => $e->getMessage()
                ];
            }

            // Enhanced analysis
            try {
                $enhancedAnalysis = $this->enhancedService->analyze($url);
            } catch (\Exception $e) {
                Log::error('Enhanced analysis error: ' . $e->getMessage());
                $enhancedAnalysis = [
                    'network_analysis' => ['suspicious_urls' => []],
                    'hidden_elements' => ['css_hidden' => []],
                    'js_analysis' => ['suspicious_patterns' => []],
                    'redirect_analysis' => ['suspicious_redirects' => []],
                    'registration_analysis' => [
                        'required_fields' => [],
                        'suspicious_elements' => []
                    ]
                ];
            }

            // Save scan result
            $scanResult = new ScanResult([
                'url' => $url,
                'backdoor_risk' => $backdoorResult['risk_level'] ?? 'Error',
                'gambling_risk' => $gamblingResult['risk_level'] ?? 'Error',
                'scan_time' => now(),
                'detailed_report' => json_encode([
                    'backdoor_details' => $backdoorResult,
                    'gambling_details' => $gamblingResult,
                    'enhanced_analysis' => $enhancedAnalysis
                ])
            ]);
            $scanResult->save();

            // Generate AI recommendation
            try {
                $aiRecommendation = $this->aiRecommendationService->generateRecommendation(
                    $backdoorResult,
                    $gamblingResult,
                    $url
                );
            } catch (\Exception $e) {
                Log::error('AI Recommendation error: ' . $e->getMessage());
                $aiRecommendation = [
                    'recommendations' => [
                        '⚠️ Tidak dapat menghasilkan rekomendasi karena terjadi error pada analisis.'
                    ]
                ];
            }

            return view('scanner.result', [
                'scanResult' => $scanResult,
                'backdoorResult' => $backdoorResult,
                'gamblingResult' => $gamblingResult,
                'aiRecommendation' => $aiRecommendation,
                'networkAnalysis' => $enhancedAnalysis['network_analysis'],
                'hiddenElements' => $enhancedAnalysis['hidden_elements'],
                'jsAnalysis' => $enhancedAnalysis['js_analysis'],
                'redirectAnalysis' => $enhancedAnalysis['redirect_analysis'],
                'registrationAnalysis' => $enhancedAnalysis['registration_analysis']
            ]);

        } catch (\Exception $e) {
            Log::error('Scanning Error', [
                'message' => $e->getMessage(),
                'url' => $url ?? 'not set'
            ]);

            return back()->with('error', 'Terjadi kesalahan saat melakukan scanning: ' . $e->getMessage());
        }
    }

    public function history()
    {
        $scanResults = ScanResult::orderByDesc('created_at')
            ->take(50)
            ->get();

        return view('scanner.history', compact('scanResults'));
    }
}
