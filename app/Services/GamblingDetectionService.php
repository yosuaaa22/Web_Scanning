<?php
namespace App\Services;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class GamblingDetectionService
{
    private $client;
    private const CACHE_TTL = 3600; // 1 hour cache


    // Expanded pattern categories with more comprehensive coverage
    private $advancedGamblingPatterns = [
        'financial_keywords' => [
            '/deposit|withdraw|balance|transaction|e-wallet|dana|ovo|gopay|linkaja|pulsa/i',
            '/minimal\s*deposit|max\s*withdraw|instant\s*payment/i',
            '/bank\s*(transfer|bca|bni|bri|mandiri|cimb)/i'
        ],
        'betting_patterns' => [
            '/\d+x\s*bet|win\s*rate|bonus\s*\d+%/i',
            '/odds\s*\d+|\d+:\d+|handicap|parlay/i',
            '/free\s*spin|jackpot|mega\s*win|big\s*win/i',
            '/rtp\s*\d+%|live\s*casino|slot\s*gacor/i'
        ],
        'hidden_indicators' => [
            '/display:\s*none|opacity:\s*0|visibility:\s*hidden/i',
            '/position:\s*absolute;\s*left:\s*-\d+px/i',
            '/transform:\s*translateX\(-\d+px\)/i',
            '/clip:\s*rect\(0,\s*0,\s*0,\s*0\)/i'
        ],
        'suspicious_structures' => [
            '/link\s*alternatif|link\s*terbaru|situs\s*resmi/i',
            '/daftar\s*sekarang|main\s*sekarang|join\s*now/i',
            '/wa\s*\+\d+|telegram\s*[@\w]+|line\s*id/i',
            '/\b(?:cs|customer\s*service)\s*24\s*jam\b/i'
        ],
        'promo_patterns' => [
            '/bonus\s*new\s*member|bonus\s*deposit|cashback/i',
            '/welcome\s*bonus|rollingan|turnover/i',
            '/promosi\s*menarik|bonus\s*mingguan|event\s*deposit/i'
        ]
    ];

    // Known gambling-related domains and their variations
    private $knownGamblingSites = [
        'domain_patterns' => [
            '/(slot|judi|casino|poker|betting|togel|bola)\d*\./i',
            '/(qq|agen|bandar|raja|situs|dewa)\d*\./i',
            '/\d{2,3}\.\d{2,3}\.\d{2,3}\.\d{2,3}/', // IP address pattern
            '/.+\.(fun|top|vip|xyz|win|bet)$/i'
        ],
        'tld_patterns' => [
            '/\.(fun|top|vip|xyz|win|bet)$/i'
        ]
    ];



    public function __construct(Client $client)
    {
        $this->client = $client;
    }


    public function detect($url)
    {
        $cacheKey = 'gambling_detection_' . md5($url);

        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Configure client with better error handling
            $response = $this->client->request('GET', $url, [
                'timeout' => 10,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ],
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true
                ]
            ]);

            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            // Comprehensive analysis
            $result = $this->performComprehensiveAnalysis($crawler, $html, $url);

            // Cache the result
            Cache::put($cacheKey, $result, self::CACHE_TTL);

            return $result;

        } catch (\Exception $e) {
            Log::error('Gambling detection error: ' . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'detected' => false,
                'risk_level' => 'Error',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ];
        }
    }

    private function performComprehensiveAnalysis(Crawler $crawler, $html, $url): array
    {
        // Log awal scanning
        Log::info('Gambling Detection Started', [
            'url' => $url,
            'timestamp' => now()->toIso8601String()
        ]);

        // Initialize scoring system
        $scoreSystem = [
            'content_score' => 0,
            'technical_score' => 0,
            'behavioral_score' => 0
        ];

        // Content Analysis
        $contentAnalysis = $this->analyzeContent($html, $crawler);
        $scoreSystem['content_score'] += $this->calculateContentScore($contentAnalysis);

        // Log detail analisis konten
        Log::debug('Content Analysis Details', [
            'gambling_keywords' => $contentAnalysis['gambling_keywords'],
            'suspicious_domains' => $contentAnalysis['suspicious_domains'],
            'betting_terms' => $contentAnalysis['betting_analysis'],
            'content_score' => $scoreSystem['content_score']
        ]);

        // Technical Analysis
        $technicalAnalysis = $this->analyzeTechnicalElements($crawler, $html, $url);
        $scoreSystem['technical_score'] += $this->calculateTechnicalScore($technicalAnalysis);

        // Log detail analisis teknis
        Log::debug('Technical Analysis Details', [
            'hidden_elements' => $technicalAnalysis['hidden_elements'],
            'js_analysis' => $technicalAnalysis['js_analysis'],
            'technical_score' => $scoreSystem['technical_score']
        ]);

        // Behavioral Analysis
        $behavioralAnalysis = $this->analyzeBehavioralPatterns($crawler, $html);
        $timeSensitiveAnalysis = $this->analyzeTimeSensitiveElements($html);
        $scoreSystem['behavioral_score'] += $this->calculateBehavioralScore(
            array_merge($behavioralAnalysis, ['time_sensitive' => $timeSensitiveAnalysis])
        );

        // Log detail analisis perilaku
        Log::debug('Behavioral Analysis Details', [
            'payment_patterns' => $behavioralAnalysis['payment_patterns'],
            'user_interaction' => $behavioralAnalysis['user_interaction'],
            'time_sensitive' => $timeSensitiveAnalysis,
            'behavioral_score' => $scoreSystem['behavioral_score']
        ]);

        // Calculate final risk assessment
        $riskAssessment = $this->calculateFinalRisk($scoreSystem);

        // Log final risk assessment
        Log::info('Gambling Detection Result', [
            'detected' => $riskAssessment['is_gambling'],
            'risk_level' => $riskAssessment['risk_level'],
            'confidence_score' => $riskAssessment['confidence']
        ]);

        return [
            'detected' => $riskAssessment['is_gambling'],
            'risk_level' => $riskAssessment['risk_level'],
            'confidence_score' => $riskAssessment['confidence'],
            'analysis' => [
                'content' => $contentAnalysis,
                'technical' => $technicalAnalysis,
                'behavioral' => $behavioralAnalysis,
                'scores' => $scoreSystem
            ],
            'timestamp' => now(),
            'metadata' => [
                'url' => $url,
                'scan_date' => now()->toIso8601String(),
                'version' => '2.0.0'
            ]
        ];
    }

   // Perlu menambahkan pemanggilan di method analyzeContent()
