<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BackdoorDetectionService
{
    private $client;


    private $sensitivePatterns = [
        // API Keys and Tokens
        '/([a-zA-Z0-9_-]+(?:key|token|secret|password|pwd|auth))\s*[=:]\s*[\'"][a-zA-Z0-9._-]+[\'"]/i',

        // Email addresses
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i',

        // IP addresses
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',

        // Base64 encoded data
        '/[a-zA-Z0-9+\/]{32,}={0,2}/',
    ];

    // private $riskWeights = [
    //     'potential_rce' => 10,
    //     'obfuscation' => 8,
    //     'file_manipulation' => 7,
    //     'suspicious_endpoints' => 6,
    //     'sql_injection' => 9,
    //     'xss_injection' => 7,
    //     'information_disclosure' => 5
    // ];

    private $riskWeights = [
        'potential_rce' => 10,
        'obfuscation' => 8,
        'file_manipulation' => 7,
        'suspicious_endpoints' => 6,
        'sql_injection' => 9,
        'xss_injection' => 7,
        'information_disclosure' => 5,
        'contextual_risk' => 4
    ];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function detect($url)
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('URL tidak valid');
            }

            try {
                // Set strict timeout and security options
                $response = $this->client->request('GET', $url, [
                    'timeout' => 10,
                    'connect_timeout' => 5,
                    'verify' => false, // Set to false to handle self-signed certs
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.5',
                        'Connection' => 'close'
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
                $headers = $response->getHeaders();

                // Initialize results array with safe defaults
                $results = [
                    'detected' => false,
                    'risk_level' => 'Rendah',
                    'risk_score' => 0,
                    'confidence_level' => 0,
                    'details' => [
                        'risks' => [],
                        'metrics' => [
                            'total_risk' => 0,
                            'max_severity' => 0,
                            'threat_categories' => 0
                        ],
                        'recommendations' => []
                    ],
                    'metadata' => [
                        'scan_timestamp' => time(),
                        'response_code' => $response->getStatusCode(),
                        'content_type' => $headers['Content-Type'][0] ?? 'unknown',
                        'redirect_chain' => $response->getHeader('X-Guzzle-Redirect-History')
                    ]
                ];

                // Perform security analysis with error handling for each step
                try {
                    $crawler = new Crawler($html);

                    // Static Analysis
                    try {
                        $staticAnalysis = $this->analyzeSecurityRisks($crawler, $html);
                    } catch (\Exception $e) {
                        Log::warning('Static analysis error: ' . $e->getMessage());
                        $staticAnalysis = [];
                    }

                    // Dynamic Analysis
                    try {
                        $dynamicAnalysis = $this->performDynamicAnalysis($html);
                    } catch (\Exception $e) {
                        Log::warning('Dynamic analysis error: ' . $e->getMessage());
                        $dynamicAnalysis = [];
                    }

                    // Sensitive Data Detection
                    try {
                        $sensitiveData = $this->detectSensitiveData($html);
                    } catch (\Exception $e) {
                        Log::warning('Sensitive data detection error: ' . $e->getMessage());
                        $sensitiveData = [];
                    }

                    // Attack Vector Analysis
                    try {
                        $attackVectors = $this->locateBackdoorAttackPoints($html, $url);
                    } catch (\Exception $e) {
                        Log::warning('Attack vector analysis error: ' . $e->getMessage());
                        $attackVectors = [];
                    }

                    // Header Analysis
                    try {
                        $headerRisks = $this->analyzeHeaders($headers);
                    } catch (\Exception $e) {
                        Log::warning('Header analysis error: ' . $e->getMessage());
                        $headerRisks = [];
                    }

                    // Consolidate all analysis results
                    $consolidatedRisks = $this->consolidateRisks([
                        'static_analysis' => $staticAnalysis,
                        'dynamic_analysis' => $dynamicAnalysis,
                        'sensitive_data' => $sensitiveData,
                        'attack_vectors' => $attackVectors,
                        'header_risks' => $headerRisks
                    ]);

                    // Calculate risk metrics
                    $riskMetrics = $this->calculateRiskMetrics($consolidatedRisks);

                    // Generate final recommendations
                    $recommendations = $this->generateRecommendations($consolidatedRisks);

                    // Update results with analysis data
                    $results['detected'] = $riskMetrics['total_risk'] > 0;
                    $results['risk_level'] = $this->determineRiskLevel($riskMetrics);
                    $results['risk_score'] = $riskMetrics['risk_score'];
                    $results['confidence_level'] = $riskMetrics['confidence_level'];
                    $results['details'] = [
                        'risks' => $consolidatedRisks,
                        'metrics' => $riskMetrics,
                        'recommendations' => $recommendations
                    ];
                } catch (\Exception $e) {
                    Log::error('Analysis error: ' . $e->getMessage());
                    $results['error'] = 'Error during analysis: ' . $e->getMessage();
                }

                return $results;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                Log::error('Request failed: ' . $e->getMessage());
                return [
                    'detected' => false,
                    'risk_level' => 'Error',
                    'error' => 'Failed to fetch URL: ' . $e->getMessage(),
                    'error_type' => 'RequestException'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Backdoor detection error: ' . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'detected' => false,
                'risk_level' => 'Error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'details' => [
                    'risks' => [],
                    'metrics' => [
                        'total_risk' => 0,
                        'risk_score' => 0,
                        'confidence_level' => 0
                    ],
                    'recommendations' => []
                ]
            ];
        }
    }

    private function locateBackdoorAttackPoints($html, $url)
    {
        $attackVectors = [
            'entry_points' => $this->analyzeEntryPoints($html),
            'command_injection' => $this->analyzeCommandInjectionPoints($html),
            'file_operations' => $this->analyzeFileOperationPoints($html),
            'authentication_bypass' => $this->analyzeAuthenticationBypass($html),
            'hidden_functionality' => $this->analyzeHiddenFunctionality($html, $url),
            'persistence_mechanisms' => $this->analyzePersistenceMechanisms($html)
        ];

        return array_filter($attackVectors);
    }

    private function analyzeEntryPoints($html)
    {
        $entryPoints = [];

        // Check for vulnerable GET/POST parameters
        $parameterPatterns = [
            'remote_file_inclusion' => [
                '/\b(?:include|require)(?:_once)?\s*\(\s*\$_(GET|POST|REQUEST)\[[\'"](file|path|url|page)[\'"]/',
                '/\bfile_get_contents\s*\(\s*\$_(GET|POST|REQUEST)\[[\'"](url|source)[\'"]/'
            ],
            'code_execution' => [
                '/\b(?:eval|assert|create_function)\s*\(\s*\$_(GET|POST|REQUEST)\[/',
                '/\b(?:call_user_func|call_user_func_array)\s*\(\s*\$_(GET|POST|REQUEST)\[/'
            ],
            'command_execution' => [
                '/\b(?:system|exec|shell_exec|passthru|popen)\s*\(\s*\$_(GET|POST|REQUEST)\[/',
                '/\`[^`]*\$_(GET|POST|REQUEST)\[[^\]]+\][^`]*\`/'
            ]
        ];

        foreach ($parameterPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $entryPoints[$type] = array_merge(
                        $entryPoints[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        // Check for hidden form fields
        if (preg_match_all('/<input[^>]+type=[\'"]hidden[\'"](.*?)>/i', $html, $matches)) {
            $entryPoints['hidden_inputs'] = array_unique($matches[0]);
        }

        // Check for vulnerable cookie handling
        if (preg_match_all('/\$_COOKIE\[[^\]]+\]\s*(?:!==?|==|===)/', $html, $matches)) {
            $entryPoints['cookie_validation'] = array_unique($matches[0]);
        }

        return $entryPoints;
    }

    private function analyzeCommandInjectionPoints($html)
    {
        $injectionPoints = [];

        // Direct command execution
        $commandPatterns = [
            'system_commands' => [
                '/\b(?:system|exec|shell_exec|passthru)\s*\([^)]*(?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].*(?:\||&|;).*[\'"])\s*\)/i',
                '/\b(?:popen|proc_open)\s*\([^)]*(?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].*(?:\||&|;).*[\'"])\s*\)/i'
            ],
            'backtick_execution' => [
                '/`[^`]*(?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].*(?:\||&|;).*[\'"])[^`]*`/i'
            ],
            'indirect_execution' => [
                '/\$(?:cmd|command|exec|script)\s*=\s*(?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].*(?:\||&|;).*[\'"])/i',
                '/\b(?:escapeshell_cmd|escapeshell_arg)\s*\([^)]*\$[^)]+\)/i'
            ]
        ];

        foreach ($commandPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $injectionPoints[$type] = array_merge(
                        $injectionPoints[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        return $injectionPoints;
    }

    private function analyzeFileOperationPoints($html)
    {
        $fileOperations = [];

        // File operation patterns
        $patterns = [
            'file_uploads' => [
                '/move_uploaded_file\s*\([^)]+\)/',
                '/<input[^>]+type=[\'"]file[\'"]/i',
                '/\$_FILES\[[^\]]+\]/'
            ],
            'file_manipulation' => [
                '/\b(?:fopen|file_get_contents|file_put_contents)\s*\([^)]*\$[^)]+\)/',
                '/\b(?:unlink|rename|copy|mkdir|rmdir|chmod|touch)\s*\([^)]*\$[^)]+\)/',
                '/\b(?:readfile|fwrite|fputs)\s*\([^)]*\$[^)]+\)/'
            ],
            'directory_traversal' => [
                '/\.\.[\/\\\]/',
                '/%2e%2e%2f/',
                '/\.\.%2f/',
                '/\b(?:glob|opendir|readdir|scandir)\s*\([^)]*\$[^)]+\)/'
            ],
            'file_inclusion' => [
                '/\b(?:include|require)(?:_once)?\s*\([^)]*(?:\.\.\/|\$[^)]+)\)/',
                '/\b(?:include|require)(?:_once)?\s*[\'"][^\'"]+\$[^\'"]+[\'"]\s*/',
                '/\b(?:virtual|readfile)\s*\([^)]*(?:\.\.\/|\$[^)]+)\)/'
            ]
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $fileOperations[$type] = array_merge(
                        $fileOperations[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        return $fileOperations;
    }

    private function analyzeAuthenticationBypass($html)
    {
        $bypassPoints = [];

        // Authentication bypass patterns
        $patterns = [
            'auth_checks' => [
                '/\b(?:isset|empty)\s*\(\s*\$(?:_SESSION|_COOKIE)\[[\'"](?:user|admin|logged_in|auth)[\'"]\]\s*\)/',
                '/\$(?:_SESSION|_COOKIE)\[[\'"](?:user|admin|logged_in|auth)[\'"]\]\s*(?:===?|!==?)\s*/',
                '/\b(?:password_verify|md5|sha1)\s*\([^)]+\)/'
            ],
            'session_manipulation' => [
                '/session_(?:start|destroy|regenerate_id|unset)\s*\(/',
                '/\$_SESSION\[[^\]]+\]\s*=/',
                '/session_id\s*\([^)]*\)/'
            ],
            'cookie_manipulation' => [
                '/setcookie\s*\([^)]+\)/',
                '/\$_COOKIE\[[^\]]+\]\s*=/',
                '/(?:httponly|secure)\s*=\s*(?:true|false)/'
            ],
            'weak_comparisons' => [
                '/==\s*[\'"](?:1|true|yes|on)[\'"]\s*/',
                '/!=\s*[\'"](?:0|false|no|off)[\'"]\s*/',
                '/\b(?:is_null|isset|empty)\s*\([^)]+\)\s*(?:===?|!==?)\s*(?:true|false)/'
            ]
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $bypassPoints[$type] = array_merge(
                        $bypassPoints[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        return $bypassPoints;
    }

    private function analyzeHiddenFunctionality($html, $url)
    {
        $hiddenFeatures = [];

        // Check for hidden or obfuscated functionality
        $patterns = [
            'encoded_strings' => [
                '/base64_decode\s*\([^)]+\)/',
                '/(?:eval|assert|create_function)\s*\(\s*(?:base64_decode|gzinflate|str_rot13)\s*\([^)]+\)\s*\)/',
                '/\\\\x[0-9a-fA-F]{2}/',
                '/[a-zA-Z0-9+\/]{32,}={0,2}/' // Base64 encoded content
            ],
            'conditional_execution' => [
                '/\bif\s*\(\s*\$(?:_GET|_POST|_REQUEST|_COOKIE)\[[^\]]+\]\s*(?:===?|!==?)\s*[\'"][^\'"]+[\'"]\s*\)/',
                '/\bswitch\s*\(\s*\$(?:_GET|_POST|_REQUEST|_COOKIE)\[[^\]]+\]\s*\)/',
                '/\bpreg_replace\s*\(\s*[\'"]\/[^\/]+\/e[\'"]\s*,/'
            ],
            'timing_based' => [
                '/\b(?:sleep|usleep|time_nanosleep)\s*\([^)]+\)/',
                '/\b(?:set_time_limit|max_execution_time)\s*\([^)]+\)/',
                '/\@ignore_user_abort\s*\([^)]*\)/'
            ]
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $hiddenFeatures[$type] = array_merge(
                        $hiddenFeatures[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        // Check for suspicious URL parameters
        $urlParts = parse_url($url);
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            foreach ($queryParams as $param => $value) {
                if (preg_match('/(?:cmd|exec|system|shell|admin|debug|test|backdoor)/i', $param)) {
                    $hiddenFeatures['suspicious_parameters'][$param] = $value;
                }
            }
        }

        return $hiddenFeatures;
    }

    private function analyzePersistenceMechanisms($html)
    {
        $persistenceMechanisms = [];

        // Check for persistence mechanisms
        $patterns = [
            'file_creation' => [
                '/\b(?:fopen|file_put_contents)\s*\([^)]*(?:\.htaccess|\.php|\.jsp|\.asp)[\'"]\s*[,\)]/i',
                '/\b(?:copy|rename|move_uploaded_file)\s*\([^)]*(?:\.php|\.jsp|\.asp)[\'"]\s*[,\)]/i'
            ],
            'scheduled_tasks' => [
                '/\b(?:cron|at|schtasks)\b/',
                '/\* \* \* \* \*/', // Cron syntax
                '/\b(?:system|exec|shell_exec)\s*\([^)]*(?:crontab|at|schtasks)[^)]*\)/'
            ],
            'auto_execution' => [
                '/(?:auto_prepend_file|auto_append_file)\s*=/',
                '/php_value\s+(?:auto_prepend_file|auto_append_file)/',
                '/\b(?:register_shutdown_function|register_tick_function)\s*\(/'
            ],
            'service_manipulation' => [
                '/\b(?:service|systemctl|initd)\b/',
                '/\/etc\/(?:init\.d|systemd|rc\.d)/',
                '/\b(?:daemon|background|nohup)\b/'
            ]
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $persistenceMechanisms[$type] = array_merge(
                        $persistenceMechanisms[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        // Check for file permissions modification
        if (preg_match_all('/chmod\s*\(\s*[^)]+,\s*0[0-7]{3}\s*\)/', $html, $matches)) {
            $persistenceMechanisms['permission_modification'] = array_unique($matches[0]);
        }

        return $persistenceMechanisms;
    }

    private function performDynamicAnalysis($html)
    {
        $analysis = [];

        try {
            $analysis['dynamic_code_execution'] = $this->analyzeDynamicCodeExecution($html);
        } catch (\Exception $e) {
            Log::warning('Error analyzing dynamic code execution: ' . $e->getMessage());
            $analysis['dynamic_code_execution'] = [];
        }

        try {
            $analysis['data_flows'] = $this->analyzeDataFlows($html);
        } catch (\Exception $e) {
            Log::warning('Error analyzing data flows: ' . $e->getMessage());
            $analysis['data_flows'] = [];
        }

        try {
            $analysis['suspicious_functions'] = $this->analyzeSuspiciousFunctions($html);
        } catch (\Exception $e) {
            Log::warning('Error analyzing suspicious functions: ' . $e->getMessage());
            $analysis['suspicious_functions'] = [];
        }

        try {
            $analysis['encoding_patterns'] = $this->analyzeEncodingPatterns($html);
        } catch (\Exception $e) {
            Log::warning('Error analyzing encoding patterns: ' . $e->getMessage());
            $analysis['encoding_patterns'] = [];
        }

        try {
            $analysis['runtime_modifications'] = $this->analyzeRuntimeModifications($html);
        } catch (\Exception $e) {
            Log::warning('Error analyzing runtime modifications: ' . $e->getMessage());
            $analysis['runtime_modifications'] = [];
        }

        return array_filter($analysis);
    }

    private function analyzeDynamicCodeExecution($html)
    {
        $risks = [];

        // Check for dynamic function calls
        $dynamicFunctionPatterns = [
            '/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\([^)]*\)/', // Variable functions
            '/\bcreate_function\s*\([^)]+\)/', // Dynamic function creation
            '/\beval\s*\([^)]+\)/', // eval usage
            '/\bassert\s*\([^)]+\)/', // assert usage
            '/\bcall_user_func(?:_array)?\s*\([^)]+\)/' // call_user_func usage
        ];

        foreach ($dynamicFunctionPatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $risks['dynamic_functions'] = array_merge(
                    $risks['dynamic_functions'] ?? [],
                    array_unique($matches[0])
                );
            }
        }

        // Check for dynamic includes
        $dynamicIncludePatterns = [
            '/include(?:_once)?\s*\([^)]*\$[^)]+\)/',
            '/require(?:_once)?\s*\([^)]*\$[^)]+\)/'
        ];

        foreach ($dynamicIncludePatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $risks['dynamic_includes'] = array_merge(
                    $risks['dynamic_includes'] ?? [],
                    array_unique($matches[0])
                );
            }
        }

        return $risks;
    }

    private function analyzeDataFlows($html)
    {
        $flows = [];

        // Track variable assignments from user input
        $inputSources = [
            'GET' => '\$_GET\s*\[\s*[\'"][^\'"]+[\'"]\s*\]',
            'POST' => '\$_POST\s*\[\s*[\'"][^\'"]+[\'"]\s*\]',
            'REQUEST' => '\$_REQUEST\s*\[\s*[\'"][^\'"]+[\'"]\s*\]',
            'COOKIE' => '\$_COOKIE\s*\[\s*[\'"][^\'"]+[\'"]\s*\]',
            'FILES' => '\$_FILES\s*\[\s*[\'"][^\'"]+[\'"]\s*\]'
        ];

        foreach ($inputSources as $source => $pattern) {
            try {
                if (preg_match_all('/' . preg_quote($pattern, '/') . '/i', $html, $matches)) {
                    $flows['input_sources'][$source] = array_unique($matches[0]);
                }
            } catch (\Exception $e) {
                Log::warning("Error analyzing input source $source: " . $e->getMessage());
                continue;
            }
        }

        // Track data flow to sensitive operations
        $sensitiveOperations = [
            'database' => '\b(?:mysql_query|mysqli_query|PDO::query)\b',
            'filesystem' => '\b(?:file_get_contents|file_put_contents|fopen|unlink)\b',
            'system' => '\b(?:system|exec|shell_exec|passthru)\b',
            'output' => '\b(?:echo|print|die|exit)\b'
        ];

        foreach ($sensitiveOperations as $type => $pattern) {
            try {
                $escapedPattern = '/' . preg_quote($pattern, '/') . '.*?\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/i';
                if (preg_match_all($escapedPattern, $html, $matches)) {
                    $flows['sensitive_operations'][$type] = array_unique($matches[0]);
                }
            } catch (\Exception $e) {
                Log::warning("Error analyzing sensitive operation $type: " . $e->getMessage());
                continue;
            }
        }

        return $flows;
    }

    private function analyzeSuspiciousFunctions($html)
    {
        $suspiciousFunctions = [
            'system_commands' => [
                'system',
                'exec',
                'shell_exec',
                'passthru',
                'proc_open',
                'popen',
                'pcntl_exec',
                'expect_popen'
            ],
            'code_execution' => [
                'eval',
                'assert',
                'create_function',
                'include',
                'require',
                'include_once',
                'require_once'
            ],
            'information_disclosure' => [
                'phpinfo',
                'posix_getpwuid',
                'posix_getgrgid',
                'get_current_user',
                'getcwd',
                'getmyuid',
                'getmygid'
            ],
            'network_functions' => [
                'fsockopen',
                'pfsockopen',
                'stream_socket_client',
                'curl_exec',
                'file_get_contents'
            ]
        ];

        $findings = [];
        foreach ($suspiciousFunctions as $category => $functions) {
            $pattern = '/\b(' . implode('|', $functions) . ')\s*\([^)]*\)/i';
            if (preg_match_all($pattern, $html, $matches)) {
                $findings[$category] = array_unique($matches[0]);
            }
        }

        return $findings;
    }

    private function analyzeEncodingPatterns($html)
    {
        $patterns = [
            'base64' => [
                '/base64_decode\s*\([^)]+\)/',
                '/[a-zA-Z0-9+\/]{32,}={0,2}/' // Base64 encoded strings
            ],
            'hex' => [
                '/0x[0-9A-Fa-f]+/',
                '/\\\\x[0-9A-Fa-f]{2}/'
            ],
            'url' => [
                '/urldecode\s*\([^)]+\)/',
                '/%[0-9A-Fa-f]{2}/',
            ],
            'compression' => [
                '/gzinflate\s*\([^)]+\)/',
                '/gzuncompress\s*\([^)]+\)/',
                '/gzdecode\s*\([^)]+\)/'
            ]
        ];

        $findings = [];
        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $findings[$type] = array_merge(
                        $findings[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        return $findings;
    }

    private function analyzeRuntimeModifications($html)
    {
        $modifications = [];

        // Check for variable modifications
        $variableModificationPatterns = [
            'globals' => '/\$GLOBALS\s*\[[^\]]+\]\s*=/',
            'superglobals' => '/\$_(?:GET|POST|REQUEST|SERVER|COOKIE|SESSION|ENV)\s*\[[^\]]+\]\s*=/',
            'reference_modification' => '/\${\s*[\'"][^\'"]+[\'"]\s*}/',
            'variable_variables' => '/\$\s*\{?\s*\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\}?/'
        ];

        foreach ($variableModificationPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $modifications[$type] = array_unique($matches[0]);
            }
        }

        // Check for function/class modifications
        $runtimeModificationPatterns = [
            'function_modifications' => [
                '/function_exists\s*\([^)]+\)/',
                '/create_function\s*\([^)]+\)/'
            ],
            'class_modifications' => [
                '/class_exists\s*\([^)]+\)/',
                '/get_class\s*\([^)]+\)/',
                '/get_class_methods\s*\([^)]+\)/'
            ],
            'constant_modifications' => [
                '/define\s*\([^)]+\)/',
                '/constant\s*\([^)]+\)/'
            ]
        ];

        foreach ($runtimeModificationPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    $modifications[$type] = array_merge(
                        $modifications[$type] ?? [],
                        array_unique($matches[0])
                    );
                }
            }
        }

        return $modifications;
    }

    private function analyzeSecurityRisks(Crawler $crawler, $html)
    {
        $risks = [
            'script_analysis' => $this->analyzeScriptTags($crawler),
            'link_analysis' => $this->analyzeLinkTags($crawler),
            'form_analysis' => $this->analyzeFormElements($crawler),
            'iframe_analysis' => $this->analyzeIframeElements($crawler),
            'meta_analysis' => $this->analyzeMetaTags($crawler),
            'comment_analysis' => $this->analyzeHtmlComments($crawler),
            'input_validation' => $this->analyzeInputValidation($html),
            'security_headers' => $this->checkSecurityHeaders($html),
        ];

        return array_filter($risks);
    }

    private function analyzeScriptTags(Crawler $crawler)
    {
        $scriptRisks = [];
        $crawler->filter('script')->each(function ($node) use (&$scriptRisks) {
            $content = $node->attr('src') ?? $node->text();

            // Check for inline scripts
            if (!$node->attr('src')) {
                if (preg_match('/\b(eval|document\.write|innerHTML)\b/i', $content)) {
                    $scriptRisks['dangerous_methods'][] = $content;
                }

                if (preg_match('/\b(localStorage|sessionStorage|cookie)\b/i', $content)) {
                    $scriptRisks['storage_access'][] = $content;
                }
            }

            // Check external scripts
            if ($src = $node->attr('src')) {
                if (!preg_match('/^https:/', $src)) {
                    $scriptRisks['insecure_sources'][] = $src;
                }
                if (preg_match('/\.(php|asp|jsp)\b/i', $src)) {
                    $scriptRisks['dynamic_sources'][] = $src;
                }
            }
        });

        return $scriptRisks;
    }

    private function analyzeLinkTags(Crawler $crawler)
    {
        $linkRisks = [];
        $crawler->filter('a')->each(function ($node) use (&$linkRisks) {
            $href = $node->attr('href');
            if ($href) {
                if (preg_match('/\.(php|asp|jsp)\?.*=(.*)/i', $href)) {
                    $linkRisks['suspicious_parameters'][] = $href;
                }
                if (preg_match('/javascript:/i', $href)) {
                    $linkRisks['javascript_protocol'][] = $href;
                }
                if (preg_match('/(admin|shell|backdoor|upload)/i', $href)) {
                    $linkRisks['suspicious_endpoints'][] = $href;
                }
            }
        });

        return $linkRisks;
    }

    private function analyzeFormElements(Crawler $crawler)
    {
        $formRisks = [];
        $crawler->filter('form')->each(function ($node) use (&$formRisks) {
            $action = $node->attr('action');

            // Check for insecure form submission
            if ($action && !preg_match('/^https:/', $action)) {
                $formRisks['insecure_form_action'][] = $action;
            }

            // Check for file upload capabilities
            $node->filter('input[type="file"]')->each(function ($input) use (&$formRisks) {
                $formRisks['file_upload_forms'][] = [
                    'name' => $input->attr('name'),
                    'accept' => $input->attr('accept')
                ];
            });
        });

        return $formRisks;
    }

    private function analyzeIframeElements(Crawler $crawler)
    {
        $iframeRisks = [];
        $crawler->filter('iframe')->each(function ($node) use (&$iframeRisks) {
            $src = $node->attr('src');
            if ($src) {
                if (!preg_match('/^https:/', $src)) {
                    $iframeRisks['insecure_iframe'][] = $src;
                }
                if (preg_match('/(data|javascript):/i', $src)) {
                    $iframeRisks['suspicious_protocol'][] = $src;
                }
            }
        });

        return $iframeRisks;
    }

    private function analyzeMetaTags(Crawler $crawler)
    {
        $metaRisks = [];
        $crawler->filter('meta')->each(function ($node) use (&$metaRisks) {
            $content = $node->attr('content');
            if ($content) {
                if (preg_match('/(php|asp|jsp)/i', $content)) {
                    $metaRisks['suspicious_meta_content'][] = $content;
                }
            }
        });

        return $metaRisks;
    }

    private function analyzeHtmlComments(Crawler $crawler)
    {
        $commentRisks = [];
        preg_match_all('/<!--(.*?)-->/s', $crawler->html(), $matches);

        foreach ($matches[1] as $comment) {
            if (preg_match('/(password|user|admin|key|token|secret)/i', $comment)) {
                $commentRisks['sensitive_data'][] = $comment;
            }
            if (preg_match('/(todo|hack|fixme|backdoor)/i', $comment)) {
                $commentRisks['suspicious_comments'][] = $comment;
            }
        }

        return $commentRisks;
    }

    private function analyzeInputValidation($html)
    {
        $validationRisks = [];

        // Check for unfiltered inputs
        if (preg_match_all('/\$_(GET|POST|REQUEST|COOKIE)\[([^\]]+)\]/', $html, $matches)) {
            foreach ($matches[0] as $index => $match) {
                $validationRisks['unfiltered_inputs'][] = [
                    'type' => $matches[1][$index],
                    'variable' => $matches[2][$index]
                ];
            }
        }

        // Check for unsafe variable handling
        if (preg_match_all('/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*=\s*\$_(GET|POST|REQUEST|COOKIE)/', $html, $matches)) {
            $validationRisks['direct_assignments'] = $matches[0];
        }

        return $validationRisks;
    }

    private function checkSecurityHeaders($html)
    {
        $headerRisks = [];

        // Check for security-related meta tags
        if (!preg_match('/<meta\s+http-equiv="X-Frame-Options"/i', $html)) {
            $headerRisks['missing_headers'][] = 'X-Frame-Options';
        }

        if (!preg_match('/<meta\s+http-equiv="Content-Security-Policy"/i', $html)) {
            $headerRisks['missing_headers'][] = 'Content-Security-Policy';
        }

        return $headerRisks;
    }

    private function analyzeHeaders(array $headers)
    {
        $suspiciousHeaders = [];
        $securityHeaders = [
            'X-Frame-Options',
            'X-XSS-Protection',
            'X-Content-Type-Options',
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'Referrer-Policy'
        ];

        // Check for missing security headers
        foreach ($securityHeaders as $header) {
            if (!isset($headers[$header])) {
                $suspiciousHeaders['missing_security_headers'][] = $header;
            }
        }

        // Check for suspicious server information
        if (isset($headers['Server'])) {
            $serverInfo = $headers['Server'][0];
            if (preg_match('/(PHP|Apache|nginx).*/i', $serverInfo)) {
                $suspiciousHeaders['server_info_disclosure'] = $serverInfo;
            }
        }

        // Check for sensitive information in headers
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                if ($this->containsSensitiveData($value)) {
                    $suspiciousHeaders['sensitive_header_data'][] = $name;
                }
            }
        }

        return $suspiciousHeaders;
    }

    private function containsSensitiveData($content)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        return false;
    }

    private function detectSensitiveData($html)
    {
        $sensitiveData = [];

        // Common sensitive data patterns
        $patterns = [
            'api_keys' => [
                '/[\'"](?:[a-zA-Z0-9_-]{20,40})[\'"]/', // Generic API key pattern
                '/(?:api|key|token|secret)[\'"]\s*(?::|=>|=)\s*[\'"][a-zA-Z0-9_-]{20,}[\'"]/i',
                '/(?:sk|pk)_(?:test|live)_[0-9a-zA-Z]{24,}/' // Payment provider API keys
            ],
            'credentials' => [
                '/(?:password|passwd|pwd)[\'"]\s*(?::|=>|=)\s*[\'"][^\'"\s]+[\'"]/i',
                '/(?:username|user|admin)[\'"]\s*(?::|=>|=)\s*[\'"][^\'"\s]+[\'"]/i',
                '/"?(?:connection_string|conn_str|connection)"?\s*(?::|=>|=)\s*[\'"][^\'"\n]{10,}[\'"]/i'
            ],
            'personal_data' => [
                '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', // Email addresses
                '/(?:\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]\d{3}[\s.-]\d{4}/', // Phone numbers
                '/\b\d{3}-\d{2}-\d{4}\b/' // Social Security Numbers
            ],
            'authentication' => [
                '/(?:bearer|token|auth)\s+[a-zA-Z0-9_-]{20,}/',
                '/(?:jwt|authorization)[\'"]\s*(?::|=>|=)\s*[\'"][a-zA-Z0-9._-]{20,}[\'"]/i',
                '/eyJ[A-Za-z0-9-_]+\.eyJ[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+/' // JWT pattern
            ],
            'cryptographic' => [
                '/(?:ssh-rsa|ssh-dss)\s+[A-Za-z0-9+\/]+[=]{0,2}/',
                '/-----BEGIN(?:\s\w+)?\sPRIVATE\sKEY-----[^-]+-----END(?:\s\w+)?\sPRIVATE\sKEY-----/',
                '/[a-f0-9]{32}/', // MD5 hashes
                '/[a-f0-9]{40}/', // SHA1 hashes
                '/[a-f0-9]{64}/'  // SHA256 hashes
            ]
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    // Filter out false positives and commonly known test values
                    $filtered = array_filter($matches[0], [$this, 'isLikelySensitiveData']);
                    if (!empty($filtered)) {
                        $sensitiveData[$type] = array_merge(
                            $sensitiveData[$type] ?? [],
                            array_unique($filtered)
                        );
                    }
                }
            }
        }

        return $sensitiveData;
    }

    // private function isLikelySensitiveData($value)
    // {
    //     // Common test/example values to exclude
    //     $exclusions = [
    //         'example',
    //         'test',
    //         'sample',
    //         'demo',
    //         'dummy',
    //         'username',
    //         'password',
    //         'user@example.com',
    //         '123-45-6789',
    //         '123.456.7890'
    //     ];

    //     foreach ($exclusions as $exclusion) {
    //         if (stripos($value, $exclusion) !== false) {
    //             return false;
    //         }
    //     }

    //     // Minimum entropy check for potential sensitive data
    //     $entropy = $this->calculateStringEntropy($value);
    //     return strlen($value) > 8 && $entropy > 3.0;
    // }

    private function isLikelySensitiveData($value)
    {
        // Expanded exclusions
        $exclusions = [
            'example',
            'test',
            'sample',
            'demo',
            'dummy',
            'username',
            'password',
            'user@example.com',
            '123-45-6789',
            '123.456.7890'
        ];

        // Case-insensitive exclusion check
        foreach ($exclusions as $exclusion) {
            if (stripos($value, $exclusion) !== false) {
                return false;
            }
        }

        // Enhanced entropy calculation
        $entropy = $this->calculateStringEntropy($value);
        $length = strlen($value);

        // Multi-factor sensitive data detection
        $criteria = [
            $length > 8,           // Minimum length
            $entropy > 3.0,        // High entropy
            preg_match('/[^\w\s]/', $value), // Contains special characters
            preg_match('/\d/', $value),      // Contains numbers
            preg_match('/[A-Z]/', $value)    // Contains uppercase
        ];

        // Require at least 3 out of 5 criteria
        $criteriaScore = count(array_filter($criteria));

        return $criteriaScore >= 3;
    }

    private function calculateStringEntropy($string)
    {
        $frequencies = array_count_values(str_split($string));
        $entropy = 0;
        $length = strlen($string);

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }
    private function consolidateRisks(array $analyses)
    {
        $consolidated = [];

        foreach ($analyses as $type => $risks) {
            if (!empty($risks)) {
                try {
                    $severity = $this->calculateSeverity($risks);
                    $confidence = $this->calculateConfidence($risks);

                    $consolidated[$type] = [
                        'findings' => $risks,
                        'severity' => $severity,
                        'confidence' => $confidence
                    ];
                } catch (\Exception $e) {
                    Log::warning("Error consolidating risks for type $type: " . $e->getMessage());
                    $consolidated[$type] = [
                        'findings' => [],
                        'severity' => ['score' => 0, 'max_weight' => 0],
                        'confidence' => 0
                    ];
                }
            }
        }

        return $consolidated;
    }

    private function calculateSeverity($risks)
    {
        if (empty($risks)) return ['score' => 0, 'max_weight' => 0];

        $totalWeight = 0;
        $maxWeight = 0;
        $riskCategories = 0;

        foreach ($risks as $category => $findings) {
            // Pastikan kategori risiko ada dalam bobot
            if (isset($this->riskWeights[$category])) {
                $weight = $this->riskWeights[$category];

                // Hitung jumlah temuan yang valid
                $findingsCount = is_array($findings) ?
                    count(array_filter($findings, function ($finding) {
                        // Filter temuan yang memiliki konten
                        return !empty($finding) &&
                            (is_array($finding) ? count($finding) > 0 : strlen(trim($finding)) > 0);
                    })) : ($findings ? 1 : 0);

                // Bobot bertambah dengan logaritma jumlah temuan
                $logarithmicWeight = $weight * log($findingsCount + 1, 2);

                $totalWeight += $logarithmicWeight;
                $maxWeight = max($maxWeight, $weight);

                // Hitung kategori risiko unik
                if ($findingsCount > 0) {
                    $riskCategories++;
                }
            }
        }

        return [
            'score' => round($totalWeight, 2),
            'max_weight' => $maxWeight,
            'risk_categories' => $riskCategories
        ];
    }

    private function calculateConfidence($risks)
    {
        if (empty($risks)) return 0;

        $confidenceFactors = [];
        $uniquePatterns = [];

        foreach ($risks as $category => $findings) {
            if (is_array($findings)) {
                try {
                    // Analisis kompleksitas dan keunikan
                    $categoryConfidence = 0;
                    $categoryPatterns = [];

                    foreach ($findings as $finding) {
                        // Konversi temuan ke string untuk hash
                        $patternStr = is_array($finding) ? json_encode($finding) : (string)$finding;
                        $hash = md5($patternStr);

                        // Ukur keunikan dan kompleksitas
                        if (!in_array($hash, $uniquePatterns)) {
                            $uniquePatterns[] = $hash;
                            $categoryPatterns[] = $patternStr;

                            // Berikan bobot tambahan untuk pola kompleks
                            $complexityBonus = strlen($patternStr) > 50 ? 1.5 : 1;
                            $categoryConfidence += $complexityBonus;
                        }
                    }

                    // Normalisasi kepercayaan kategori
                    $categoryConfidenceNormalized = min(
                        100,
                        round(($categoryConfidence / max(1, count($findings))) * 100, 2)
                    );

                    $confidenceFactors[$category] = [
                        'confidence' => $categoryConfidenceNormalized,
                        'unique_patterns' => count($categoryPatterns)
                    ];
                } catch (\Exception $e) {
                    Log::warning("Confidence calculation error in $category: " . $e->getMessage());
                }
            }
        }

        // Hitung kepercayaan akhir dengan pembobotan
        $totalConfidence = 0;
        $totalWeight = 0;

        foreach ($confidenceFactors as $category => $data) {
            // Berikan bobot tambahan untuk kategori dengan banyak pola unik
            $categoryWeight = $this->riskWeights[$category] ?? 1;
            $uniquePatternBonus = log($data['unique_patterns'] + 1, 2);

            $totalConfidence += $data['confidence'] * $categoryWeight * $uniquePatternBonus;
            $totalWeight += $categoryWeight;
        }

        return $totalWeight > 0 ?
            min(100, round($totalConfidence / $totalWeight, 2)) : 0;
    }


    private function calculateRiskMetrics($consolidatedRisks)
    {
        $severityResult = $this->calculateSeverity(
            array_map(function ($risk) {
                return $risk['findings'] ?? [];
            }, $consolidatedRisks)
        );

        // Perhitungan skor risiko yang lebih kompleks
        $riskScore = 0;
        $totalWeight = array_sum($this->riskWeights);
        $riskCategories = $severityResult['risk_categories'];

        // Skor risiko berbasis logaritma dan kategori
        $baseRiskScore = $severityResult['score'] / $totalWeight * 100;
        $categoryMultiplier = log($riskCategories + 1, 2);

        $riskScore = min(100, $baseRiskScore * $categoryMultiplier);

        // Perhitungan kepercayaan
        $confidenceScore = $this->calculateConfidence($consolidatedRisks);

        return [
            'total_risk' => $severityResult['score'],
            'risk_score' => round($riskScore, 2),
            'max_severity' => $severityResult['max_weight'],
            'confidence_level' => round($confidenceScore, 2),
            'threat_categories' => $riskCategories,
            'category_breakdown' => array_map(function ($risk) {
                return $risk['findings'] ?? [];
            }, $consolidatedRisks)
        ];
    }


    // private function normalizeRiskScore($totalRisk)
    // {
    //     // Normalize risk score to 0-100 scale
    //     $maxPossibleRisk = array_sum($this->riskWeights);
    //     return min(100, round(($totalRisk / $maxPossibleRisk) * 100));
    // }


    private function determineRiskLevel($metrics)
    {
        $riskScore = $metrics['risk_score'];
        $confidenceLevel = $metrics['confidence_level'];
        $threatCategories = $metrics['threat_categories'];
        $maxSeverity = $metrics['max_severity'];

        // Perhitungan tingkat risiko yang lebih komprehensif
        $baseRiskFactors = [
            'risk_score' => $riskScore / 100,
            'confidence' => $confidenceLevel / 100,
            'categories' => $threatCategories / 5, // Normalisasi jumlah kategori
            'max_severity' => $maxSeverity / max(array_values($this->riskWeights))
        ];

        // Bobot untuk setiap faktor
        $riskFactorWeights = [
            'risk_score' => 0.4,
            'confidence' => 0.2,
            'categories' => 0.2,
            'max_severity' => 0.2
        ];

        // Hitung skor akhir
        $adjustedScore = 0;
        foreach ($baseRiskFactors as $factor => $value) {
            $adjustedScore += $value * $riskFactorWeights[$factor] * 100;
        }

        // Tentukan level risiko
        if ($adjustedScore >= 85) {
            $riskLevel = 'Kritis';
        } elseif ($adjustedScore >= 70) {
            $riskLevel = 'Tinggi';
        } elseif ($adjustedScore >= 50) {
            $riskLevel = 'Sedang';
        } elseif ($adjustedScore >= 30) {
            $riskLevel = 'Rendah';
        } else {
            $riskLevel = 'Minimal';
        }

        // Tambahkan kualifikasi kepercayaan
        if ($confidenceLevel < 40) {
            $riskLevel .= ' (Kepercayaan Rendah)';
        } elseif ($confidenceLevel > 80) {
            $riskLevel .= ' (Kepercayaan Tinggi)';
        }

        return $riskLevel;
    }

    private function generateRecommendations($consolidatedRisks)
    {
        $recommendations = [];

        $recommendationTemplates = [
            'potential_rce' => [
                'title' => 'Mitigasi Remote Code Execution',
                'actions' => [
                    'Terapkan whitelist untuk input yang diizinkan',
                    'Gunakan prepared statements untuk query database',
                    'Nonaktifkan fungsi-fungsi berbahaya seperti eval() dan system()',
                    'Terapkan WAF (Web Application Firewall)',
                    'Validasi dan sanitasi semua input user'
                ]
            ],
            'obfuscation' => [
                'title' => 'Penanganan Kode Terenkripsi',
                'actions' => [
                    'Periksa semua file PHP untuk kode yang diobfuskasi',
                    'Implementasi monitoring file untuk perubahan mencurigakan',
                    'Gunakan tool deobfuscation untuk analisis lebih lanjut',
                    'Terapkan kontrol versi untuk melacak perubahan kode'
                ]
            ],
            'file_manipulation' => [
                'title' => 'Keamanan File System',
                'actions' => [
                    'Batasi izin file dan direktori',
                    'Implementasi whitelist untuk upload file',
                    'Validasi semua operasi file',
                    'Gunakan lokasi penyimpanan yang aman di luar web root'
                ]
            ],
            'suspicious_endpoints' => [
                'title' => 'Pengamanan Endpoint',
                'actions' => [
                    'Audit dan hapus endpoint yang tidak digunakan',
                    'Terapkan rate limiting',
                    'Implementasi logging untuk semua endpoint sensitif',
                    'Gunakan autentikasi yang kuat'
                ]
            ],
            'information_disclosure' => [
                'title' => 'Pencegahan Kebocoran Informasi',
                'actions' => [
                    'Sembunyikan informasi versi dan teknologi',
                    'Konfigurasi header keamanan yang tepat',
                    'Nonaktifkan tampilan error detail',
                    'Enkripsi data sensitif'
                ]
            ]
        ];

        foreach ($consolidatedRisks as $category => $analysis) {
            if (!empty($analysis['findings']) && isset($recommendationTemplates[$category])) {
                $template = $recommendationTemplates[$category];
                $recommendations[] = [
                    'category' => $category,
                    'title' => $template['title'],
                    'severity' => $analysis['severity']['score'] ?? 'unknown',
                    'actions' => $template['actions'],
                    'priority' => $this->calculatePriority($analysis['severity']['score'] ?? 0, $analysis['confidence'] ?? 0)
                ];
            }
        }

        // Sort recommendations by priority
        usort($recommendations, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return $recommendations;
    }

    private function calculatePriority($severity, $confidence)
    {
        // Calculate priority score (0-100)
        return round(($severity * 0.7) + ($confidence * 0.3));
    }

    private function performDeepAnalysis($html, $url)
    {
        $analysis = [
            'network_indicators' => $this->analyzeNetworkIndicators($html),
            'code_patterns' => $this->analyzeCodePatterns($html),
            'behavioral_analysis' => $this->analyzeBehavioralPatterns($html),
            'context_analysis' => $this->analyzeContext($html, $url)
        ];

        return $this->correlateFindings($analysis);
    }

    private function correlateFindings($analysis)
    {
        $correlatedResults = [];
        $threatScore = 0;
        $confidenceScore = 0;

        // Weight factors for different types of findings
        $weights = [
            'network_indicators' => 0.8,
            'code_patterns' => 0.9,
            'behavioral_analysis' => 0.7,
            'context_analysis' => 0.6
        ];

        foreach ($analysis as $category => $findings) {
            if (!empty($findings)) {
                $categoryWeight = $weights[$category] ?? 0.5;
                $findingCount = $this->countFindings($findings);

                $threatScore += $findingCount * $categoryWeight;

                // Calculate confidence based on correlation between different categories
                $correlatedResults[$category] = [
                    'findings' => $findings,
                    'count' => $findingCount,
                    'weight' => $categoryWeight,
                    'confidence' => $this->calculateCategoryConfidence($findings)
                ];
            }
        }

        // Calculate overall confidence score
        $confidenceScore = $this->calculateOverallConfidence($correlatedResults);

        return [
            'correlated_findings' => $correlatedResults,
            'threat_score' => min(100, $threatScore),
            'confidence_score' => $confidenceScore,
            'correlation_summary' => $this->generateCorrelationSummary($correlatedResults)
        ];
    }

    private function countFindings($findings)
    {
        $count = 0;
        foreach ($findings as $finding) {
            if (is_array($finding)) {
                $count += $this->countFindings($finding);
            } else {
                $count++;
            }
        }
        return $count;
    }

    private function calculateCategoryConfidence($findings)
    {
        // Base confidence on the diversity and quantity of findings
        $uniquePatterns = 0;
        $totalFindings = 0;

        foreach ($findings as $type => $items) {
            if (is_array($items)) {
                $uniquePatterns += count(array_unique($items));
                $totalFindings += count($items);
            }
        }

        if ($totalFindings === 0) return 0;

        // Calculate confidence score (0-100)
        $diversity = $uniquePatterns / max(1, $totalFindings);
        return min(100, round($diversity * 100));
    }

    private function calculateOverallConfidence($correlatedResults)
    {
        $weightedConfidence = 0;
        $totalWeight = 0;

        foreach ($correlatedResults as $category => $result) {
            $weightedConfidence += $result['confidence'] * $result['weight'];
            $totalWeight += $result['weight'];
        }

        return $totalWeight > 0 ? round($weightedConfidence / $totalWeight) : 0;
    }

    private function generateCorrelationSummary($correlatedResults)
    {
        $summary = [];

        // Analyze correlations between different types of findings
        foreach ($correlatedResults as $category => $result) {
            $summary[$category] = [
                'severity' => $this->calculateSeverity($result['count']),
                'confidence' => $result['confidence'],
                'related_findings' => $this->findRelatedPatterns($category, $correlatedResults)
            ];
        }

        return $summary;
    }

    private function findRelatedPatterns($category, $results)
    {
        $related = [];

        foreach ($results as $otherCategory => $result) {
            if ($otherCategory !== $category) {
                $intersection = $this->findPatternIntersection(
                    $results[$category]['findings'],
                    $result['findings']
                );

                if (!empty($intersection)) {
                    $related[$otherCategory] = $intersection;
                }
            }
        }

        return $related;
    }

    private function findPatternIntersection($findings1, $findings2)
    {
        $intersection = [];

        foreach ($findings1 as $key1 => $value1) {
            foreach ($findings2 as $key2 => $value2) {
                if (is_array($value1) && is_array($value2)) {
                    $common = array_intersect($value1, $value2);
                    if (!empty($common)) {
                        $intersection[$key1 . '_' . $key2] = $common;
                    }
                }
            }
        }

        return $intersection;
    }
    private function analyzeNetworkIndicators($html)
    {
        $indicators = [];

        // Extract all URLs and IP addresses
        preg_match_all('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $html, $urls);
        preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $html, $ips);

        // Analyze found URLs
        foreach ($urls[0] as $url) {
            if ($this->issupricious_url($url)) {
                $indicators['suspicious_urls'][] = $url;
            }
        }

        // Analyze found IPs
        foreach ($ips[0] as $ip) {
            if ($this->isSuspiciousIP($ip)) {
                $indicators['suspicious_ips'][] = $ip;
            }
        }

        return $indicators;
    }

    private function issupricious_url($url)
    {
        $suspicious_patterns = [
            '/(?:pastebin\.com|github\.io|raw\.githubusercontent\.com)/i',
            '/(?:\/shell|\/cmd|\/exec|\/system)/i',
            '/(?:\.php\?(?:cmd|exec|system)=)/i'
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    private function isSuspiciousIP($ip)
    {
        // Check if IP is private or localhost
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function analyzeCodePatterns($html)
    {
        return [
            'encryption_patterns' => $this->detectEncryptionPatterns($html),
            'shell_patterns' => $this->detectShellPatterns($html),
            'evasion_techniques' => $this->detectEvasionTechniques($html)
        ];
    }

    private function detectEncryptionPatterns($html)
    {
        $patterns = [
            '/\b(?:mcrypt_|openssl_|crypt)\w+\s*\(/i',
            '/\b(?:AES|DES|RC4|Blowfish)\b/i',
            '/\\x[0-9A-F]{2}/i'
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $found)) {
                $matches = array_merge($matches, $found[0]);
            }
        }

        return array_unique($matches);
    }

    private function detectShellPatterns($html)
    {
        $patterns = [
            '/\b(?:shell_exec|system|passthru|exec)\s*\([^)]*\$[^)]+\)/i',
            '/`[^`]*\$[^`]+`/',
            '/2>&1|>/dev/null/'
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $found)) {
                $matches = array_merge($matches, $found[0]);
            }
        }

        return array_unique($matches);
    }

    private function detectEvasionTechniques($html)
    {
        $techniques = [];

        // String splitting/concatenationy
        if (preg_match_all('/[\'"][^\'"]+[\'"]\s*\.\s*[\'"][^\'"]+[\'"]/', $html, $matches)) {
            $techniques['string_concatenation'] = $matches[0];
        }

        // Variable function calls
        if (preg_match_all('/\$\w+\s*\([^)]*\)/', $html, $matches)) {
            $techniques['variable_functions'] = $matches[0];
        }

        // Character code usage
        if (preg_match_all('/chr\(\d+\)/', $html, $matches)) {
            $techniques['char_code'] = $matches[0];
        }

        return $techniques;
    }

    private function analyzeBehavioralPatterns($html)
    {
        return [
            'file_operations' => $this->detectFileOperations($html),
            'network_operations' => $this->detectNetworkOperations($html),
            'data_exfiltration' => $this->detectDataExfiltration($html)
        ];
    }

    private function detectFileOperations($html)
    {
        $patterns = [
            'read' => '/\b(?:fopen|file_get_contents|readfile)\s*\([^)]+\)/',
            'write' => '/\b(?:fwrite|file_put_contents)\s*\([^)]+\)/',
            'delete' => '/\b(?:unlink|rmdir)\s*\([^)]+\)/',
            'create' => '/\b(?:mkdir|touch)\s*\([^)]+\)/'
        ];

        $operations = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $operations[$type] = $matches[0];
            }
        }

        return $operations;
    }

    private function detectNetworkOperations($html)
    {
        $patterns = [
            'connections' => '/\b(?:fsockopen|stream_socket_client|curl_exec)\s*\([^)]+\)/',
            'downloads' => '/\b(?:wget|curl|file_get_contents)\s*\([^)]*(?:http|ftp)[^)]+\)/',
            'uploads' => '/\b(?:move_uploaded_file|curl_setopt)\s*\([^)]+CURLOPT_UPLOAD[^)]+\)/'
        ];

        $operations = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $operations[$type] = $matches[0];
            }
        }

        return $operations;
    }

    private function detectDataExfiltration($html)
    {
        $patterns = [
            'database' => '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b.*\bFROM\b/i',
            'credentials' => '/\b(?:password|user|admin|root)\s*=/',
            'system' => '/\b(?:phpinfo|get_current_user|getcwd)\s*\(\)/'
        ];

        $exfiltration = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $exfiltration[$type] = $matches[0];
            }
        }

        return $exfiltration;
    }

    private function analyzeContext($html, $url)
    {
        $urlParts = parse_url($url);
        $domain = $urlParts['host'] ?? '';

        return [
            'domain_analysis' => $this->analyzeDomain($domain),
            'path_analysis' => $this->analyzePath($urlParts['path'] ?? ''),
            'content_analysis' => $this->analyzeContent($html)
        ];
    }

    private function analyzeDomain($domain)
    {
        // Implement domain reputation check
        // This could integrate with external APIs in a real implementation
        return [
            'is_suspicious' => false,
            'reputation_score' => 0,
            'known_malicious' => false
        ];
    }

    private function analyzePath($path)
    {
        $suspicious_paths = [
            '/admin',
            '/backup',
            '/test',
            '/temp',
            '/old',
            '/wp-content',
            '/wp-includes',
            '/wp-admin'
        ];

        return [
            'is_suspicious' => in_array(strtolower($path), $suspicious_paths),
            'depth' => substr_count($path, '/'),
            'hidden' => strpos($path, '.') === 0
        ];
    }

    private function analyzeContent($html)
    {
        return [
            'size' => strlen($html),
            'entropy' => $this->calculateEntropy($html),
            'comment_analysis' => $this->analyzeComments($html)
        ];
    }

    private function calculateEntropy($string)
    {
        $frequencies = array_count_values(str_split($string));
        $entropy = 0;
        $length = strlen($string);

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    private function analyzeComments($html)
    {
        preg_match_all('/<!--.*?-->/s', $html, $matches);
        return [
            'count' => count($matches[0]),
            'suspicious' => $this->findSuspiciousComments($matches[0])
        ];
    }

    private function findSuspiciousComments($comments)
    {
        $suspicious = [];
        $patterns = [
            '/(?:hack|backdoor|shell|password|todo)/i',
            '/(?:@author|@version|@copyright)/i',
            '/(?:\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/i'
        ];

        foreach ($comments as $comment) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $comment)) {
                    $suspicious[] = $comment;
                    break;
                }
            }
        }

        return array_unique($suspicious);
    }
}
