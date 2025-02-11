<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
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
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,/;q=0.8',
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
                '/\b(?:include|require)(?:once)?\s*\(\s*\$(GET|POST|REQUEST)\[[\'"](file|path|url|page)[\'"]/',
                '/\bfile_get_contents\s*\(\s*\$_(GET|POST|REQUEST)\[[\'"](url|source)[\'"]/'
            ],
            'code_execution' => [
                '/\b(?:eval|assert|create_function)\s*\(\s*\$_(GET|POST|REQUEST)\[/',
                '/\b(?:call_user_func|call_user_func_array)\s*\(\s*\$_(GET|POST|REQUEST)\[/'
            ],
            'command_execution' => [
                '/\b(?:system|exec|shell_exec|passthru|popen)\s*\(\s*\$_(GET|POST|REQUEST)\[/',
                '/\[^]\$_(GET|POST|REQUEST)\[[^\]]+\][^]\/'
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
                '/\b(?:system|exec|shell_exec|passthru)\s*\([^)](?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].(?:\||&|;).[\'"])\s\)/i',
                '/\b(?:popen|proc_open)\s*\([^)](?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].(?:\||&|;).[\'"])\s\)/i'
            ],
            'backtick_execution' => [
                '/[^](?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].(?:\||&|;).[\'"])[^]/i'
            ],
            'indirect_execution' => [
                '/\$(?:cmd|command|exec|script)\s*=\s*(?:\$(?:_GET|_POST|_REQUEST|_COOKIE)|[\'"].(?:\||&|;).[\'"])/i',
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
                '/\b(?:fopen|file_put_contents)\s*\([^)](?:\.htaccess|\.php|\.jsp|\.asp)[\'"]\s[,\)]/i',
                '/\b(?:copy|rename|move_uploaded_file)\s*\([^)](?:\.php|\.jsp|\.asp)[\'"]\s[,\)]/i'
            ],
            'scheduled_tasks' => [
                '/\b(?:cron|at|schtasks)\b/',
                '/\* \* \* \* \*/', // Cron syntax
                '/\b(?:system|exec|shell_exec)\s*\([^)](?:crontab|at|schtasks)[^)]\)/'
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
            '/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]\s\([^)]*\)/', // Variable functions
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
                $escapedPattern = '/' . preg_quote($pattern, '/') . '.?\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]/i';
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
            'variable_variables' => '/\$\s*\{?\s*\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]\s\}?/'
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
            'script_analysis' => $this->enhancedScriptAnalysis($crawler),
            'link_analysis' => $this->enhancedLinkAnalysis($crawler),
            'form_analysis' => $this->enhancedFormAnalysis($crawler),
            'iframe_analysis' => $this->enhancedIframeAnalysis($crawler),
            'meta_analysis' => $this->enhancedMetaAnalysis($crawler),
            'comment_analysis' => $this->enhancedCommentAnalysis($crawler),
            'input_validation' => $this->enhancedInputValidation($html),
            'security_headers' => $this->enhancedSecurityHeaderCheck($html),
            'dynamic_execution' => $this->analyzeDynamicExecution($html),
            'data_exfiltration' => $this->analyzeDataExfiltration($html),
            'persistence_mechanisms' => $this->analyzePersistenceMechanisms($html)
        ];

        return array_filter($risks);
    }

    private function analyzeDataExfiltration($html)
    {
        return [
            'database_operations' => $this->analyzeDatabaseOperations($html),
            'network_operations' => $this->analyzeNetworkOperations($html),
            'file_operations' => $this->analyzeFileOperations($html),
            'sensitive_data_patterns' => $this->analyzeSensitiveDataPatterns($html),
            'data_encoding' => $this->analyzeDataEncoding($html),
            'communication_channels' => $this->analyzeCommsChannels($html),
            'storage_operations' => $this->analyzeStorageOperations($html),
            'context_analysis' => $this->analyzeDataContext($html)
        ];
    }

    private function analyzeSensitiveDataPatterns($html)
    {
        $sensitiveDataRisks = [
            'credentials' => [],
            'personal_info' => [],
            'system_data' => []
        ];

        // Credential and authentication patterns
        $credentialPatterns = [
            'password_patterns' => '/(?:password|passwd|pwd)[\s:=]+[\'"][^\'"\s]{6,}[\'"]/i',
            'api_keys' => '/(?:api_key|secret|token)[\s:=]+[\'"][^\'"\s]{20,}[\'"]/i',
            'jwt_tokens' => '/eyJ[A-Za-z0-9-]+\.[A-Za-z0-9-]+\.[A-Za-z0-9-_]+/i'
        ];

        foreach ($credentialPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $sensitiveDataRisks['credentials'][$type] = array_unique($matches[0]);
            }
        }

        // Personal information patterns
        $personalInfoPatterns = [
            'email_addresses' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/i',
            'phone_numbers' => '/\b(?:\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]\d{3}[\s.-]\d{4}\b/',
            'social_security' => '/\b\d{3}-\d{2}-\d{4}\b/',
            'credit_cards' => '/\b(?:\d{4}[-\s]?){3}\d{4}\b/'
        ];

        foreach ($personalInfoPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $sensitiveDataRisks['personal_info'][$type] = array_unique($matches[0]);
            }
        }

        // System and configuration data
        $systemDataPatterns = [
            'server_info' => '/\b(?:phpinfo\(\)|get_current_user\(\)|getcwd\(\))\b/i',
            'system_paths' => '/(?:\/(?:etc|var|home|root|usr)\/[^\s]+)/i',
            'environment_vars' => '/\$(?:_SERVER|_ENV)\[[\'"][^\'"]+[\'"]\]/i'
        ];

        foreach ($systemDataPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $sensitiveDataRisks['system_data'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($sensitiveDataRisks);
    }


    private function analyzeDatabaseOperations($html)
    {
        $databaseRisks = [
            'sql_queries' => [],
            'connection_attempts' => [],
            'data_manipulation' => []
        ];

        // Detect potential SQL queries and database operations
        $sqlPatterns = [
            'select_queries' => '/\b(?:SELECT|SELECT\s+DISTINCT)\s+.*\sFROM\b/i',
            'insert_queries' => '/\bINSERT\s+(?:INTO|IGNORE\s+INTO)\b/i',
            'update_queries' => '/\bUPDATE\s+\w+\s+SET\b/i',
            'delete_queries' => '/\bDELETE\s+FROM\b/i',
            'join_operations' => '/\b(?:INNER\s+JOIN|LEFT\s+JOIN|RIGHT\s+JOIN)\b/i'
        ];

        foreach ($sqlPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $databaseRisks['sql_queries'][$type] = array_unique($matches[0]);
            }
        }

        // Detect database connection and manipulation functions
        $connectionPatterns = [
            'mysql_functions' => '/\b(?:mysql_connect|mysqli_connect|PDO::__construct)\s*\(/i',
            'connection_params' => '/(?:host|database|username|password)\s*=\s*[\'"][^\'"]*/i',
            'prepared_statements' => '/\b(?:prepare|bindParam|execute)\s*\(/i'
        ];

        foreach ($connectionPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $databaseRisks['connection_attempts'][$type] = array_unique($matches[0]);
            }
        }

        // Advanced data manipulation detection
        $manipulationPatterns = [
            'bulk_operations' => '/\b(?:INSERT\s+MULTIPLE|BULK\s+INSERT)\b/i',
            'data_export' => '/\b(?:SELECT\s+.*\bINTO\s+OUTFILE)\b/i',
            'stored_procedures' => '/\bCALL\s+\w+\s*\(/i'
        ];

        foreach ($manipulationPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $databaseRisks['data_manipulation'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($databaseRisks);
    }

    private function analyzeNetworkOperations($html)
    {
        $networkRisks = [
            'remote_connections' => [],
            'data_transfer' => [],
            'protocol_usage' => []
        ];

        // Detect network connection functions and techniques
        $connectionPatterns = [
            'socket_connections' => '/\b(?:fsockopen|stream_socket_client|socket_connect)\s*\(/i',
            'curl_operations' => '/\bcurl_(?:init|setopt|exec|close)\s*\(/i',
            'remote_file_access' => '/\b(?:file_get_contents|fopen)\s*\([\'"](?:https?|ftp|sftp):/i',
            'dns_lookup' => '/\b(?:gethostbyname|dns_get_record)\s*\(/i'
        ];

        foreach ($connectionPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $networkRisks['remote_connections'][$type] = array_unique($matches[0]);
            }
        }

        // Detect data transfer techniques
        $transferPatterns = [
            'data_upload' => '/\b(?:move_uploaded_file|curl_setopt\s*\([^)]+CURLOPT_UPLOAD)\b/i',
            'remote_download' => '/\b(?:wget|curl)\s+[\'"][^\'"]*/i',
            'file_transfer' => '/\b(?:ftp_(?:put|get)|ssh2_(?:sftp_put|sftp_get))\s*\(/i'
        ];

        foreach ($transferPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $networkRisks['data_transfer'][$type] = array_unique($matches[0]);
            }
        }

        // Analyze protocol and data transmission methods
        $protocolPatterns = [
            'http_methods' => '/\b(?:GET|POST|HEAD|OPTIONS|PUT|DELETE)\s+HTTP\/\d\.\d/i',
            'encryption_protocols' => '/\b(?:SSL|TLS|HTTPS)\b/i',
            'api_calls' => '/\b(?:curl_setopt\s*\([^)]+CURLOPT_[A-Z_]+)\b/i'
        ];

        foreach ($protocolPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $networkRisks['protocol_usage'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($networkRisks);
    }

    private function analyzeFileOperations($html)
    {
        $fileRisks = [
            'read_operations' => [],
            'write_operations' => [],
            'deletion_operations' => [],
            'permission_changes' => []
        ];

        // File read operations detection
        $readPatterns = [
            'file_reading' => '/\b(?:file_get_contents|fopen|readfile|fread)\s*\(/i',
            'directory_listing' => '/\b(?:scandir|glob|readdir)\s*\(/i',
            'file_parsing' => '/\b(?:parse_ini_file|simplexml_load_file)\s*\(/i'
        ];

        foreach ($readPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $fileRisks['read_operations'][$type] = array_unique($matches[0]);
            }
        }

        // File write operations detection
        $writePatterns = [
            'file_writing' => '/\b(?:file_put_contents|fwrite|fputs)\s*\(/i',
            'file_creation' => '/\b(?:touch|tmpfile)\s*\(/i',
            'log_writing' => '/\b(?:error_log|syslog)\s*\(/i'
        ];

        foreach ($writePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $fileRisks['write_operations'][$type] = array_unique($matches[0]);
            }
        }

        // File deletion and manipulation operations
        $deletePatterns = [
            'file_deletion' => '/\b(?:unlink|rmdir|delete_file)\s*\(/i',
            'file_truncation' => '/\b(?:ftruncate)\s*\(/i',
            'file_moving' => '/\b(?:rename|copy)\s*\(/i'
        ];

        foreach ($deletePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $fileRisks['deletion_operations'][$type] = array_unique($matches[0]);
            }
        }

        // File permission and ownership changes
        $permissionPatterns = [
            'permission_modification' => '/\b(?:chmod|chown|chgrp)\s*\(/i',
            'access_control' => '/\b(?:is_readable|is_writable|is_executable)\s*\(/i'
        ];

        foreach ($permissionPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $fileRisks['permission_changes'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($fileRisks);
    }



    private function analyzeDataEncoding($html)
    {
        $encodingRisks = [
            'base64_encoding' => [],
            'url_encoding' => [],
            'hex_encoding' => [],
            'obfuscation_techniques' => []
        ];

        // Base64 encoding detection
        $base64Patterns = [
            'base64_functions' => '/\b(?:base64_encode|base64_decode)\s*\(/i',
            'base64_strings' => '/[a-zA-Z0-9+\/]{32,}={0,2}/',
            'base64_file_handling' => '/\bfile_get_contents\s*\(["\']data:.*base64,/i'
        ];

        foreach ($base64Patterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $encodingRisks['base64_encoding'][$type] = array_unique($matches[0]);
            }
        }

        // URL encoding detection
        $urlEncodingPatterns = [
            'urlencode_functions' => '/\b(?:urlencode|urldecode)\s*\(/i',
            'url_encoded_data' => '/%[0-9A-Fa-f]{2}/',
            'complex_url_encoding' => '/(?:\+|\%20|\%0A|\%0D)/'
        ];

        foreach ($urlEncodingPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $encodingRisks['url_encoding'][$type] = array_unique($matches[0]);
            }
        }

        // Hex encoding detection
        $hexEncodingPatterns = [
            'hex_functions' => '/\b(?:hex2bin|bin2hex)\s*\(/i',
            'hex_strings' => '/0x[0-9A-Fa-f]+/',
            'unicode_escaping' => '/\\\\x[0-9A-Fa-f]{2}/'
        ];

        foreach ($hexEncodingPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $encodingRisks['hex_encoding'][$type] = array_unique($matches[0]);
            }
        }

        // Advanced obfuscation techniques
        $obfuscationPatterns = [
            'string_splitting' => '/[\'"][^\'"]+[\'"]\s*\.\s*[\'"][^\'"]+[\'"]/',
            'char_code_conversion' => '/\bchr\(\d+\)/',
            'rot_encoding' => '/\bstr_rot13\s*\(/i',
            'compression' => '/\b(?:gzinflate|gzuncompress|gzdecode)\s*\(/i'
        ];

        foreach ($obfuscationPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $encodingRisks['obfuscation_techniques'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($encodingRisks);
    }

    private function analyzeCommsChannels($html)
    {
        $communicationRisks = [
            'messaging_channels' => [],
            'external_communication' => [],
            'inter_process_communication' => []
        ];

        // Messaging and communication detection
        $messagingPatterns = [
            'email_functions' => '/\b(?:mail|imap_open|smtp_connect)\s*\(/i',
            'websocket_usage' => '/\b(?:WebSocket|socket\.io)\b/i',
            'pub_sub_channels' => '/\b(?:publish|subscribe|channel)\b/i'
        ];

        foreach ($messagingPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $communicationRisks['messaging_channels'][$type] = array_unique($matches[0]);
            }
        }

        // External communication methods
        $externalCommunicationPatterns = [
            'remote_apis' => '/\b(?:curl_exec|file_get_contents)\s*\([\'"]https?:/i',
            'external_services' => '/\b(?:Amazon|Google|Facebook|Twitter)\.(?:API|Service)\b/i',
            'cloud_storage' => '/\b(?:S3|Azure|GCP)\s*(?:upload|download)\b/i'
        ];

        foreach ($externalCommunicationPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $communicationRisks['external_communication'][$type] = array_unique($matches[0]);
            }
        }

        // Inter-process communication detection
        $ipcPatterns = [
            'shared_memory' => '/\b(?:shmop_open|ftok|shmget)\s*\(/i',
            'message_queues' => '/\b(?:msg_send|msg_receive|msg_get_queue)\s*\(/i',
            'process_signals' => '/\b(?:posix_kill|pcntl_signal)\s*\(/i',
            'pipes_and_sockets' => '/\b(?:popen|proc_open|stream_socket_pair)\s*\(/i',
            'named_pipes' => '/\b(?:fifo|mkfifo)\b/i'
        ];

        foreach ($ipcPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $communicationRisks['inter_process_communication'][$type] = array_unique($matches[0]);
            }
        }

        // Advanced communication channel detection
        $advancedChannelPatterns = [
            'unix_domain_sockets' => '/\b(?:AF_UNIX|PF_UNIX)\b/i',
            'network_ipc' => '/\b(?:TCP|UDP|socket\(\))\b/i',
            'rpc_mechanisms' => '/\b(?:XML-RPC|JSON-RPC|gRPC)\b/i'
        ];

        foreach ($advancedChannelPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $communicationRisks['inter_process_communication'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($communicationRisks);
    }

    private function analyzeStorageOperations($html)
    {
        $storageRisks = [
            'persistent_storage' => [],
            'caching_mechanisms' => [],
            'data_persistence' => []
        ];

        // Persistent storage detection
        $persistentStoragePatterns = [
            'file_storage' => '/\b(?:file_put_contents|fwrite|file_append)\s*\(/i',
            'serialization' => '/\b(?:serialize|unserialize)\s*\(/i',
            'database_storage' => '/\b(?:INSERT|UPDATE)\s+INTO\s+\w+\b/i'
        ];

        foreach ($persistentStoragePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $storageRisks['persistent_storage'][$type] = array_unique($matches[0]);
            }
        }

        // Caching mechanisms detection
        $cachingPatterns = [
            'memory_cache' => '/\b(?:memcache_set|redis_set)\s*\(/i',
            'session_storage' => '/\b(?:$_SESSION\[|session_start\(\))\b/i',
            'local_storage' => '/\b(?:localStorage\.setItem|sessionStorage\.setItem)\b/i'
        ];

        foreach ($cachingPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $storageRisks['caching_mechanisms'][$type] = array_unique($matches[0]);
            }
        }

        // Data persistence techniques
        $persistencePatterns = [
            'cookie_storage' => '/\b(?:setcookie|$_COOKIE)\b/i',
            'permanent_storage' => '/\b(?:IndexedDB|WebSQL|localStorage)\b/i',
            'state_management' => '/\b(?:setState|dispatch|reducer)\b/i'
        ];

        foreach ($persistencePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $storageRisks['data_persistence'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($storageRisks);
    }

    private function analyzeDataContext($html)
    {
        $contextRisks = [
            'data_origin' => [],
            'transmission_methods' => [],
            'context_leakage' => []
        ];

        // Data origin and source tracking
        $originPatterns = [
            'user_input_sources' => '/\$_(?:GET|POST|REQUEST|COOKIE|FILES)\[/i',
            'server_variables' => '/\$_SERVER\[[\'"][^\'"]+[\'"]\]/i',
            'environment_vars' => '/\$_ENV\[[\'"][^\'"]+[\'"]\]/i'
        ];

        foreach ($originPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $contextRisks['data_origin'][$type] = array_unique($matches[0]);
            }
        }

        // Data transmission and flow detection
        $transmissionPatterns = [
            'data_binding' => '/\b(?:bind|map|reduce)\s*\(/i',
            'data_transformation' => '/\b(?:filter|transform|convert)\s*\(/i',
            'stream_processing' => '/\b(?:pipe|stream|buffer)\b/i'
        ];

        foreach ($transmissionPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $contextRisks['transmission_methods'][$type] = array_unique($matches[0]);
            }
        }

        // Potential context information leakage
        $leakagePatterns = [
            'debug_info' => '/\b(?:var_dump|print_r|debug_backtrace)\s*\(/i',
            'error_exposure' => '/\b(?:error_reporting|display_errors)\s*\(/i',
            'sensitive_context' => '/\b(?:phpinfo|get_defined_vars)\s*\(/i',
            'configuration_leak' => '/\b(?:ini_get|get_cfg_var)\s*\(/i'
        ];

        foreach ($leakagePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $contextRisks['context_leakage'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($contextRisks);
    }

    private function enhancedSecurityHeaderCheck($html)
    {
        $headerRisks = [
            'missing_headers' => [],
            'insecure_headers' => [],
            'header_disclosure' => []
        ];

        // Check for security headers in meta tags
        $securityHeaders = [
            'X-Frame-Options',
            'X-XSS-Protection',
            'X-Content-Type-Options',
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'Referrer-Policy'
        ];

        foreach ($securityHeaders as $header) {
            if (!preg_match('/<meta[^>]+http-equiv=[\'"]' . preg_quote($header) . '[\'"][^>]*>/i', $html)) {
                $headerRisks['missing_headers'][] = $header;
            }
        }

        // Check for potentially insecure header values
        if (preg_match_all('/<meta[^>]+http-equiv=[\'"]([^\'"]+)[\'"][^>]+content=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $index => $header) {
                $content = $matches[2][$index];
                if (!$this->isSecureHeaderValue($header, $content)) {
                    $headerRisks['insecure_headers'][] = [
                        'header' => $header,
                        'value' => $content
                    ];
                }
            }
        }

        // Check for server information disclosure
        if (preg_match('/<meta[^>]+name=[\'"]generator[\'"][^>]+content=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $match)) {
            $headerRisks['header_disclosure'][] = $match[1];
        }

        return array_filter($headerRisks);
    }

    // Fix missing analyzeDynamicExecution function
    private function analyzeDynamicExecution($html)
    {
        $risks = [
            'dynamic_code' => [],
            'eval_usage' => [],
            'runtime_includes' => []
        ];

        // Check for dynamic code execution
        $codePatterns = [
            'eval_usage' => '/\beval\s*\([^)]+\)/',
            'create_function' => '/\bcreate_function\s*\([^)]+\)/',
            'callable_execution' => '/\bcall_user_func(?:_array)?\s*\([^)]+\)/',
            'dynamic_includes' => '/include(?:_once)?\s*\([^)]*\$[^)]+\)/',
            'variable_functions' => '/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]\s\([^)]*\)/'
        ];

        foreach ($codePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $risks['dynamic_code'][$type] = array_unique($matches[0]);
            }
        }

        // Check for runtime code modification
        $runtimePatterns = [
            'register_shutdown_function' => '/\bregister_shutdown_function\s*\([^)]+\)/',
            'register_tick_function' => '/\bregister_tick_function\s*\([^)]+\)/',
            'spl_autoload_register' => '/\bspl_autoload_register\s*\([^)]+\)/',
        ];

        foreach ($runtimePatterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $risks['runtime_includes'][$type] = array_unique($matches[0]);
            }
        }

        return array_filter($risks);
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
                '/(?:sk|pk)(?:test|live)[0-9a-zA-Z]{24,}/' // Payment provider API keys
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
                '/eyJ[A-Za-z0-9-]+\.eyJ[A-Za-z0-9-]+\.[A-Za-z0-9-_]+/' // JWT pattern
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
        $severityMultipliers = [
            'critical' => 2.0,
            'high' => 1.5,
            'medium' => 1.0,
            'low' => 0.5
        ];

        foreach ($risks as $category => $findings) {
            if (isset($this->riskWeights[$category])) {
                $weight = $this->riskWeights[$category];
                $findingsCount = $this->countValidFindings($findings);

                // Enhanced severity calculation with pattern recognition
                $patternSeverity = $this->calculatePatternSeverity($findings);
                $contextualRisk = $this->assessContextualRisk($category, $findings);

                // Logarithmic scaling with base adjustment
                $baseLog = max(2, log($findingsCount + 1, 2));
                $adjustedWeight = $weight * $baseLog;

                // Apply severity multipliers
                $severityMultiplier = $severityMultipliers[$patternSeverity] ?? 1.0;
                $finalWeight = $adjustedWeight * $severityMultiplier * (1 + $contextualRisk);

                $totalWeight += $finalWeight;
                $maxWeight = max($maxWeight, $weight);

                if ($findingsCount > 0) {
                    $riskCategories++;
                }
            }
        }

        return [
            'score' => round($totalWeight, 2),
            'max_weight' => $maxWeight,
            'risk_categories' => $riskCategories,
            // Tambahkan pengecekan untuk mencegah division by zero
            'normalized_score' => $maxWeight > 0
                ? min(100, round(($totalWeight / ($maxWeight * 3)) * 100, 2))
                : 0
        ];
    }

    private function calculatePatternSeverity($findings)
    {
        // Pastikan $findings adalah array
        if (!is_array($findings)) {
            $findings = [$findings];
        }

        $criticalPatterns = [
            '/(?:system|exec|shell_exec|passthru|eval)\s*\(/',
            '/(?:base64_decode|str_rot13|gzinflate)\s*\(.*\$/',
            '/\b(?:phpinfo|get_current_user|getcwd)\s*\(\)/',
        ];

        $highPatterns = [
            '/(?:file_get_contents|file_put_contents|fopen)\s*\(/',
            '/\$_(?:GET|POST|REQUEST|COOKIE)\[/',
            '/(?:chmod|chown|chgrp)\s*\(/',
        ];

        $mediumPatterns = [
            '/(?:include|require)(?:_once)?\s*\(/',
            '/mysql_query|mysqli_query|PDO::query/',
            '/move_uploaded_file\s*\(/',
        ];

        // Gabungkan semua pola untuk pencarian
        $allPatterns = array_merge($criticalPatterns, $highPatterns, $mediumPatterns);

        // Periksa apakah ada pola yang cocok dalam temuan
        foreach ($allPatterns as $pattern) {
            foreach ($findings as $finding) {
                if (is_array($finding)) {
                    // Jika $finding adalah array, cek setiap elemennya
                    foreach ($finding as $item) {
                        if (preg_match($pattern, (string)$item)) {
                            // Tentukan tingkat risiko berdasarkan pola
                            if (in_array($pattern, $criticalPatterns)) return 'critical';
                            if (in_array($pattern, $highPatterns)) return 'high';
                            if (in_array($pattern, $mediumPatterns)) return 'medium';
                        }
                    }
                } else {
                    // Jika $finding bukan array
                    if (preg_match($pattern, (string)$finding)) {
                        // Tentukan tingkat risiko berdasarkan pola
                        if (in_array($pattern, $criticalPatterns)) return 'critical';
                        if (in_array($pattern, $highPatterns)) return 'high';
                        if (in_array($pattern, $mediumPatterns)) return 'medium';
                    }
                }
            }
        }

        // Kembalikan 'low' jika tidak ada pola yang cocok
        return 'low';
    }
    private function assessContextualRisk($category, $findings)
    {
        $contextScore = 0;

        // Risk patterns with weights
        $riskPatterns = [
            // Critical context patterns
            '/(?:admin|root|sudo)\b/i' => 0.4,
            '/(?:password|passwd|pwd)\b/i' => 0.35,
            '/(?:bash|sh|cmd|powershell)\b/i' => 0.45,

            // High risk context patterns
            '/(?:wget|curl|fetch)\b/i' => 0.3,
            '/(?:chmod|chown)\s+[0-7]{3,4}/i' => 0.35,
            '/(?:nc|netcat|ncat)\b/i' => 0.4,

            // Medium risk context patterns
            '/(?:base64|rot13|hex)\b/i' => 0.25,
            '/(?:cron|scheduled|task)\b/i' => 0.2,
            '/(?:backdoor|exploit|hack)/i' => 0.3,

            // Network related patterns
            '/(?:\d{1,3}\.){3}\d{1,3}/' => 0.25,
            '/(?:http|ftp|sftp):\/\// ' => 0.2,
        ];

        $foundPatterns = [];
        foreach ($riskPatterns as $pattern => $weight) {
            if ($this->findContextualPattern($pattern, $findings)) {
                $contextScore += $weight;
                $foundPatterns[] = $pattern;
            }
        }

        // Additional context-based risk factors
        $contextScore *= (1 + $this->calculatePatternDensity($findings));
        $contextScore *= (1 + $this->calculateEntropyFactor($findings));

        // Category-specific adjustments
        $categoryMultipliers = [
            'potential_rce' => 1.5,
            'file_manipulation' => 1.3,
            'suspicious_endpoints' => 1.2,
            'obfuscation' => 1.4,
            'information_disclosure' => 1.1
        ];

        if (isset($categoryMultipliers[$category])) {
            $contextScore *= $categoryMultipliers[$category];
        }

        return min(1.0, $contextScore);
    }

    private function findContextualPattern($pattern, $findings)
    {
        if (is_array($findings)) {
            foreach ($findings as $finding) {
                if ($this->findContextualPattern($pattern, $finding)) return true;
            }
            return false;
        }
        return preg_match($pattern, (string)$findings);
    }

    private function calculatePatternDensity($findings)
    {
        $content = $this->flattenFindings($findings);
        $totalLength = strlen($content);
        if ($totalLength === 0) return 0;

        $patternMatches = preg_match_all('/(?:eval|system|exec|base64|http|ftp|wget|curl)/i', $content, $matches);
        return $patternMatches / max(1, $totalLength / 100);
    }

    private function calculateEntropyFactor($findings)
    {
        $content = $this->flattenFindings($findings);
        $frequencies = array_count_values(str_split($content));
        $entropy = 0;
        $length = strlen($content);

        if ($length === 0) return 0;

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        // Normalize entropy to 0-1 range (typical entropy ranges from 0 to about 8)
        return min(1.0, $entropy / 8.0);
    }

    private function flattenFindings($findings)
    {
        if (!is_array($findings)) {
            return (string)$findings;
        }

        return implode(' ', array_map([$this, 'flattenFindings'], $findings));
    }


    private function calculateConfidence($risks)
    {
        if (empty($risks)) return 0;

        $confidenceFactors = [];
        $uniquePatterns = [];
        $totalPatterns = 0;

        foreach ($risks as $category => $findings) {
            if (is_array($findings)) {
                try {
                    $categoryConfidence = $this->calculateCategoryConfidence($findings);
                    $patternComplexity = $this->calculatePatternComplexity($findings);
                    $consistencyScore = $this->calculateConsistencyScore($findings);

                    $confidenceFactors[$category] = [
                        'base_confidence' => $categoryConfidence,
                        'pattern_complexity' => $patternComplexity,
                        'consistency_score' => $consistencyScore,
                        'unique_patterns' => count($uniquePatterns) - $totalPatterns
                    ];

                    $totalPatterns = count($uniquePatterns);
                } catch (\Exception $e) {
                    Log::warning("Confidence calculation error in $category: " . $e->getMessage());
                }
            }
        }

        return $this->aggregateConfidenceScores($confidenceFactors);
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

    private function calculateCategoryConfidence($findings)
    {
        // Base confidence pada diversitas dan kuantitas findings
        $uniquePatterns = [];
        $totalFindings = 0;
        $weightedConfidence = 0;

        $extractedPatterns = $this->extractPatterns($findings);
        foreach ($extractedPatterns as $pattern) {
            $hash = md5($pattern);
            if (!in_array($hash, $uniquePatterns)) {
                $uniquePatterns[] = $hash;
                $patternWeight = $this->calculatePatternWeight($pattern);
                $weightedConfidence += $patternWeight;
                $totalFindings++;
            }
        }

        return $totalFindings > 0 ? ($weightedConfidence / $totalFindings) * 100 : 0;
    }
    private function calculatePatternWeight($pattern)
    {
        $weights = [
            'complexity' => $this->getPatternComplexity($pattern),
            'uniqueness' => $this->getPatternUniqueness($pattern),
            'context' => $this->getContextRelevance($pattern)
        ];

        return array_sum($weights) / count($weights);
    }

    private function getPatternComplexity($pattern)
    {
        $factors = [
            'length' => strlen($pattern) / 100, // Normalized by 100
            'special_chars' => preg_match_all('/[^a-zA-Z0-9\s]/', $pattern) / 10,
            'structure' => preg_match_all('/[\(\)\[\]\{\}]/', $pattern) / 5
        ];

        return min(1.0, array_sum($factors) / count($factors));
    }

    private function getPatternUniqueness($pattern)
    {
        // Measure pattern entropy as a proxy for uniqueness
        $entropy = 0;
        $frequencies = array_count_values(str_split($pattern));
        $length = strlen($pattern);

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return min(1.0, $entropy / 8.0); // Normalize to 0-1
    }

    private function getContextRelevance($pattern)
    {
        $relevancePatterns = [
            '/(?:eval|system|exec)/i' => 1.0,
            '/(?:base64|rot13)/i' => 0.8,
            '/(?:http|ftp|file)/i' => 0.6,
            '/(?:password|user|admin)/i' => 0.7
        ];

        $maxRelevance = 0;
        foreach ($relevancePatterns as $relevancePattern => $weight) {
            if (preg_match($relevancePattern, $pattern)) {
                $maxRelevance = max($maxRelevance, $weight);
            }
        }

        return $maxRelevance;
    }

    private function calculatePatternComplexity($findings)
    {
        $patterns = $this->extractPatterns($findings);
        if (empty($patterns)) return 0;

        $complexityScores = array_map(function ($pattern) {
            return $this->getPatternComplexity($pattern);
        }, $patterns);

        return array_sum($complexityScores) / count($complexityScores);
    }

    private function calculateConsistencyScore($findings)
    {
        $patterns = $this->extractPatterns($findings);
        if (count($patterns) < 2) return 1.0;

        $similarities = [];
        for ($i = 0; $i < count($patterns); $i++) {
            for ($j = $i + 1; $j < count($patterns); $j++) {
                $similarities[] = $this->calculatePatternSimilarity($patterns[$i], $patterns[$j]);
            }
        }

        return array_sum($similarities) / count($similarities);

        return array_sum($similarities) / count($similarities);
    }

    private function calculatePatternSimilarity($pattern1, $pattern2)
    {
        $length1 = strlen($pattern1);
        $length2 = strlen($pattern2);

        if ($length1 === 0 || $length2 === 0) return 0;

        $levenshtein = levenshtein($pattern1, $pattern2);
        $maxLength = max($length1, $length2);

        return 1 - ($levenshtein / $maxLength);
    }

    private function extractPatterns($findings)
    {
        $patterns = [];

        if (is_array($findings)) {
            foreach ($findings as $finding) {
                if (is_array($finding)) {
                    $patterns = array_merge($patterns, $this->extractPatterns($finding));
                } else {
                    $patterns[] = (string)$finding;
                }
            }
        } else {
            $patterns[] = (string)$findings;
        }

        return array_filter($patterns, 'strlen');
    }

    private function aggregateConfidenceScores($confidenceFactors)
    {
        if (empty($confidenceFactors)) return 0;

        $aggregatedScore = 0;
        $totalWeight = 0;

        foreach ($confidenceFactors as $category => $factors) {
            $categoryWeight = $this->riskWeights[$category] ?? 1;

            $compositeScore = (
                $factors['base_confidence'] * 0.4 +
                $factors['pattern_complexity'] * 100 * 0.3 +
                $factors['consistency_score'] * 100 * 0.3
            );

            $patternBonus = log($factors['unique_patterns'] + 1, 2) * 0.1;
            $finalScore = min(100, $compositeScore * (1 + $patternBonus));

            $aggregatedScore += $finalScore * $categoryWeight;
            $totalWeight += $categoryWeight;
            foreach ($confidenceFactors as $category => $factors) {
                $categoryWeight = $this->riskWeights[$category] ?? 1;

                $compositeScore = (
                    $factors['base_confidence'] * 0.4 +
                    $factors['pattern_complexity'] * 100 * 0.3 +
                    $factors['consistency_score'] * 100 * 0.3
                );

                $patternBonus = log($factors['unique_patterns'] + 1, 2) * 0.1;
                $finalScore = min(100, $compositeScore * (1 + $patternBonus));

                $aggregatedScore += $finalScore * $categoryWeight;
                $totalWeight += $categoryWeight;
            }

            return $totalWeight > 0 ? round($aggregatedScore / $totalWeight, 2) : 0;
            return $totalWeight > 0 ? round($aggregatedScore / $totalWeight, 2) : 0;
        }
    }
    function enhancedScriptAnalysis(Crawler $crawler)
    {
        $scriptRisks = [
            'inline_scripts' => [],
            'external_scripts' => [],
            'dangerous_patterns' => [],
            'obfuscation_attempts' => [],
            'event_handlers' => []
        ];

        $crawler->filter('script')->each(function ($node) use (&$scriptRisks) {
            $content = $node->attr('src') ?? $node->text();
            $href = $node->attr('src');

            // Analisis skrip inline
            if (!$href) {
                $this->analyzeInlineScript($content, $scriptRisks);
            } else {
                $this->analyzeExternalScript($href, $scriptRisks);
            }
        });

        // Analisis event handler untuk semua elemen
        $crawler->filter('*[onclick], *[onload], *[onmouseover], *[onfocus]')->each(
            function ($node) use (&$scriptRisks) {
                $this->analyzeEventHandlers($node, $scriptRisks);
            }
        );

        return array_filter($scriptRisks);
        $scriptRisks = [
            'inline_scripts' => [],
            'external_scripts' => [],
            'dangerous_patterns' => [],
            'obfuscation_attempts' => [],
            'event_handlers' => []
        ];

        $crawler->filter('script')->each(function ($node) use (&$scriptRisks) {
            $content = $node->attr('src') ?? $node->text();
            $href = $node->attr('src');

            // Analisis skrip inline
            if (!$href) {
                $this->analyzeInlineScript($content, $scriptRisks);
            } else {
                $this->analyzeExternalScript($href, $scriptRisks);
            }
        });

        // Analisis event handler untuk semua elemen
        $crawler->filter('*[onclick], *[onload], *[onmouseover], *[onfocus]')->each(
            function ($node) use (&$scriptRisks) {
                $this->analyzeEventHandlers($node, $scriptRisks);
            }
        );

        return array_filter($scriptRisks);
    }

    private function analyzeInlineScript($content, &$scriptRisks)
    {
        $dangerousPatterns = [
            'eval_usage' => '/\beval\s*\([^)]*\)/',
            'document_write' => '/\bdocument\.write\s*\(/',
            'innerHTML_usage' => '/\.innerHTML\s*=/',
            'storage_access' => '/\b(?:localStorage|sessionStorage)\b/',
            'cookie_manipulation' => '/\bdocument\.cookie\b/',
            'dynamic_script' => '/\bdocument\.createElement\s*\(\s*[\'"]script[\'"]\s*\)/',
            'base64_usage' => '/\batob\s*\(|\bbtoa\s*\(/',
            'script_injection' => '/\.src\s*=/',
            'domain_access' => '/\b(?:domain|subdomain|hostname)\b/',
            'window_manipulation' => '/\bwindow\.(?:open|location|history)\b/'
        ];

        foreach ($dangerousPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $scriptRisks['dangerous_patterns'][$type] = array_unique($matches[0]);
            }
        }

        // Check for obfuscation
        $obfuscationIndicators = [
            'string_concat' => '/[\'"]\s*\+\s*[\'"]/',
            'char_code' => '/String\.fromCharCode|charCodeAt/',
            'hex_encoding' => '/\\\\x[0-9a-fA-F]{2}/',
            'unicode_escape' => '/\\\\u[0-9a-fA-F]{4}/',
            'excessive_escape' => '/\\\\{2,}/',
            'packed_code' => '/eval\(function\(p,a,c,k,e,(?:r|d)?\)/',
            'suspicious_arrays' => '/\[[^\]]+\]\.join\s*\(\s*[\'"][\'"]?\s*\)/'
        ];

        foreach ($obfuscationIndicators as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $scriptRisks['obfuscation_attempts'][$type] = array_unique($matches[0]);
            }
        }

        // Entropy analysis for potential obfuscation
        if ($this->calculateScriptEntropy($content) > 5.0) {
            $scriptRisks['obfuscation_attempts']['high_entropy'] = true;
        }
    }

    private function analyzeExternalScript($src, &$scriptRisks)
    {
        $suspiciousPatterns = [
            'non_https' => '/^(?!https:)/',
            'dynamic_script' => '/\.(php|asp|jsp)\b/i',
            'suspicious_domain' => '/(?:pastebin\.com|raw\.githubusercontent\.com)/i',
            'ip_address' => '/^https?:\/\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',
            'suspicious_params' => '/\?.*(?:eval|exec|system|cmd|shell)=/',
            'data_uri' => '/^data:/',
            'relative_path' => '/^(?!https?:\/\/)/',
        ];

        foreach ($suspiciousPatterns as $type => $pattern) {
            if (preg_match($pattern, $src)) {
                $scriptRisks['external_scripts'][$type][] = $src;
            }
        }

        // Additional domain analysis
        $domain = parse_url($src, PHP_URL_HOST);
        if ($domain) {
            $this->analyzeDomainReputation($domain, $scriptRisks);
        }
    }


    // Fix untuk fungsi countValidFindings yang hilang
    private function countValidFindings($findings)
    {
        if (!is_array($findings)) {
            return !empty($findings) ? 1 : 0;
        }

        $count = 0;
        foreach ($findings as $finding) {
            if (is_array($finding)) {
                $count += $this->countValidFindings($finding);
            } else {
                $count += !empty(trim((string)$finding)) ? 1 : 0;
            }
        }
        return $count;
    }

    // Fix untuk fungsi enhancedFormAnalysis yang hilang
    private function enhancedFormAnalysis(Crawler $crawler)
    {
        $formRisks = [
            'insecure_forms' => [],
            'file_uploads' => [],
            'sensitive_inputs' => [],
            'csrf_protection' => []
        ];

        $crawler->filter('form')->each(function ($node) use (&$formRisks) {
            $action = $node->attr('action');
            $method = strtoupper($node->attr('method') ?? 'GET');

            // Check for insecure form submission
            if ($action && !preg_match('/^https:/', $action)) {
                $formRisks['insecure_forms'][] = [
                    'action' => $action,
                    'method' => $method
                ];
            }

            // Check file upload capabilities
            $node->filter('input[type="file"]')->each(function ($input) use (&$formRisks) {
                $formRisks['file_uploads'][] = [
                    'name' => $input->attr('name'),
                    'accept' => $input->attr('accept')
                ];
            });

            // Check for sensitive inputs
            $node->filter('input')->each(function ($input) use (&$formRisks) {
                $type = $input->attr('type');
                $name = $input->attr('name');
                if (
                    in_array($type, ['password', 'hidden']) ||
                    preg_match('/(password|token|key|secret)/i', $name)
                ) {
                    $formRisks['sensitive_inputs'][] = [
                        'type' => $type,
                        'name' => $name
                    ];
                }
            });

            // Check CSRF protection
            if (!$node->filter('input[name="_token"]')->count()) {
                $formRisks['csrf_protection'][] = [
                    'action' => $action,
                    'has_protection' => false
                ];
            }
        });

        return array_filter($formRisks);
    }

    // Fix untuk fungsi enhancedIframeAnalysis yang hilang
    private function enhancedIframeAnalysis(Crawler $crawler)
    {
        $iframeRisks = [
            'insecure_sources' => [],
            'suspicious_content' => [],
            'sandbox_inspection' => []
        ];

        $crawler->filter('iframe')->each(function ($node) use (&$iframeRisks) {
            $src = $node->attr('src');
            $sandbox = $node->attr('sandbox');

            if ($src) {
                // Check for insecure sources
                if (!preg_match('/^https:/', $src)) {
                    $iframeRisks['insecure_sources'][] = $src;
                }

                // Check for suspicious content
                if (preg_match('/(data|javascript|file):/i', $src)) {
                    $iframeRisks['suspicious_content'][] = [
                        'src' => $src,
                        'type' => 'suspicious_protocol'
                    ];
                }
            }

            // Analyze sandbox attributes
            $iframeRisks['sandbox_inspection'][] = [
                'has_sandbox' => !empty($sandbox),
                'sandbox_value' => $sandbox,
                'src' => $src
            ];
        });

        return array_filter($iframeRisks);
    }

    // Fix untuk fungsi enhancedMetaAnalysis yang hilang
    private function enhancedMetaAnalysis(Crawler $crawler)
    {
        $metaRisks = [
            'security_headers' => [],
            'content_policies' => [],
            'suspicious_content' => []
        ];

        $crawler->filter('meta')->each(function ($node) use (&$metaRisks) {
            $httpEquiv = $node->attr('http-equiv');
            $content = $node->attr('content');
            $name = $node->attr('name');

            if ($httpEquiv) {
                $this->analyzeSecurityHeader($httpEquiv, $content, $metaRisks);
            }

            if ($content && preg_match('/(php|asp|jsp|cgi|eval|exec)/i', $content)) {
                $metaRisks['suspicious_content'][] = [
                    'name' => $name ?? $httpEquiv,
                    'content' => $content
                ];
            }
        });

        return array_filter($metaRisks);
    }

    private function enhancedCommentAnalysis(Crawler $crawler)
    {
        $commentRisks = [
            'sensitive_data' => [],
            'debug_info' => [],
            'suspicious_content' => []
        ];

        preg_match_all('/<!--(.*?)-->/s', $crawler->html(), $matches);

        foreach ($matches[1] as $comment) {
            // Check for sensitive data
            if (preg_match('/(password|user|admin|key|token|secret)/i', $comment)) {
                $commentRisks['sensitive_data'][] = $this->sanitizeComment($comment);
            }

            // Check for debug information
            if (preg_match('/(todo|fixme|hack|debug|test)/i', $comment)) {
                $commentRisks['debug_info'][] = $this->sanitizeComment($comment);
            }

            // Check for suspicious content
            if (preg_match('/(backdoor|exploit|bypass|inject)/i', $comment)) {
                $commentRisks['suspicious_content'][] = $this->sanitizeComment($comment);
            }
        }

        return array_filter($commentRisks);
    }

    // Helper function untuk sanitize comment
    private function sanitizeComment($comment)
    {
        return [
            'content' => trim($comment),
            'length' => strlen($comment),
            'entropy' => $this->calculateStringEntropy($comment)
        ];
    }

    // Fix untuk fungsi enhancedInputValidation yang hilang
    private function enhancedInputValidation($html)
    {
        $validationRisks = [
            'unfiltered_inputs' => [],
            'unsafe_assignments' => [],
            'validation_bypass' => []
        ];

        // Check unfiltered inputs
        $inputPatterns = [
            'GET' => '\$_GET',
            'POST' => '\$_POST',
            'REQUEST' => '\$_REQUEST',
            'COOKIE' => '\$_COOKIE'
        ];

        foreach ($inputPatterns as $type => $pattern) {
            if (preg_match_all('/' . $pattern . '\s*\[\s*([^\]]+)\s*\]/', $html, $matches)) {
                foreach ($matches[1] as $variable) {
                    $validationRisks['unfiltered_inputs'][] = [
                        'type' => $type,
                        'variable' => $variable
                    ];
                }
            }
        }

        // Check unsafe direct assignments
        if (preg_match_all('/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff])\s=\s*\$_(GET|POST|REQUEST|COOKIE)/', $html, $matches)) {
            foreach ($matches[1] as $index => $variable) {
                $validationRisks['unsafe_assignments'][] = [
                    'variable' => $variable,
                    'source' => $matches[2][$index]
                ];
            }
        }

        // Check validation bypass attempts
        $bypassPatterns = [
            '/\b(?:strip_tags|htmlspecialchars|htmlentities)\s*\([^)]\)\s=/',
            '/\b(?:addslashes|mysql_real_escape_string)\s*\([^)]\)\s=/'
        ];

        foreach ($bypassPatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $validationRisks['validation_bypass'] = array_merge(
                    $validationRisks['validation_bypass'] ?? [],
                    array_unique($matches[0])
                );
            }
        }

        return array_filter($validationRisks);
    }

    // Fix untuk fungsi analyzeSecurityHeader yang hilang
    private function analyzeSecurityHeader($httpEquiv, $content, &$metaRisks)
    {
        $securityHeaders = [
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-XSS-Protection',
            'X-Content-Type-Options',
            'Referrer-Policy'
        ];

        if (in_array($httpEquiv, $securityHeaders)) {
            $metaRisks['security_headers'][] = [
                'header' => $httpEquiv,
                'value' => $content,
                'is_secure' => $this->isSecureHeaderValue($httpEquiv, $content)
            ];
        }
    }

    // Helper function untuk check secure header value
    private function isSecureHeaderValue($header, $value)
    {
        $secureValues = [
            'X-Frame-Options' => ['DENY', 'SAMEORIGIN'],
            'X-XSS-Protection' => ['1; mode=block'],
            'X-Content-Type-Options' => ['nosniff']
        ];

        if (isset($secureValues[$header])) {
            return in_array($value, $secureValues[$header]);
        }

        return true;
    }

    private function analyzeEventHandlers(Crawler $node, &$scriptRisks)
    {
        $suspiciousHandlers = [
            'onload',
            'onerror',
            'onmouseover',
            'onclick',
            'onmouseout',
            'onkeypress',
            'onkeydown',
            'onkeyup',
            'onsubmit',
            'onbeforeunload'
        ];

        foreach ($suspiciousHandlers as $handler) {
            // Gunakan method attr() bukan attributes()
            $content = $node->attr($handler);
            if ($content) {
                $scriptRisks['event_handlers'][$handler][] = [
                    'content' => $content,
                    'risk_level' => $this->assessEventHandlerRisk($content)
                ];
            }
        }
    }

    private function assessEventHandlerRisk($content)
    {
        $riskPatterns = [
            'critical' => [
                '/\beval\s*\(/',
                '/\bdocument\.write\s*\(/',
                '/\blocation\s*=/',
                '/\bwindow\.open\s*\(/'
            ],
            'high' => [
                '/\.innerHTML\s*=/',
                '/\bdocument\.cookie\b/',
                '/\blocalStorage\b/',
                '/\bsessionStorage\b/'
            ],
            'medium' => [
                '/\balert\s*\(/',
                '/\bconfirm\s*\(/',
                '/\bprompt\s*\(/',
                '/\.style\b/'
            ]
        ];

        foreach ($riskPatterns as $level => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    return [
                        'level' => $level,
                        'matched_pattern' => $pattern
                    ];
                }
            }
        }

        return ['level' => 'low', 'matched_pattern' => null];
    }

    private function calculateScriptEntropy($content)
    {
        $frequencies = array_count_values(str_split($content));
        $entropy = 0;
        $length = strlen($content);

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    private function analyzeDomainReputation($domain, &$scriptRisks)
    {
        // Implement domain reputation checks
        $suspiciousIndictors = [
            'length' => strlen($domain) > 30,
            'numbers' => preg_match('/\d{4,}/', $domain),
            'special_chars' => preg_match('/[-]{2,}/', $domain),
            'suspicious_words' => preg_match('/(?:hack|crack|warez|spam|phish|malware)/i', $domain)
        ];

        if (array_filter($suspiciousIndictors)) {
            $scriptRisks['external_scripts']['suspicious_domain'][] = [
                'domain' => $domain,
                'indicators' => array_keys(array_filter($suspiciousIndictors))
            ];
        }
    }

    private function enhancedLinkAnalysis(Crawler $crawler)
    {
        $linkRisks = [
            'suspicious_targets' => [],
            'dynamic_redirects' => [],
            'protocol_handlers' => [],
            'parameter_analysis' => []
        ];

        $crawler->filter('a')->each(function ($node) use (&$linkRisks) {
            $href = $node->attr('href');
            $onclick = $node->attr('onclick');

            if ($href) {
                $this->analyzeLinkTarget($href, $linkRisks);
                $this->analyzeLinkParameters($href, $linkRisks);
            }

            if ($onclick) {
                $this->analyzeLinkBehavior($onclick, $linkRisks);
            }
        });

        return array_filter($linkRisks);
    }

    private function analyzeLinkTarget($href, &$linkRisks)
    {
        $suspiciousTargets = [
            'admin_access' => '/(?:admin|administrator|setup|install|phpinfo)/',
            'sensitive_files' => '/\.(?:php|asp|aspx|jsp|cgi|env|config|ini)/',
            'protocol_handlers' => '/^(?:javascript|data|vbscript|file):/i',
            'suspicious_domains' => '/(?:\.tk|\.top|\.xyz)$/i',
            'ip_addresses' => '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/'
        ];

        foreach ($suspiciousTargets as $type => $pattern) {
            if (preg_match($pattern, $href)) {
                $linkRisks['suspicious_targets'][$type][] = $href;
            }
        }
    }

    private function analyzeLinkParameters($href, &$linkRisks)
    {
        parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $params);

        $suspiciousParams = [
            'command_injection' => '/(?:cmd|exec|system|shell)/',
            'file_operations' => '/(?:file|path|directory|folder)/',
            'authentication' => '/(?:user|admin|login|auth|pass)/',
            'database' => '/(?:select|union|insert|delete)/',
            'redirects' => '/(?:url|redirect|return|goto)/'
        ];

        foreach ($params as $key => $value) {
            foreach ($suspiciousParams as $type => $pattern) {
                if (preg_match($pattern, strtolower($key))) {
                    $linkRisks['parameter_analysis'][$type][] = [
                        'parameter' => $key,
                        'value' => $value,
                        'url' => $href
                    ];
                }
            }
        }
    }

    private function analyzeLinkBehavior($onclick, &$linkRisks)
    {
        $suspiciousBehaviors = [
            'window_manipulation' => '/\bwindow\.(open|location)\b/',
            'form_manipulation' => '/\bdocument\.forms\b/',
            'event_prevention' => '/\bevent\.preventDefault\(\)/',
            'dynamic_navigation' => '/\blocation\.(href|replace|assign)\b/',
            'parameter_manipulation' => '/\blocation\.search\b/'
        ];

        foreach ($suspiciousBehaviors as $type => $pattern) {
            if (preg_match($pattern, $onclick)) {
                $linkRisks['dynamic_redirects'][$type][] = $onclick;
            }
        }
    }
}