private function analyzeContent($html, Crawler $crawler)
{
    $contentAnalysis = [
        'gambling_keywords' => $this->detectGamblingKeywords($html),
        'suspicious_domains' => $this->checkSuspiciousDomains($crawler),
        'financial_indicators' => $this->analyzeFinancialContent($html),
        'betting_analysis' => $this->analyzeBettingTerms($html), // Gunakan method baru
        'promo_analysis' => $this->analyzePromotionalContent($html),
        'text_sentiment' => $this->analyzeSentiment($crawler)
    ];

    $contentAnalysis['meta_analysis'] = $this->analyzeMetaInformation($crawler);

    return $contentAnalysis;
}

    private function detectGamblingKeywords($html)
    {
        $analysis = [
            'direct_gambling_terms' => [],
            'game_related_terms' => [],
            'betting_terms' => [],
            'casino_terms' => [],
            'risk_score' => 0
        ];

        // Kata kunci perjudian langsung
        $directGamblingPatterns = [
            'basic' => [
                '/\b(?:judi|gambling|taruhan|betting)\b/i',
                '/\b(?:bandar|agen|bookie|dealer)\b/i',
                '/\b(?:togel|toto|lottery|lotto)\b/i'
            ],
            'variations' => [
                '/j\s*u\s*d\s*i/i', // Deteksi kata yang dipisah
                '/j[u4]d[1i]/i',    // Deteksi variasi numerik
                '/t[0o]g[e3]l/i',   // Deteksi variasi leet speak
                '/b[e3]tt[1i]ng/i'
            ]
        ];

        // Istilah terkait game
        $gamePatterns = [
            'slot' => [
                '/\b(?:slot|slots)\b/i',
                '/slot\s*(?:online|gacor|maxwin)/i',
                '/game\s*slot/i',
                '/\b(?:pragmatic|habanero|pgsoft)\b/i'
            ],
            'poker' => [
                '/\b(?:poker|pokerv|pokergame)\b/i',
                '/texas\s*holdem/i',
                '/domino\s*(?:qq|99|kiu)/i',
                '/\b(?:capsa|sakong|bandarq)\b/i'
            ],
            'casino' => [
                '/\b(?:casino|kasino)\b/i',
                '/live\s*casino/i',
                '/\b(?:roulette|baccarat|blackjack)\b/i',
                '/sicbo|dragon\s*tiger/i'
            ]
        ];

        // Istilah taruhan
        $bettingPatterns = [
            'sports' => [
                '/sportsbook/i',
                '/\b(?:bola|soccer|football)\s*(?:online|betting)\b/i',
                '/pasaran\s*(?:bola|togel)/i',
                '/odds|handicap|parlay/i'
            ],
            'odds' => [
                '/\d+\s*:\s*\d+/i',  // Format odds
                '/hadiah\s*\d+x/i',   // Multiplier rewards
                '/kei\s*\d+%/i',      // Kei percentage
                '/diskon\s*\d+%/i'    // Discount percentage
            ]
        ];

        // Cek setiap kategori pola
        foreach ($directGamblingPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $analysis['direct_gambling_terms'] = array_merge(
                        $analysis['direct_gambling_terms'],
                        array_unique($matches[0])
                    );
                    $analysis['risk_score'] += 5; // Bobot tinggi untuk istilah langsung
                }
            }
        }

        foreach ($gamePatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $analysis['game_related_terms'] = array_merge(
                        $analysis['game_related_terms'],
                        array_unique($matches[0])
                    );
                    $analysis['risk_score'] += 3;
                }
            }
        }

        foreach ($bettingPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $analysis['betting_terms'] = array_merge(
                        $analysis['betting_terms'],
                        array_unique($matches[0])
                    );
                    $analysis['risk_score'] += 4;
                }
            }
        }

        // Deteksi frasa kontekstual
        $contextualPhrases = [
            '/minimal\s*deposit/i',
            '/withdraw\s*(?:cepat|instant)/i',
            '/customer\s*service\s*24\s*jam/i',
            '/link\s*alternatif/i',
            '/situs\s*terpercaya/i',
            '/daftar\s*sekarang/i'
        ];

        foreach ($contextualPhrases as $phrase) {
            if (preg_match_all($phrase, $html, $matches)) {
                $analysis['casino_terms'] = array_merge(
                    $analysis['casino_terms'],
                    array_unique($matches[0])
                );
                $analysis['risk_score'] += 2;
            }
        }

        return $analysis;
    }

    private function checkSuspiciousDomains(Crawler $crawler)
    {
        $analysis = [
            'suspicious_domains' => [],
            'redirect_domains' => [],
            'affiliate_domains' => [],
            'risk_patterns' => [],
            'risk_score' => 0
        ];

        // Whitelist domain pemerintah dan umum
        $whitelistDomains = [
            'go.id',
            'lkpp.go.id',
            'google.com',
            'play.google.com',
            'facebook.com',
            'twitter.com',
            'instagram.com'
        ];

        // Pattern untuk domain mencurigakan
        $domainPatterns = [
            'gambling' => [
                '/(?:judi|betting|casino|poker|togel|slot)\d*\./',
                '/(?:qq|kiu|domino|capsa|sakong)\d*\./',
                '/(?:bola|sport|games|win)\d*\./',
                '/-(?:judi|bet|casino|poker|togel|slot)-/'
            ],
            'affiliate' => [
                '/(?:affiliate|partner|ref|link)\d*\./',
                '/(?:alternatif|mirror|login|daftar)\d*\./',
                '/(?:bonus|promo|vip|royal)\d*\./'
            ],
            'masking' => [
                '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', // IP address
                '/(?:xyz|top|vip|fun|win|bet)$/',        // Suspicious TLDs
                '/[a-z0-9]{20,}\./',                     // Random long strings
                '/(?:[0-9]{8,}|[a-z]{15,})\./'          // Unusual patterns
            ]
        ];

        // Ekstrak dan analisis semua link
        $crawler->filter('a')->each(function ($node) use (&$analysis, $domainPatterns, $whitelistDomains) {
            $href = $node->attr('href');
            if (!$href) return;

            // Parse URL dan dapatkan domain
            $parsedUrl = parse_url($href);
            if (!isset($parsedUrl['host'])) return;

            $domain = strtolower($parsedUrl['host']);

            // Cek whitelist domain
            foreach ($whitelistDomains as $whitelistDomain) {
                if (strpos($domain, $whitelistDomain) !== false) {
                    return; // Lewati domain yang ada di whitelist
                }
            }

            // Cek pattern domain mencurigakan
            $isSuspicious = false;
            foreach ($domainPatterns as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $domain)) {
                        $analysis['suspicious_domains'][] = [
                            'domain' => $domain,
                            'full_url' => $href,
                            'type' => $type,
                            'pattern_matched' => $pattern
                        ];
                        $analysis['risk_patterns'][] = $pattern;
                        $analysis['risk_score'] += 3;
                        $isSuspicious = true;
                        break 2;
                    }
                }
            }

            // Jika tidak mencurigakan, lanjutkan dengan parameter
            if (!$isSuspicious) {
                // Cek redirect dan affiliate parameters
                if (isset($parsedUrl['query'])) {
                    parse_str($parsedUrl['query'], $params);
                    $suspiciousParams = [
                        'ref', 'referral', 'affiliate', 'partner', 'bonus', 'promo',
                        'source', 'campaign', 'track', 'id', 'sub'
                    ];

                    foreach ($suspiciousParams as $param) {
                        if (array_key_exists($param, $params)) {
                            $analysis['affiliate_domains'][] = [
                                'domain' => $domain,
                                'parameter' => $param,
                                'value' => $params[$param]
                            ];
                            $analysis['risk_score'] += 1;
                        }
                    }
                }

                // Cek redirect links
                if (strpos($href, 'redirect') !== false ||
                    strpos($href, 'go') !== false ||
                    strpos($href, 'link') !== false) {
                    $analysis['redirect_domains'][] = [
                        'domain' => $domain,
                        'full_url' => $href
                    ];
                    $analysis['risk_score'] += 2;
                }
            }
        });

        // Deduplikasi hasil dengan cara yang lebih efisien
        $analysis['suspicious_domains'] = array_map('unserialize', array_unique(array_map('serialize', $analysis['suspicious_domains'])));
        $analysis['redirect_domains'] = array_map('unserialize', array_unique(array_map('serialize', $analysis['redirect_domains'])));
        $analysis['affiliate_domains'] = array_map('unserialize', array_unique(array_map('serialize', $analysis['affiliate_domains'])));
        $analysis['risk_patterns'] = array_unique($analysis['risk_patterns']);

        // Batasi risiko maksimum
        $analysis['risk_score'] = min($analysis['risk_score'], 50);

        return $analysis;
    }
    private function analyzeSentiment(Crawler $crawler)
    {
        $analysis = [
            'gambling_related_content' => [],
            'urgency_indicators' => [],
            'trust_signals' => [],
            'risk_score' => 0
        ];

        // Analisis konten terkait perjudian
        $gamblingTerms = [
            '/(?:daftar|main|join)\s*sekarang/i',
            '/menang\s*(?:besar|jutaan|banyak)/i',
            '/jackpot\s*(?:terbesar|maxwin)/i',
            '/slot\s*(?:gacor|terpercaya|resmi)/i'
        ];

        $textContent = $crawler->filter('body')->text();

        foreach ($gamblingTerms as $pattern) {
            if (preg_match_all($pattern, $textContent, $matches)) {
                $analysis['gambling_related_content'] = array_merge(
                    $analysis['gambling_related_content'],
                    array_unique($matches[0])
                );
                $analysis['risk_score'] += 3;
            }
        }

        // Analisis indikator urgensi
        $urgencyPatterns = [
            '/jangan\s*sampai\s*ketinggalan/i',
            '/buruan\s*daftar/i',
            '/kesempatan\s*terbatas/i',
            '/(?:hanya|tersisa)\s*(?:untuk|sampai)/i'
        ];

        foreach ($urgencyPatterns as $pattern) {
            if (preg_match_all($pattern, $textContent, $matches)) {
                $analysis['urgency_indicators'] = array_merge(
                    $analysis['urgency_indicators'],
                    array_unique($matches[0])
                );
                $analysis['risk_score'] += 2;
            }
        }

        // Analisis sinyal kepercayaan
        $trustPatterns = [
            '/terpercaya|resmi|official/i',
            '/(?:dijamin|guarantee)\s*(?:aman|pasti)/i',
            '/support\s*24\s*jam/i',
            '/customer\s*service\s*online/i'
        ];

        foreach ($trustPatterns as $pattern) {
            if (preg_match_all($pattern, $textContent, $matches)) {
                $analysis['trust_signals'] = array_merge(
                    $analysis['trust_signals'],
                    array_unique($matches[0])
                );
                $analysis['risk_score'] += 2;
            }
        }

        return $analysis;
    }

    private function analyzeFinancialContent($html)
{
    $analysis = [
        'payment_methods' => [],
        'transaction_patterns' => [],
        'currency_indicators' => [],
        'crypto_analysis' => [], // Akan diisi oleh verifikasiCrypto
        'risk_score' => 0
    ];

    // Deteksi metode pembayaran dengan pattern yang ditingkatkan
    $paymentPatterns = [
        'bank_transfer' => [
            '/bank\s*(transfer|bca|bni|bri|mandiri|cimb|danamon|permata)/i',
            '/virtual\s*account|va\s*number/i',
            '/rekening\s*(bank|transfer|deposit)/i',
            '/(?:rek|norek|no\.?\s*rek)\s*\d{10,16}/i' // Nomor rekening pattern
        ],
        'e_wallet' => [
            '/(ovo|gopay|dana|linkaja|shopeepay|sakuku)/i',
            '/e[-\s]wallet|dompet\s*digital/i',
            '/qris|quick\s*response\s*code/i',
            '/scan\s*(?:untuk|to)\s*(?:bayar|pay)/i'
        ],
        'crypto' => [
            '/(bitcoin|ethereum|usdt|crypto|bnb|dogecoin)/i',
            '/blockchain|wallet\s*address/i',
            '/(?:btc|eth|usdt|doge|bnb)\s*address/i',
            // Bitcoin address patterns
            '/([13]|bc1)[A-HJ-NP-Za-km-z1-9]{25,39}/i',
            // Ethereum address pattern
            '/0x[a-fA-F0-9]{40}/i',
            // USDT TRC20 address pattern
            '/T[A-Za-z1-9]{33}/i'
        ],
        'pulsa' => [
            '/pulsa|telkomsel|xl|indosat|tri|smartfren/i',
            '/top[-\s]?up|isi\s*ulang/i',
            '/nomor\s*(?:hp|telepon|seluler)/i'
        ]
    ];

    foreach ($paymentPatterns as $type => $patterns) {
        $analysis['payment_methods'][$type] = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $analysis['payment_methods'][$type] = array_merge(
                    $analysis['payment_methods'][$type],
                    array_unique($matches[0])
                );

                // Bobot risiko berbeda untuk setiap tipe pembayaran
                $riskWeights = [
                    'bank_transfer' => 2,
                    'e_wallet' => 2,
                    'crypto' => 4, // Crypto memiliki bobot lebih tinggi
                    'pulsa' => 1
                ];

                $analysis['risk_score'] += $riskWeights[$type] ?? 2;
            }
        }
    }

    // Deteksi pola transaksi mencurigakan yang diperluas
    $transactionPatterns = [
        // Deposit patterns
        '/min(?:imal|imum)?\s*deposit\s*\d+[k\s]*/i',
        '/deposit\s*\d+\s*(?:ribu|rb|k|juta|jt|m)\b/i',
        '/deposit\s*(?:pertama|awal)\s*\d+[k\s]*/i',
        '/setor\s*dana\s*\d+[k\s]*/i',

        // Withdraw patterns
        '/max(?:imal|imum)?\s*withdraw\s*\d+[k\s]*/i',
        '/withdraw\s*\d+\s*(?:ribu|rb|k|juta|jt|m)\b/i',
        '/penarikan\s*(?:dana|uang)\s*\d+[k\s]*/i',
        '/tarik\s*dana\s*\d+[k\s]*/i',

        // Processing time patterns
        '/proses?\s*\d+\s*menit/i',
        '/instant\s*withdrawal|withdraw\s*instan/i',
        '/proses\s*(?:cepat|kilat|express)/i',
        '/\d+\s*menit\s*proses/i'
    ];

    foreach ($transactionPatterns as $pattern) {
        if (preg_match_all($pattern, $html, $matches)) {
            $analysis['transaction_patterns'] = array_merge(
                $analysis['transaction_patterns'],
                array_unique($matches[0])
            );

            // Tingkatkan risk score berdasarkan pola yang ditemukan
            if (strpos(strtolower($pattern), 'deposit') !== false) {
                $analysis['risk_score'] += 3;
            } elseif (strpos(strtolower($pattern), 'withdraw') !== false) {
                $analysis['risk_score'] += 3;
            } else {
                $analysis['risk_score'] += 2;
            }
        }
    }

    // Deteksi indikator mata uang yang diperluas
    $currencyPatterns = [
        // Rupiah patterns
        '/rp\s*\d+[\d.,]*\s*(?:ribu|rb|k|juta|jt|m|miliar|b)\b/i',
        '/idr\s*\d+[\d.,]*\s*(?:k|m|b)\b/i',
        '/\d+[\d.,]*\s*(?:ribu|rb|k|juta|jt|m|miliar|b)\s*rupiah\b/i',

        // Dollar patterns
        '/\$\s*\d+[\d.,]*\s*(?:k|m|b)\b/i',
        '/usd\s*\d+[\d.,]*\s*(?:k|m|b)\b/i',

        // Crypto currency patterns
        '/(?:btc|eth|usdt)\s*\d+(?:\.\d+)?/i',
        '/\d+(?:\.\d+)?\s*(?:bitcoin|ethereum|tether)/i'
    ];

    foreach ($currencyPatterns as $pattern) {
        if (preg_match_all($pattern, $html, $matches)) {
            $analysis['currency_indicators'] = array_merge(
                $analysis['currency_indicators'],
                array_unique($matches[0])
            );

            // Tingkatkan risk score berdasarkan tipe mata uang
            if (preg_match('/(btc|eth|usdt|bitcoin|ethereum|tether)/i', $pattern)) {
                $analysis['risk_score'] += 2; // Skor lebih tinggi untuk crypto
            } else {
                $analysis['risk_score'] += 1;
            }
        }
    }

    // Tambahkan analisis crypto yang lebih mendalam
    $cryptoAnalysis = $this->verifyCryptoTransactions($html);
    $analysis['crypto_analysis'] = $cryptoAnalysis;
    $analysis['risk_score'] += $cryptoAnalysis['risk_score'];

    return $analysis;
}

    private function analyzePromotionalContent($html)
    {
        $analysis = [
            'bonus_offers' => [],
            'promotional_terms' => [],
            'time_limited_offers' => [],
            'risk_score' => 0
        ];

        // Deteksi penawaran bonus
        $bonusPatterns = [
            'welcome_bonus' => [
                '/bonus\s*new\s*member/i',
                '/welcome\s*bonus/i',
                '/bonus\s*deposit\s*pertama/i'
            ],
            'deposit_bonus' => [
                '/bonus\s*deposit\s*\d+%/i',
                '/deposit\s*bonus\s*harian/i',
                '/bonus\s*reload/i'
            ],
            'cashback' => [
                '/cashback\s*\d+%/i',
                '/rollingan\s*\d+%/i',
                '/turnover\s*bonus/i'
            ],
            'referral' => [
                '/bonus\s*referr?al/i',
                '/komisi\s*referr?al/i',
                '/affiliate\s*bonus/i'
            ]
        ];

        foreach ($bonusPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $analysis['bonus_offers'][$type] = array_merge(
                        $analysis['bonus_offers'][$type] ?? [],
                        array_unique($matches[0])
                    );
                    $analysis['risk_score'] += 2;
                }
            }
        }

        // Deteksi istilah promosi
        $promotionalTerms = [
            '/bonus\s*mingguan|bonus\s*bulanan/i',
            '/event\s*(?:deposit|withdraw)/i',
            '/hadiah\s*(?:langsung|menarik)/i',
            '/promosi\s*(?:menarik|terbaru|terbesar)/i',
            '/bonus\s*(?:member|vip|loyal)/i'
        ];

        foreach ($promotionalTerms as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $analysis['promotional_terms'] = array_merge(
                    $analysis['promotional_terms'],
                    array_unique($matches[0])
                );
                $analysis['risk_score'] += 1;
            }
        }

        // Deteksi penawaran terbatas waktu
        $timeLimitedPatterns = [
            '/promo\s*terbatas/i',
            '/periode\s*\d+\s*-\s*\d+/i',
            '/(?:hanya|berlaku)\s*(?:sampai|hingga)/i',
            '/limited\s*time\s*offer/i',
            '/promo\s*(?:spesial|khusus)/i'
        ];

        foreach ($timeLimitedPatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $analysis['time_limited_offers'] = array_merge(
                    $analysis['time_limited_offers'],
                    array_unique($matches[0])
                );
                $analysis['risk_score'] += 2;
            }
        }

        return $analysis;
    }

    private function analyzeTechnicalElements(Crawler $crawler, $html, $url)
    {
        return [
            'hidden_elements' => $this->findHiddenElements($crawler),
            'js_analysis' => $this->analyzeJavaScript($html),
            'link_structure' => $this->analyzeLinkStructure($crawler),
            'domain_analysis' => $this->analyzeDomain($url),
            'redirect_chains' => $this->analyzeRedirects($url)
        ];
    }

    private function analyzeRedirects($url)
    {
        $analysis = [
            'redirect_chain' => [],
            'suspicious_redirects' => [],
            'risk_score' => 0
        ];

        try {
            $response = $this->client->request('GET', $url, [
                'allow_redirects' => [
                    'max' => 10,
                    'track_redirects' => true,
                    'strict' => true
                ]
            ]);

            // Mendapatkan rantai redirect
            $redirects = $response->getHeader('X-Guzzle-Redirect-History');
            $analysis['redirect_chain'] = $redirects;

            // Analisis setiap redirect
            foreach ($redirects as $redirect) {
                $redirectDomain = parse_url($redirect, PHP_URL_HOST);

                // Cek domain mencurigakan
                foreach ($this->knownGamblingSites['domain_patterns'] as $pattern) {
                    if (preg_match($pattern, $redirectDomain)) {
                        $analysis['suspicious_redirects'][] = [
                            'url' => $redirect,
                            'pattern_matched' => $pattern
                        ];
                        $analysis['risk_score'] += 3;
                    }
                }

                // Cek parameter mencurigakan
                $queryString = parse_url($redirect, PHP_URL_QUERY);
                if ($queryString) {
                    $suspiciousParams = [
                        'ref', 'affiliate', 'partner', 'bonus', 'promo'
                    ];
                    parse_str($queryString, $params);
                    foreach ($suspiciousParams as $param) {
                        if (array_key_exists($param, $params)) {
                            $analysis['suspicious_redirects'][] = [
                                'url' => $redirect,
                                'suspicious_param' => $param
                            ];
                            $analysis['risk_score'] += 2;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $analysis['error'] = $e->getMessage();
        }

        return $analysis;
    }

    private function findHiddenElements(Crawler $crawler)
{
    $hiddenElements = [
        'css_hidden' => [],
        'js_hidden' => [],
        'obscured_elements' => [],
        'risk_score' => 0
    ];

    // Deteksi elemen tersembunyi via CSS
    $cssSelectors = [
        '[style*="display:none"]',
        '[style*="visibility:hidden"]',
        '[style*="opacity:0"]',
        '.hidden',
        '.invisible',
        '[hidden]'
    ];

    foreach ($cssSelectors as $selector) {
        try {
            $crawler->filter($selector)->each(function ($node) use (&$hiddenElements) {
                $hiddenElements['css_hidden'][] = [
                    'element' => $node->nodeName(),
                    'class' => $node->attr('class'),
                    'id' => $node->attr('id'),
                    'content' => $node->text()
                ];
                $hiddenElements['risk_score'] += 2;
            });
        } catch (\Exception $e) {
            continue; // Skip invalid selectors
        }
    }

    // Deteksi elemen tersembunyi via JavaScript
    $jsSelectors = [
        '[onclick*="show"]',
        '[onmouseover*="reveal"]',
        '[data-hidden="true"]',
        '.js-toggle'
    ];

    foreach ($jsSelectors as $selector) {
        try {
            $crawler->filter($selector)->each(function ($node) use (&$hiddenElements, $selector) {
                $hiddenElements['js_hidden'][] = [
                    'element' => $node->nodeName(),
                    'trigger' => $selector,
                    'content' => $node->text()
                ];
                $hiddenElements['risk_score'] += 2;
            });
        } catch (\Exception $e) {
            continue;
        }
    }

    // Deteksi elemen yang dikaburkan
    $obscuredSelectors = [
        '[style*="text-indent"]',
        '[style*="position:absolute"]',
        '[style*="clip:"]',
        '[style*="transform:translate"]'
    ];

    foreach ($obscuredSelectors as $selector) {
        try {
            $crawler->filter($selector)->each(function ($node) use (&$hiddenElements) {
                $hiddenElements['obscured_elements'][] = [
                    'element' => $node->nodeName(),
                    'style' => $node->attr('style'),
                    'content' => $node->text()
                ];
                $hiddenElements['risk_score'] += 1;
            });
        } catch (\Exception $e) {
            continue;
        }
    }

    return $hiddenElements;
}

    private function analyzeBehavioralPatterns(Crawler $crawler, $html)
    {
        return [
            'user_interaction' => $this->analyzeUserInteraction($crawler),
            'payment_patterns' => $this->analyzePaymentPatterns($html),
            'registration_flow' => $this->analyzeRegistrationFlow($crawler),
            'time_sensitive' => $this->analyzeTimeSensitiveElements($html)
        ];
    }

    private function calculateContentScore($contentAnalysis)
{
    $score = 0;
    $scoringRules = [
        'gambling_keywords' => [
            'base_score' => 2,
            'max_impact' => 20,
            'multiplier' => 1.5,
            'weight_factors' => [
                'direct_terms' => 2.0,
                'indirect_terms' => 1.2,
                'context_terms' => 1.0
            ]
        ],
        'suspicious_domains' => [
            'base_score' => 3,
            'max_impact' => 30,
            'multiplier' => 2,
            'weight_factors' => [
                'gambling_domains' => 2.0,
                'redirect_domains' => 1.5,
                'masked_domains' => 1.8
            ]
        ],
        'financial_indicators' => [
            'base_score' => 2.5,
            'max_impact' => 25,
            'multiplier' => 1.75,
            'weight_factors' => [
                'crypto_transactions' => 2.0,
                'suspicious_amounts' => 1.5,
                'payment_methods' => 1.2
            ]
        ],
        'betting_analysis' => [
            'base_score' => 2.5,
            'max_impact' => 25,
            'multiplier' => 1.8,
            'weight_factors' => [
                'time_patterns' => 2.0,
                'numeric_patterns' => 1.8,
                'context_analysis' => 1.5
            ]
        ],
        'promo_analysis' => [
            'base_score' => 2,
            'max_impact' => 20,
            'multiplier' => 1.25,
            'weight_factors' => [
                'bonus_offers' => 1.5,
                'time_limited' => 1.2,
                'referral_programs' => 1.3
            ]
        ],
        'text_sentiment' => [
            'base_score' => 1.5,
            'max_impact' => 15,
            'multiplier' => 1,
            'weight_factors' => [
                'urgency_indicators' => 1.3,
                'trust_signals' => 1.2,
                'risk_terms' => 1.1
            ]
        ]
    ];

    foreach ($scoringRules as $category => $rules) {
        if (!empty($contentAnalysis[$category])) {
            $categoryData = $contentAnalysis[$category];

            // Hitung skor dasar
            $categoryCount = $this->calculateCategoryCount($categoryData);

            // Aplikasikan faktor pembobotan berdasarkan subcategories
            $weightedScore = $this->applyWeightFactors($categoryData, $rules['weight_factors']);

            // Gunakan fungsi logaritmik untuk menghindari skalabilitas linier
            $logarithmicScore = $rules['base_score'] * log($categoryCount + $weightedScore + 1, 2);

            // Terapkan multiplier dan batasan maksimum
            $scaledScore = min(
                $logarithmicScore * $rules['multiplier'],
                $rules['max_impact']
            );

            $score += $scaledScore;
        }
    }

    // Faktor penalti untuk kombinasi kategori berisiko tinggi
    if ($this->hasHighRiskCombination($contentAnalysis)) {
        $score *= 1.2; // Tingkatkan skor sebesar 20%
    }

    // Normalisasi skor akhir
    return min(round($score, 2), 100);
}

private function calculateCategoryCount($categoryData)
{
    if (is_array($categoryData)) {
        if (isset($categoryData['risk_score'])) {
            return $categoryData['risk_score'];
        }

        $count = 0;
        foreach ($categoryData as $item) {
            if (is_array($item)) {
                $count += count(array_filter($item));
            } elseif ($item) {
                $count++;
            }
        }
        return $count;
    }
    return $categoryData ? 1 : 0;
}
private function applyWeightFactors($categoryData, $weightFactors)
{
    $weightedScore = 0;

    foreach ($weightFactors as $factor => $weight) {
        if (isset($categoryData[$factor])) {
            $value = is_array($categoryData[$factor]) ?
                count($categoryData[$factor]) :
                (float)$categoryData[$factor];
            $weightedScore += $value * $weight;
        }
    }

    return $weightedScore;
}

private function hasHighRiskCombination($contentAnalysis)
{
    $highRiskFactors = 0;

    // Check untuk kombinasi faktor berisiko tinggi
    if (!empty($contentAnalysis['crypto_analysis']['detected_addresses'])) {
        $highRiskFactors++;
    }

    if (!empty($contentAnalysis['gambling_keywords']['direct_gambling_terms'])) {
        $highRiskFactors++;
    }

    if (!empty($contentAnalysis['betting_analysis']['matched_patterns'])) {
        $highRiskFactors++;
    }

    if (!empty($contentAnalysis['suspicious_domains']['gambling_domains'])) {
        $highRiskFactors++;
    }

    // Return true jika ada minimal 2 faktor berisiko tinggi
    return $highRiskFactors >= 2;
}

private function analyzeBettingTerms($text)
{
    $analysis = [
        'matched_patterns' => [],
        'numeric_patterns' => [],
        'time_patterns' => [],
        'context_analysis' => [],
        'risk_score' => 0
    ];

    // Input validation and sanitization
    if (!is_string($text)) {
        Log::warning('Invalid input type for analyzeBettingTerms', [
            'expected' => 'string',
            'received' => gettype($text)
        ]);
        return $analysis;
    }

    try {
        // Sanitize input
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Legitimate contexts that should be excluded
        $legitimateContexts = [
            'schedule' => '/(?:jadwal|schedule|agenda|acara)\s*(?:kegiatan|event|meeting)?/i',
            'sports_news' => '/(?:hasil|skor|pertandingan|tournament|liga)\s*(?:sepakbola|bola|olahraga)/i',
            'business' => '/(?:profit|loss|revenue|pendapatan|kerugian)\s*(?:perusahaan|bisnis|usaha)/i',
            'education' => '/(?:nilai|grade|score|exam|ujian|test)\s*(?:siswa|mahasiswa|student)/i'
        ];

        // Enhanced betting patterns with contextual awareness and validation
        $bettingPatterns = [
            'odds' => [
                'pattern' => '/\b\d+(?:\.\d+)?\s*(?::|x|-)\s*\d+(?:\.\d+)?\b/',
                'weight' => 3,
                'validation' => function($match, $context) {
                    // Check if odds are in betting context
                    if (preg_match('/(?:odds|peluang|chance|probability|bet|taruhan|pasang)/i', $context)) {
                        // Exclude mathematical/statistical contexts
                        if (preg_match('/(?:ratio|perbandingan|scale|skala|matematika|statistik)\s*\d+/i', $context)) {
                            return false;
                        }
                        return true;
                    }
                    return false;
                }
            ],
            'multipliers' => [
                'pattern' => '/\b(?:x|×)\s*\d+(?:\.\d+)?\s*(?:bet|win|menang)\b/i',
                'weight' => 4,
                'validation' => function($match, $context) {
                    // Enhanced validation for multipliers
                    if (preg_match('/(?:bet|taruhan|pasang|gambling|judi)/i', $context)) {
                        // Exclude educational/business contexts
                        if (preg_match('/(?:multiplication|perkalian|factor|faktor|pembelajaran|business)\s*(?:matematika|study|belajar|analysis)/i', $context)) {
                            return false;
                        }
                        return true;
                    }
                    return false;
                }
            ],
            'time_sensitive' => [
                'pattern' => '/\b(?:dalam|in|within)\s*\d+\s*(?:menit|minute|detik|second|jam|hour)\b/i',
                'weight' => 2,
                'validation' => function($match, $context) {
                    // Enhanced time context validation
                    if (preg_match('/(?:deposit|withdraw|bet|taruhan|menang|jackpot|bonus|hadiah)/i', $context)) {
                        // Exclude legitimate time references
                        if (preg_match('/(?:delivery|pengiriman|process|proses|jadwal|schedule|appointment)\s*(?:time|waktu)/i', $context)) {
                            return false;
                        }
                        return true;
                    }
                    return false;
                }
            ],
            'betting_keywords' => [
                'pattern' => '/\b(?:bet|taruhan|judi|gambling|casino|slots?|poker|togel|sportbook)\b/i',
                'weight' => 5,
                'validation' => function($match, $context) {
                    // Validate betting keywords in context
                    if (preg_match('/(?:illegal|warning|bahaya|larangan|anti|against)\s*(?:gambling|judi|betting)/i', $context)) {
                        return false;
                    }
                    return true;
                }
            ]
        ];

        // First check for legitimate contexts
        foreach ($legitimateContexts as $type => $pattern) {
            if (preg_match_all('/\b(?:[01]?[0-9]|2[0-3]):[0-5][0-9]\b/', $text, $matches)) {
                foreach ($matches[0] as $time) {
                    $context = $this->extractContext($text, $time, 100);
                    if (!$this->isLegitimateTimeFormat($time, $context)) {
                        $analysis['betting_terms'][] = $time;
                        $analysis['risk_score'] += 1;
                    }
                }
            }
        }

        // Analyze betting patterns with enhanced context
        foreach ($bettingPatterns as $type => $config) {
            if (preg_match_all($config['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $matchText = $match[0];
                    $position = $match[1];

                    try {
                        // Extract surrounding context with error handling
                        $context = $this->extractExtendedContext($text, $position, 150);

                        // Perform deep context analysis
                        $contextAnalysis = $this->analyzePatternContext($context, $type);

                        // Validate pattern using the provided validation function
                        if ($config['validation']($matchText, $context)) {
                            // Calculate confidence score based on context
                            $confidenceScore = $this->calculateContextConfidence(
                                $contextAnalysis,
                                $config['weight']
                            );

                            if ($confidenceScore > 0.6) { // Threshold for considering a match
                                $analysis['matched_patterns'][] = [
                                    'type' => $type,
                                    'match' => $matchText,
                                    'context' => $context,
                                    'confidence' => $confidenceScore,
                                    'analysis' => $contextAnalysis,
                                    'timestamp' => time()
                                ];

                                $analysis['risk_score'] += $config['weight'] * $confidenceScore;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error analyzing pattern match', [
                            'type' => $type,
                            'match' => $matchText,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
            }
        }

        // Analyze time patterns with error handling
        try {
            $this->analyzeTimePatterns($text, $analysis);
        } catch (\Exception $e) {
            Log::error('Error in time pattern analysis: ' . $e->getMessage());
        }

        // Normalize risk score
        $analysis['risk_score'] = max(0, min(100, $analysis['risk_score']));

        return $analysis;

    } catch (\Exception $e) {
        Log::error('Error in betting terms analysis: ' . $e->getMessage(), [
            'text_sample' => substr($text, 0, 100),
            'trace' => $e->getTraceAsString()
        ]);
        return $analysis;
    }
}

// Tambahan method untuk memudahkan konfigurasi pola

private function analyzeTimePatterns($text, &$analysis)
{
    // Validasi input
    if (!is_string($text)) {
        Log::warning('Invalid input type for analyzeTimePatterns', [
            'expected' => 'string',
            'received' => gettype($text)
        ]);
        return;
    }

    // Initialize time_patterns if not exists
    if (!isset($analysis['time_patterns'])) {
        $analysis['time_patterns'] = [];
    }

    $timePatterns = [
        'specific_time' => [
            'pattern' => '/\b(?:(?:1[0-2]|0?[1-9])(?::[0-5][0-9])?(?:\s*[AaPp][Mm])?)\b/',
            'context_rules' => [
                'suspicious' => [
                    '/(?:bet|taruhan|odds|game|main)\s*close/i',
                    '/(?:result|hasil)\s*announcement/i',
                    '/last\s*(?:chance|kesempatan)/i'
                ],
                'legitimate' => [
                    '/(?:opening|closing)\s*hours/i',
                    '/(?:meeting|appointment|jadwal)\s*time/i',
                    '/(?:class|kelas|lesson|pelajaran)\s*schedule/i'
                ]
            ]
        ],
        'countdown' => [
            'pattern' => '/\b\d+\s*(?:menit|minute|detik|second|jam|hour)\s*(?:lagi|left|remaining)\b/i',
            'context_rules' => [
                'suspicious' => [
                    '/(?:bet|taruhan)\s*(?:close|tutup)/i',
                    '/(?:promo|bonus|offer)\s*(?:end|berakhir)/i',
                    '/last\s*(?:chance|kesempatan)/i'
                ],
                'legitimate' => [
                    '/(?:delivery|shipping|pengiriman)\s*estimate/i',
                    '/(?:countdown|timer)\s*to\s*(?:event|acara)/i',
                    '/(?:session|sesi)\s*timeout/i'
                ]
            ]
        ],
        'duration' => [
            'pattern' => '/\b(?:selama|for|during)\s*\d+\s*(?:menit|minute|jam|hour|hari|day)\b/i',
            'context_rules' => [
                'suspicious' => [
                    '/(?:profit|win|menang)\s*guarantee/i',
                    '/(?:deposit|withdraw)\s*process/i',
                    '/instant\s*(?:payment|pembayaran)/i'
                ],
                'legitimate' => [
                    '/(?:warranty|garansi)\s*period/i',
                    '/(?:delivery|shipping|pengiriman)\s*time/i',
                    '/(?:validity|masa\s*berlaku)/i'
                ]
            ]
        ]
    ];

    try {
        foreach ($timePatterns as $type => $config) {
            if (preg_match_all($config['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $matchText = $match[0];
                    $position = $match[1];

                    // Extract context safely
                    $context = $this->extractExtendedContext($text, $position, 150);

                    // Analyze context using rules
                    $contextScore = $this->analyzeTimeContext(
                        $context,
                        $config['context_rules']
                    );

                    if ($contextScore > 0) {
                        $analysis['time_patterns'][] = [
                            'type' => $type,
                            'match' => $matchText,
                            'context' => $context,
                            'score' => $contextScore
                        ];

                        // Update risk score
                        if (!isset($analysis['risk_score'])) {
                            $analysis['risk_score'] = 0;
                        }
                        $analysis['risk_score'] += $contextScore;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        Log::error('Error in time pattern analysis: ' . $e->getMessage(), [
            'text_sample' => substr($text, 0, 100),
            'trace' => $e->getTraceAsString()
        ]);
    }

    // Ensure we always return a valid structure even if errors occur
    if (!isset($analysis['time_patterns'])) {
        $analysis['time_patterns'] = [];
    }
    if (!isset($analysis['risk_score'])) {
        $analysis['risk_score'] = 0;
    }
}



private function analyzeTimeContext($context, $rules)
{
    $score = 0;
    $suspiciousMatches = 0;
    $legitimateMatches = 0;

    // Check suspicious patterns
    foreach ($rules['suspicious'] as $pattern) {
        if (preg_match($pattern, $context)) {
            $suspiciousMatches++;
            $score += 2;
        }
    }

    // Check legitimate patterns
    foreach ($rules['legitimate'] as $pattern) {
        if (preg_match($pattern, $context)) {
            $legitimateMatches++;
            $score -= 2;
        }
    }

    // Calculate final score considering both pattern types
    if ($suspiciousMatches > $legitimateMatches) {
        return $score;
    }

    return 0; // Return 0 if more legitimate matches or equal
}

private function extractExtendedContext($text, $position, $radius)
{
    try {
        // Validasi input
        if (!is_string($text) || !is_numeric($position) || !is_numeric($radius)) {
            throw new \InvalidArgumentException('Invalid input parameters for extractExtendedContext');
        }

        // Ensure position and radius are within text bounds
        $textLength = strlen($text);
        $position = max(0, min($position, $textLength - 1));
        $radius = max(0, $radius);

        // Calculate boundaries
        $start = max(0, $position - $radius);
        $end = min($textLength, $position + $radius);

        // Try to extend to complete sentences
        while ($start > 0 && !in_array($text[$start - 1], ['.', '!', '?', "\n"])) {
            $start--;
        }
        while ($end < $textLength && !in_array($text[$end], ['.', '!', '?', "\n"])) {
            $end++;
        }

        return trim(substr($text, $start, $end - $start));
    } catch (\Exception $e) {
        Log::error('Error in extracting context: ' . $e->getMessage(), [
            'position' => $position,
            'radius' => $radius,
            'text_length' => isset($text) ? strlen($text) : 0
        ]);

        // Return a safe default
        return '';
    }
}


private function analyzePatternContext($context, $patternType)
{
    $analysis = [
        'gambling_indicators' => 0,
        'legitimate_indicators' => 0,
        'context_keywords' => []
    ];

    // Pattern-specific context indicators
    $contextIndicators = [
        'odds' => [
            'gambling' => [
                '/(?:bet|taruhan|pasaran|odds)/i',
                '/(?:win|menang|jackpot|hadiah)/i',
                '/(?:game|permainan|main)/i'
            ],
            'legitimate' => [
                '/(?:statistics|statistik|data|analysis)/i',
                '/(?:research|penelitian|study)/i',
                '/(?:comparison|perbandingan|ratio)/i'
            ]
        ],
        'multipliers' => [
            'gambling' => [
                '/(?:bet|taruhan|stake|modal)/i',
                '/(?:profit|keuntungan|return)/i',
                '/(?:bonus|promotion|promo)/i'
            ],
            'legitimate' => [
                '/(?:investment|investasi|return)/i',
                '/(?:growth|pertumbuhan|increase)/i',
                '/(?:factor|faktor|multiplier)/i'
            ]
        ],
        'time_sensitive' => [
            'gambling' => [
                '/(?:deposit|withdraw|payment)/i',
                '/(?:instant|cepat|fast)/i',
                '/(?:process|proses|transaction)/i'
            ],
            'legitimate' => [
                '/(?:delivery|pengiriman|shipping)/i',
                '/(?:service|layanan|support)/i',
                '/(?:response|respons|reply)/i'
            ]
        ]
    ];

    if (isset($contextIndicators[$patternType])) {
        // Check gambling indicators
        foreach ($contextIndicators[$patternType]['gambling'] as $pattern) {
            if (preg_match($pattern, $context, $matches)) {
                $analysis['gambling_indicators']++;
                $analysis['context_keywords'][] = $matches[0];
            }
        }

        // Check legitimate indicators
        foreach ($contextIndicators[$patternType]['legitimate'] as $pattern) {
            if (preg_match($pattern, $context, $matches)) {
                $analysis['legitimate_indicators']++;
                $analysis['context_keywords'][] = $matches[0];
            }
        }
    }

    return $analysis;
}

private function calculateContextConfidence($contextAnalysis, $baseWeight)
{
    // Base confidence calculation
    $confidence = 0;

    if ($contextAnalysis['gambling_indicators'] > 0 ||
        $contextAnalysis['legitimate_indicators'] > 0) {

        $totalIndicators = $contextAnalysis['gambling_indicators'] +
                          $contextAnalysis['legitimate_indicators'];

        $confidence = $contextAnalysis['gambling_indicators'] / $totalIndicators;
    }

    // Apply weight modifier
    $weightedConfidence = $confidence * ($baseWeight / 5);

    // Normalize to 0-1 range
    return min(1, max(0, $weightedConfidence));
}

private function extractContext($text, $term, $radius) {
    $pos = stripos($text, $term);
    if ($pos === false) return '';

    $start = max(0, $pos - $radius);
    $length = strlen($term) + (2 * $radius);

    return substr($text, $start, $length);
}

// Perbaikan pada fungsi analyzeBettingTerms untuk waktu
private function isLegitimateTimeFormat($time, $context) {
    // Cek format waktu standar
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        return false;
    }

    // Cek konteks legitimate
    $legitimateContexts = [
        '/(?:jadwal|schedule|agenda)\s*(?:kegiatan|acara|event)/i',
        '/jam\s*(?:buka|operasional|kerja)/i',
        '/opening\s*hours?/i',
        '/\b(?:pukul|jam|waktu)\b/i'
    ];

    foreach ($legitimateContexts as $pattern) {
        if (preg_match($pattern, $context)) {
            return true;
        }
    }

    return false;
}
private function verifyCryptoTransactions($text) {
    $analysis = [
        'detected_addresses' => [],
        'suspicious_patterns' => [],
        'risk_score' => 0
    ];

    // Pola untuk berbagai jenis alamat cryptocurrency
    $cryptoPatterns = [
        'bitcoin' => [
            'pattern' => '/\b(bc1|[13])[A-HJ-NP-Za-km-z1-9]{25,39}\b/',
            'risk_weight' => 3
        ],
        'ethereum' => [
            'pattern' => '/\b0x[a-fA-F0-9]{40}\b/',
            'risk_weight' => 3
        ],
        'tron' => [
            'pattern' => '/\bT[A-Za-z1-9]{33}\b/',
            'risk_weight' => 2
        ],
        'ripple' => [
            'pattern' => '/\br[0-9a-zA-Z]{24,34}\b/',
            'risk_weight' => 2
        ],
        'litecoin' => [
            'pattern' => '/\b[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}\b/',
            'risk_weight' => 2
        ]
    ];

    // Konteks yang mencurigakan
    $suspiciousContexts = [
        '/deposit|withdraw|transfer/i',
        '/min(?:imum)?\s*\d+/i',
        '/instant|fast|quick/i',
        '/anonymous|private|secure/i'
    ];

    foreach ($cryptoPatterns as $crypto => $data) {
        if (preg_match_all($data['pattern'], $text, $matches)) {
            foreach ($matches[0] as $address) {
                // Validasi format dan checksum
                if ($this->validateCryptoAddress($address, $crypto)) {
                    $context = $this->extractContext($text, $address, 50);
                    $contextRisk = $this->analyzeCryptoContext($context, $suspiciousContexts);

                    $analysis['detected_addresses'][] = [
                        'address' => $address,
                        'type' => $crypto,
                        'context' => $context,
                        'risk_level' => $contextRisk
                    ];

                    // Tingkatkan risk score berdasarkan konteks dan tipe crypto
                    $analysis['risk_score'] += ($contextRisk * $data['risk_weight']);
                }
            }
        }
    }

    return $analysis;
}

private function validateCryptoAddress($address, $type) {
    switch ($type) {
        case 'bitcoin':
            return $this->validateBitcoinAddress($address);
        case 'ethereum':
            return $this->validateEthereumAddress($address);
        default:
            return true; // Fallback untuk tipe lain
    }
}

private function validateBitcoinAddress($address) {
    // Implementasi validasi checksum Bitcoin
    if (!preg_match('/^(bc1|[13])[A-HJ-NP-Za-km-z1-9]{25,39}$/', $address)) {
        return false;
    }

    // Base58 karakter yang valid untuk Bitcoin
    $base58Chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    // Verifikasi karakter valid
    for ($i = 0; $i < strlen($address); $i++) {
        if (strpos($base58Chars, $address[$i]) === false &&
            !in_array($address[$i], ['1', '3', 'b', 'c'])) {
            return false;
        }
    }

    return true;
}

private function validateEthereumAddress($address) {
    // Validasi format dasar Ethereum address
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        return false;
    }

    // Verifikasi checksum untuk address dengan mixed-case
    if (preg_match('/[A-F]/', $address)) {
        // Implementasi checksum Ethereum
        $address = substr($address, 2); // Hapus '0x'
        $addressHash = hash('keccak256', strtolower($address));

        for ($i = 0; $i < 40; $i++) {
            if (ctype_alpha($address[$i])) {
                $hashChar = hexdec($addressHash[$i]);
                if ((hexdec($addressHash[$i]) > 7 &&
                     $address[$i] !== strtoupper($address[$i])) ||
                    (hexdec($addressHash[$i]) <= 7 &&
                     $address[$i] !== strtolower($address[$i]))) {
                    return false;
                }
            }
        }
    }

    return true;
}

private function analyzeCryptoContext($context, $suspiciousPatterns) {
    $riskLevel = 0;

    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $context)) {
            $riskLevel++;
        }
    }

    // Normalisasi skor (0-5)
    return min(5, $riskLevel);
}

private function calculateTechnicalScore($technicalAnalysis)
{
    $score = 0;
    $scoringRules = [
        'hidden_elements' => [
            'base_score' => 3,
            'max_impact' => 30,
            'risk_multiplier' => 2
        ],
        'domain_analysis' => [
            'base_score' => 2,
            'max_impact' => 20,
            'risk_multiplier' => 1.5
        ],
        'link_structure' => [
            'base_score' => 2.5,
            'max_impact' => 25,
            'risk_multiplier' => 1.75
        ],
        'js_analysis' => [
            'base_score' => 2,
            'max_impact' => 20,
            'risk_multiplier' => 1.25
        ]
    ];

    foreach ($scoringRules as $category => $rules) {
        $categoryRiskScore = 0;

        if (isset($technicalAnalysis[$category])) {
            $analysis = $technicalAnalysis[$category];

            // Perhitungan skor berdasarkan kompleksitas dan risiko
            switch ($category) {
                case 'hidden_elements':
                    $categoryRiskScore = isset($analysis['risk_score']) ?
                        $analysis['risk_score'] :
                        (count($analysis['css_hidden'] ?? []) +
                         count($analysis['js_hidden'] ?? []) +
                         count($analysis['obscured_elements'] ?? []));
                    break;

                case 'domain_analysis':
                    $categoryRiskScore = $analysis['risk_score'] ?? 0;
                    break;

                case 'link_structure':
                    $categoryRiskScore = count($analysis['suspicious_links'] ?? []) * 2;
                    break;

                case 'js_analysis':
                    $categoryRiskScore = $analysis['obfuscation_detected'] ? 10 :
                        (count($analysis['suspicious_patterns'] ?? []) * 2);
                    break;
            }

            // Terapkan pembobotan logaritmik dengan batasan
            $logarithmicScore = $rules['base_score'] * log($categoryRiskScore + 1, 2);
            $scaledScore = min(
                $logarithmicScore * $rules['risk_multiplier'],
                $rules['max_impact']
            );

            $score += $scaledScore;
        }
    }

    return min(round($score, 2), 100);
}

private function calculateBehavioralScore($behavioralAnalysis)
{
    $score = 0;
    $scoringRules = [
        'payment_patterns' => [
            'base_score' => 2.5,
            'max_impact' => 25,
            'risk_multiplier' => 1.75
        ],
        'user_interaction' => [
            'base_score' => 2,
            'max_impact' => 20,
            'risk_multiplier' => 1.5
        ],
        'registration_flow' => [
            'base_score' => 2,
            'max_impact' => 20,
            'risk_multiplier' => 1.25
        ],
        'time_sensitive' => [
            'base_score' => 1.5,
            'max_impact' => 15,
            'risk_multiplier' => 1
        ]
    ];

    foreach ($scoringRules as $category => $rules) {
        $categoryRiskScore = 0;

        if (isset($behavioralAnalysis[$category])) {
            $analysis = $behavioralAnalysis[$category];

            // Perhitungan skor berdasarkan kompleksitas dan risiko
            switch ($category) {
                case 'payment_patterns':
                    $categoryRiskScore = array_sum(
                        array_map('count', $analysis)
                    );
                    break;

                case 'user_interaction':
                    $categoryRiskScore = match($analysis['risk_level'] ?? 'low') {
                        'high' => 10,
                        'medium' => 5,
                        default => 1
                    } + count($analysis['suspicious_elements'] ?? []);
                    break;

                case 'registration_flow':
                    $categoryRiskScore = count($analysis['suspicious_elements'] ?? []) * 2 +
                        ($analysis['has_registration'] ?? false ? 5 : 0);
                    break;

                case 'time_sensitive':
                    // Modifikasi untuk struktur data baru
                    if (isset($analysis['total_risk_score'])) {
                        $categoryRiskScore = $analysis['total_risk_score'];

                        // Tambahan bobot untuk kategori waktu yang berisiko
                        if (!empty($analysis['risk_categories'])) {
                            foreach ($analysis['risk_categories'] as $timeCategory) {
                                $categoryRiskScore += count($timeCategory['suspicious_items'] ?? []);
                            }
                        }
                    } else {
                        // Fallback untuk struktur data lama
                        $categoryRiskScore = array_sum(
                            array_map('count', $analysis)
                        ) * 2;
                    }
                    break;
            }

            // Terapkan pembobotan logaritmik dengan batasan
            $logarithmicScore = $rules['base_score'] * log($categoryRiskScore + 1, 2);
            $scaledScore = min(
                $logarithmicScore * $rules['risk_multiplier'],
                $rules['max_impact']
            );

            $score += $scaledScore;
        }
    }

    return min(round($score, 2), 100);
}



private function calculateFinalRisk($scoreSystem)
{
    $weights = [
        'content_score' => 0.4,
        'technical_score' => 0.35,
        'behavioral_score' => 0.25
    ];

    $totalScore = 0;
    $totalWeight = 0;
    $confidenceFactors = [];

    foreach ($weights as $metric => $weight) {
        if (isset($scoreSystem[$metric]) && $scoreSystem[$metric] > 0) {
            // Normalisasi skor
            $normalizedScore = min(100, $scoreSystem[$metric]);

            // Kalkulasi weighted score
            $weightedScore = $normalizedScore * $weight;
            $totalScore += $weightedScore;
            $totalWeight += $weight;

            // Hitung faktor kepercayaan
            $confidenceFactors[$metric] = $this->calculateConfidenceFactor(
                $scoreSystem[$metric],
                $metric
            );
        }
    }

    // Normalisasi total skor
    $finalScore = $totalWeight > 0 ? ($totalScore / $totalWeight) : 0;

    // Kalkulasi confidence level
    $confidenceLevel = array_sum($confidenceFactors) / count($confidenceFactors);

    // Threshold yang lebih realistis
    return [
        'is_gambling' => $finalScore >= 60, // Meningkatkan threshold
        'risk_level' => $this->determineRiskLevel($finalScore),
        'confidence' => round($confidenceLevel, 2)
    ];
}

private function calculateConfidenceFactor($score, $metricType) {
    $baseConfidence = min(100, $score) / 100;

    // Faktor koreksi berdasarkan tipe metrik
    $correctionFactors = [
        'content_score' => 1.2,
        'technical_score' => 1.0,
        'behavioral_score' => 0.8
    ];

    return $baseConfidence * ($correctionFactors[$metricType] ?? 1.0);
}


    private function determineRiskLevel($totalScore)
{
    // Perbaikan: Definisi level risiko yang lebih granular
    $riskLevels = [
        ['threshold' => 90, 'level' => 'Sangat Tinggi (Kritis)', 'confidence' => 'Sangat Yakin'],
        ['threshold' => 75, 'level' => 'Tinggi', 'confidence' => 'Yakin'],
        ['threshold' => 60, 'level' => 'Sedang-Tinggi', 'confidence' => 'Cukup Yakin'],
        ['threshold' => 45, 'level' => 'Sedang', 'confidence' => 'Netral'],
        ['threshold' => 30, 'level' => 'Rendah-Sedang', 'confidence' => 'Kurang Yakin'],
        ['threshold' => 15, 'level' => 'Rendah', 'confidence' => 'Tidak Yakin'],
        ['threshold' => 0, 'level' => 'Sangat Rendah', 'confidence' => 'Tidak Ada Indikasi']
    ];

    foreach ($riskLevels as $riskLevel) {
        if ($totalScore >= $riskLevel['threshold']) {
            return $riskLevel['level'];
        }
    }

    return 'Sangat Rendah';
}

    private function analyzeMetaInformation(Crawler $crawler)
    {
        $metaAnalysis = [];

        // Analyze meta tags
        $crawler->filter('meta')->each(function ($node) use (&$metaAnalysis) {
            $name = $node->attr('name') ?? $node->attr('property');
            $content = $node->attr('content');

            if ($name && $content) {
                $metaAnalysis['meta_tags'][$name] = $this->analyzeMetaContent($content);
            }
        });

        // Analyze title
        $title = $crawler->filter('title')->count() > 0 ?
                 $crawler->filter('title')->text() : '';
        $metaAnalysis['title_analysis'] = $this->analyzeMetaContent($title);

        return $metaAnalysis;
    }

    private function analyzeMetaContent($content)
    {
        $analysis = [
            'gambling_indicators' => 0,
            'suspicious_words' => []
        ];

        foreach ($this->advancedGamblingPatterns as $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    $analysis['gambling_indicators']++;
                    $analysis['suspicious_words'] = array_merge(
                        $analysis['suspicious_words'],
                        $matches[0]
                    );
                }
            }
        }

        return $analysis;
    }

    private function analyzeJavaScript($html)
    {
        $jsAnalysis = [
            'suspicious_patterns' => [],
            'obfuscation_detected' => false,
            'risk_level' => 'low'
        ];

        // Check for obfuscated JavaScript
        if (preg_match('/eval\(|String\.fromCharCode|unescape\(|decrypt\(/', $html)) {
            $jsAnalysis['obfuscation_detected'] = true;
            $jsAnalysis['risk_level'] = 'high';
        }

        // Analyze inline scripts
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $matches);
        foreach ($matches[1] as $script) {
            $jsAnalysis['suspicious_patterns'] = array_merge(
                $jsAnalysis['suspicious_patterns'],
                $this->analyzeScriptContent($script)
            );
        }

        return $jsAnalysis;
    }

    private function analyzeScriptContent($script)
    {
        $suspicious = [];
        $patterns = [
            'redirect' => '/window\.location|document\.location|window\.replace/i',
            'popup' => '/window\.open|popup|modal/i',
            'timer' => '/setTimeout|setInterval/i',
            'storage' => '/localStorage|sessionStorage/i'
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $script)) {
                $suspicious[] = $type;
            }
        }

        return $suspicious;
    }

    private function analyzeLinkStructure(Crawler $crawler)
    {
        $linkAnalysis = [
            'external_links' => [],
            'suspicious_links' => [],
            'risk_patterns' => []
        ];

        $crawler->filter('a')->each(function ($node) use (&$linkAnalysis) {
            $href = $node->attr('href');
            if (!$href) return;

            $domain = parse_url($href, PHP_URL_HOST);
            if ($domain) {
                $linkAnalysis['external_links'][] = $domain;

                // Check against known patterns
                foreach ($this->knownGamblingSites['domain_patterns'] as $pattern) {
                    if (preg_match($pattern, $domain)) {
                        $linkAnalysis['suspicious_links'][] = $href;
                        $linkAnalysis['risk_patterns'][] = $pattern;
                    }
                }
            }
        });

        $linkAnalysis['external_links'] = array_unique($linkAnalysis['external_links']);
        $linkAnalysis['suspicious_links'] = array_unique($linkAnalysis['suspicious_links']);
        $linkAnalysis['risk_patterns'] = array_unique($linkAnalysis['risk_patterns']);
        return $linkAnalysis;
    }


    private function analyzeDomain($url)
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $analysis = [
            'domain' => $domain,
            'risk_score' => 0,
            'risk_factors' => []
        ];

        // Check domain age and registration
        if ($this->isDomainNewOrExpiring($domain)) {
            $analysis['risk_score'] += 3;
            $analysis['risk_factors'][] = 'new_or_expiring_domain';
        }

        // Check for suspicious TLD
        $tld = $this->extractTLD($domain);
        if ($this->isSuspiciousTLD($tld)) {
            $analysis['risk_score'] += 2;
            $analysis['risk_factors'][] = 'suspicious_tld';
        }

        // Check for number patterns in domain
        if (preg_match('/\d{2,}/', $domain)) {
            $analysis['risk_score'] += 1;
            $analysis['risk_factors'][] = 'numeric_pattern';
        }

        // Check for gambling-related keywords in domain
        foreach ($this->knownGamblingSites['domain_patterns'] as $pattern) {
            if (preg_match($pattern, $domain)) {
                $analysis['risk_score'] += 4;
                $analysis['risk_factors'][] = 'gambling_keyword_in_domain';
                break;
            }
        }

        return $analysis;
    }

    private function analyzeUserInteraction(Crawler $crawler)
    {
        $analysis = [
            'risk_level' => 'low',
            'suspicious_elements' => [],
            'interaction_patterns' => []
        ];

        // Check for registration forms
        $forms = $crawler->filter('form')->each(function ($node) {
            return [
                'action' => $node->attr('action'),
                'inputs' => $node->filter('input')->count(),
                'has_password' => $node->filter('input[type="password"]')->count() > 0,
                'has_submit' => $node->filter('input[type="submit"], button[type="submit"]')->count() > 0
            ];
        });

        foreach ($forms as $form) {
            if ($form['has_password'] && $form['has_submit']) {
                $analysis['suspicious_elements'][] = 'registration_form';
                $analysis['risk_level'] = 'medium';
            }
        }

        // Check for live chat widgets
        $chatPatterns = [
            '/livechat/i',
            '/chat-widget/i',
            '/customer-support/i',
            '/cs-online/i'
        ];

        foreach ($chatPatterns as $pattern) {
            if ($crawler->filter("[class*='$pattern'], [id*='$pattern']")->count() > 0) {
                $analysis['suspicious_elements'][] = 'live_chat';
                $analysis['risk_level'] = 'high';
            }
        }

        // Check for countdown timers or urgency elements
        $urgencyPatterns = [
            '/countdown/i',
            '/timer/i',
            '/limited-time/i',
            '/expires/i'
        ];

        foreach ($urgencyPatterns as $pattern) {
            if ($crawler->filter("[class*='$pattern'], [id*='$pattern']")->count() > 0) {
                $analysis['interaction_patterns'][] = 'urgency_element';
                $analysis['risk_level'] = 'high';
            }
        }

        return $analysis;
    }

    private function analyzePaymentPatterns($html)
    {
        $patterns = [
            'payment_methods' => [
                '/bank\s*transfer/i',
                '/e-wallet/i',
                '/crypto(?:currency)?/i',
                '/(visa|mastercard|paypal)/i'
            ],
            'transaction_terms' => [
                '/min(?:imal|imum)?\s*deposit/i',
                '/max(?:imal|imum)?\s*withdraw/i',
                '/instant\s*payment/i',
                '/fast\s*process/i'
            ],
            'bonus_terms' => [
                '/welcome\s*bonus/i',
                '/deposit\s*bonus/i',
                '/cashback/i',
                '/rollingan/i'
            ]
        ];

        $results = [];
        foreach ($patterns as $category => $categoryPatterns) {
            $results[$category] = [];
            foreach ($categoryPatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $results[$category] = array_merge($results[$category], $matches[0]);
                }
            }
            $results[$category] = array_unique($results[$category]);
        }

        return $results;
    }

    private function analyzeRegistrationFlow(Crawler $crawler)
    {
        $analysis = [
            'has_registration' => false,
            'required_fields' => [],
            'suspicious_elements' => []
        ];

        // Check for registration forms
        $crawler->filter('form')->each(function ($node) use (&$analysis) {
            $inputs = $node->filter('input, select, textarea');

            if ($inputs->count() > 0) {
                $analysis['has_registration'] = true;

                $inputs->each(function ($input) use (&$analysis) {
                    $type = $input->attr('type');
                    $name = $input->attr('name');
                    $placeholder = $input->attr('placeholder');

                    if ($type || $name || $placeholder) {
                        $analysis['required_fields'][] = [
                            'type' => $type,
                            'name' => $name,
                            'placeholder' => $placeholder
                        ];
                    }
                });
            }
        });

        // Analyze required fields for suspicious patterns
        foreach ($analysis['required_fields'] as $field) {
            $fieldString = implode(' ', array_filter($field));
            $suspicious_patterns = [
                '/referr?al/i',
                '/sponsor/i',
                '/upline/i',
                '/bank/i',
                '/wallet/i'
            ];

            foreach ($suspicious_patterns as $pattern) {
                if (preg_match($pattern, $fieldString)) {
                    $analysis['suspicious_elements'][] = $pattern;
                }
            }
        }

        return $analysis;
    }

    private function analyzeTimeSensitiveElements($html)
{
    return [
        'countdown_timers' => $this->detectAdvancedCountdownTimers($html),
        'limited_offers' => $this->detectContextualLimitedOffers($html),
        'real_time_stats' => $this->detectContextualRealTimeStats($html),
        'risk_assessment' => $this->assessTimeSensitiveRisk($html)
    ];
}
private function detectAdvancedCountdownTimers($html)
{
    $timerAnalysis = [
        'detected_timers' => [],
        'potential_gambling_indicators' => [],
        'risk_score' => 0
    ];

    // Pola waktu yang lebih kompleks
    $timerPatterns = [
        'standard_timer' => '/\d+:\d+:\d+/', // HH:MM:SS
        'countdown' => '/(?:countdown|timer)\s*(?:to|for)?\s*(\d+\s*(?:minute|hour|day|week|month))/i',
        'time_remaining' => '/(\d+(?:\.\d+)?)\s*(?:minute|hour|day|left|remaining)/i',
        'event_timer' => '/(\d+\s*(?:minute|hour|day))\s*(?:until|before)\s*(?:event|promo|offer)/i'
    ];

    foreach ($timerPatterns as $type => $pattern) {
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $context = $this->extractContext($html, $fullMatch, 100);

                // Analisis konteks untuk menentukan risiko
                $contextRisk = $this->assessTimerContext($context);

                $timerAnalysis['detected_timers'][] = [
                    'type' => $type,
                    'match' => $fullMatch,
                    'context' => $context,
                    'risk_details' => $contextRisk
                ];

                // Akumulasi skor risiko
                $timerAnalysis['risk_score'] += $contextRisk['risk_score'];

                // Tambahkan indikator perjudian yang mencurigakan
                if ($contextRisk['is_suspicious']) {
                    $timerAnalysis['potential_gambling_indicators'][] = $fullMatch;
                }
            }
        }
    }

    return $timerAnalysis;
}


private function assessTimerContext($context)
{
    $riskAssessment = [
        'is_suspicious' => false,
        'risk_score' => 0,
        'matched_patterns' => []
    ];

    // Pola konteks yang mencurigakan
    $suspiciousContextPatterns = [
        'gambling_indicators' => [
            '/bonus|winnings?|jackpot/i',
            '/(?:bet|betting|gambling)\s*(?:offer|promo)/i',
            '/limited\s*time\s*(?:offer|promo)/i'
        ],
        'urgent_language' => [
            '/hurry|quick|fast/i',
            '/don\'t\s*miss|last\s*chance/i',
            '/expires?(\s+in)?/i'
        ],
        'financial_context' => [
            '/deposit|withdraw/i',
            '/min(?:imal)?(?:\s*deposit)?/i',
            '/cashback|bonus/i'
        ]
    ];

    // Cek setiap kategori pola
    foreach ($suspiciousContextPatterns as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $context)) {
                $riskAssessment['is_suspicious'] = true;
                $riskAssessment['matched_patterns'][] = [
                    'category' => $category,
                    'pattern' => $pattern
                ];

                // Penyesuaian skor risiko berdasarkan kategori
                $riskScores = [
                    'gambling_indicators' => 3,
                    'urgent_language' => 2,
                    'financial_context' => 2
                ];
                $riskAssessment['risk_score'] += $riskScores[$category];
            }
        }
    }

    // Normalisasi skor risiko
    $riskAssessment['risk_score'] = min($riskAssessment['risk_score'], 10);

    return $riskAssessment;
}

private function detectContextualLimitedOffers($html)
{
    $offersAnalysis = [
        'detected_offers' => [],
        'potential_gambling_offers' => [],
        'risk_score' => 0
    ];

    $offerPatterns = [
        'limited_time' => '/limited\s*time\s*offer/i',
        'quantity_limited' => '/only\s*(\d+)\s*(?:left|remaining)/i',
        'time_bound' => '/(?:valid|offer)\s*(?:until|for)\s*(\d+\s*(?:hour|day|week))/i',
        'exclusive_promo' => '/exclusive\s*(?:offer|promo)/i'
    ];

    foreach ($offerPatterns as $type => $pattern) {
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $context = $this->extractContext($html, $fullMatch, 100);

                // Analisis konteks untuk menentukan risiko
                $contextRisk = $this->assessOfferContext($context);

                $offersAnalysis['detected_offers'][] = [
                    'type' => $type,
                    'match' => $fullMatch,
                    'context' => $context,
                    'risk_details' => $contextRisk
                ];

                // Akumulasi skor risiko
                $offersAnalysis['risk_score'] += $contextRisk['risk_score'];

                // Tambahkan penawaran yang mencurigakan
                if ($contextRisk['is_suspicious']) {
                    $offersAnalysis['potential_gambling_offers'][] = $fullMatch;
                }
            }
        }
    }

    return $offersAnalysis;
}
private function assessOfferContext($context)
{
    $riskAssessment = [
        'is_suspicious' => false,
        'risk_score' => 0,
        'matched_patterns' => []
    ];

    // Pola konteks untuk penilaian risiko penawaran
    $suspiciousContextPatterns = [
        'financial_indicators' => [
            '/deposit|withdraw/i',
            '/bonus\s*\d+%/i',
            '/cashback/i'
        ],
        'gambling_keywords' => [
            '/bet|betting/i',
            '/win(?:nings?)?/i',
            '/jackpot/i',
            '/slot|casino/i'
        ],
        'urgency_language' => [
            '/hurry|quick/i',
            '/last\s*chance/i',
            '/limited\s*time/i'
        ]
    ];

    // Cek setiap kategori pola
    foreach ($suspiciousContextPatterns as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $context)) {
                $riskAssessment['is_suspicious'] = true;
                $riskAssessment['matched_patterns'][] = [
                    'category' => $category,
                    'pattern' => $pattern
                ];

                // Penyesuaian skor risiko berdasarkan kategori
                $riskScores = [
                    'financial_indicators' => 3,
                    'gambling_keywords' => 4,
                    'urgency_language' => 2
                ];
                $riskAssessment['risk_score'] += $riskScores[$category];
            }
        }
    }

    // Normalisasi skor risiko
    $riskAssessment['risk_score'] = min($riskAssessment['risk_score'], 10);

    return $riskAssessment;
}
private function detectContextualRealTimeStats($html)
{
    $statsAnalysis = [
        'detected_stats' => [],
        'potential_gambling_stats' => [],
        'risk_score' => 0
    ];

    $statsPatterns = [
        'player_count' => '/(\d+)\s*(?:online|active)\s*players?/i',
        'current_winners' => '/current\s*winners?/i',
        'recent_transactions' => '/recent\s*(?:transaction|win)s?/i',
        'live_stats' => '/live\s*(?:stats?|data)/i'
    ];

    foreach ($statsPatterns as $type => $pattern) {
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $context = $this->extractContext($html, $fullMatch, 100);

                // Analisis konteks untuk menentukan risiko
                $contextRisk = $this->assessStatsContext($context);

                $statsAnalysis['detected_stats'][] = [
                    'type' => $type,
                    'match' => $fullMatch,
                    'context' => $context,
                    'risk_details' => $contextRisk
                ];

                // Akumulasi skor risiko
                $statsAnalysis['risk_score'] += $contextRisk['risk_score'];

                // Tambahkan statistik yang mencurigakan
                if ($contextRisk['is_suspicious']) {
                    $statsAnalysis['potential_gambling_stats'][] = $fullMatch;
                }
            }
        }
    }

    return $statsAnalysis;
}

private function assessStatsContext($context)
{
    $riskAssessment = [
        'is_suspicious' => false,
        'risk_score' => 0,
        'matched_patterns' => []
    ];

    // Pola konteks untuk penilaian risiko statistik
    $suspiciousContextPatterns = [
        'gambling_indicators' => [
            '/bet|betting/i',
            '/win(?:nings?)?/i',
            '/jackpot/i',
            '/slot|casino/i'
        ],
        'financial_context' => [
            '/deposit|withdraw/i',
            '/min(?:imal)?(?:\s*deposit)?/i',
            '/cashback|bonus/i'
        ],
        'real_time_language' => [
            '/live|instant/i',
            '/current|now/i',
            '/real[-\s]?time/i'
        ]
    ];

    // Cek setiap kategori pola
    foreach ($suspiciousContextPatterns as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $context)) {
                $riskAssessment['is_suspicious'] = true;
                $riskAssessment['matched_patterns'][] = [
                    'category' => $category,
                    'pattern' => $pattern
                ];

                // Penyesuaian skor risiko berdasarkan kategori
                $riskScores = [
                    'gambling_indicators' => 4,
                    'financial_context' => 3,
                    'real_time_language' => 2
                ];
                $riskAssessment['risk_score'] += $riskScores[$category];
            }
        }
    }

    // Normalisasi skor risiko
    $riskAssessment['risk_score'] = min($riskAssessment['risk_score'], 10);

    return $riskAssessment;
}

private function assessTimeSensitiveRisk($html)
{
    $overallRiskAssessment = [
        'total_risk_score' => 0,
        'risk_categories' => []
    ];

    // Analisis komponen waktu yang sensitif
    $components = [
        'countdown_timers' => $this->detectAdvancedCountdownTimers($html),
        'limited_offers' => $this->detectContextualLimitedOffers($html),
        'real_time_stats' => $this->detectContextualRealTimeStats($html)
    ];

    foreach ($components as $component => $analysis) {
        if ($analysis['risk_score'] > 0) {
            $overallRiskAssessment['risk_categories'][$component] = [
                'risk_score' => $analysis['risk_score'],
                'suspicious_items' => $analysis['potential_gambling_indicators'] ??
                                      $analysis['potential_gambling_offers'] ??
                                      $analysis['potential_gambling_stats'] ?? []
            ];
        }
    }

    // Hitung total skor risiko
    $overallRiskAssessment['total_risk_score'] = array_reduce(
        $overallRiskAssessment['risk_categories'],
        function($carry, $item) {
            return $carry + $item['risk_score'];
        },
        0
    );

    return $overallRiskAssessment;
}
    private function isDomainNewOrExpiring($domain)
    {
        // Implement domain age check logic here
        // This could involve WHOIS lookup or database check
        return false;
    }

    private function extractTLD($domain)
    {
        $parts = explode('.', $domain);
        return end($parts);
    }

    private function isSuspiciousTLD($tld)
    {
        $suspiciousTLDs = ['top', 'xyz', 'win', 'bet', 'casino', 'online'];
        return in_array(strtolower($tld), $suspiciousTLDs);
    }
}
