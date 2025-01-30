<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnhancedDetectionService
{
    private $client;
    private const CACHE_TTL = 3600; // 1 hour cache

    // Network threat patterns
    private $networkPatterns = [
        'suspicious_endpoints' => [
            // Shell access patterns
            '/api\/(?:shell|cmd|exec|system|backdoor)/',
            '/(?:cmd|shell|system)\.php/',
            // Upload/download risks
            '/(?:upload|download)\/(?:shell|backdoor|exec)/',
            // Admin/sensitive endpoints
            '/(?:admin|config|setup|install)\.php/',
            // Database exposure
            '/(?:phpmy|phpmyadmin|sql|mysql)/',
            // Common attack vectors
            '/(?:eval|assert|passthru)\.php/',
            // Suspicious parameters
            '/\?(cmd|exec|system|eval|assert|code)=/'
        ],
        'malicious_domains' => [
            // Suspicious domain patterns
            '/(?:evil|malware|hack|pwn|shell)\.[a-z]+/',
            // Random/generated domains
            '/[a-z0-9]{25,}\.[a-z]+/',
            // IP address domains
            '/(?:\d{1,3}\.){3}\d{1,3}/',
            // Typosquatting domains
            '/(?:faceb00k|g00gle|paypai|blockch[a@]in)\.[a-z]+/',
            // Phishing patterns
            '/(?:secure|login|account|verify)[0-9-]+\.[a-z]+/'
        ],
        'suspicious_tlds' => [
            'xyz', 'top', 'surf', 'club', 'cool', 'monster', 'work',
            'guru', 'win', 'pro', 'racing', 'party', 'space'
        ]
    ];

    // Hidden element patterns
    private $hiddenPatterns = [
        'css_hidden' => [
            // CSS hiding techniques
            'display: none',
            'visibility: hidden',
            'opacity: 0',
            'height: 0',
            'width: 0',
            'position: absolute; left: -9999px',
            'transform: scale(0)',
            'clip: rect(0,0,0,0)',
            'overflow: hidden'
        ],
        'js_hidden' => [
            // JavaScript hiding techniques
            'element.style.display = "none"',
            'element.hidden = true',
            'element.style.visibility = "hidden"',
            'element.remove()',
            '$(element).hide()',
            'addClass("hidden")'
        ],
        'obfuscation' => [
            // Content obfuscation
            'base64_decode',
            'str_rot13',
            'gzinflate',
            'eval(',
            'fromCharCode'
        ]
    ];

    // JavaScript security patterns
    private $jsPatterns = [
        'eval_usage' => '/eval\s*\([^)]*\)/',
        'document_write' => '/document\.write\s*\([^)]*\)/',
        'suspicious_functions' => [
            'exec', 'system', 'shell_exec', 'passthru',
            'assert', 'create_function', 'unserialize'
        ],
        'encoded_strings' => [
            'atob', 'btoa', 'escape', 'unescape',
            'encodeURIComponent', 'decodeURIComponent'
        ],
        'dom_manipulation' => [
            'innerHTML', 'outerHTML', 'insertAdjacentHTML',
            'document.createElement', 'appendChild'
        ],
        'event_handlers' => [
            'onload', 'onerror', 'onmouseover',
            'onclick', 'onsubmit', 'onkeypress'
        ]
    ];

    // Registration/form security patterns
    private $registrationPatterns = [
        'sensitive_fields' => [
            'username', 'password', 'email', 'phone',
            'credit_card', 'ssn', 'address', 'dob'
        ],
        'suspicious_fields' => [
            'admin', 'root', 'sudo', 'shell',
            'backdoor', 'hack', 'exploit'
        ],
        'vulnerable_inputs' => [
            'file', 'hidden', 'data', 'action',
            'redirect', 'url', 'callback'
        ]
    ];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function analyze($url)
    {
        $cacheKey = 'enhanced_detection_' . md5($url);

        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 10,
                'connect_timeout' => 5,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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

            $analysis = [
                'network_analysis' => $this->analyzeNetworkIndicators($crawler, $html, $url),
                'hidden_elements' => $this->findHiddenElements($crawler, $html),
                'js_analysis' => $this->analyzeJavaScript($crawler, $html),
                'redirect_analysis' => $this->analyzeRedirects($crawler, $response),
                'registration_analysis' => $this->analyzeRegistrationFlow($crawler),
                'metadata' => [
                    'scan_timestamp' => now(),
                    'response_code' => $response->getStatusCode(),
                    'content_type' => $response->getHeaderLine('Content-Type'),
                    'server' => $response->getHeaderLine('Server'),
                    'evidence_collected' => []
                ]
            ];

            // Store in cache
            Cache::put($cacheKey, $analysis, self::CACHE_TTL);

            return $analysis;

        } catch (\Exception $e) {
            Log::error('Enhanced detection error:', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getDefaultErrorResponse();
        }
    }

    private function analyzeNetworkIndicators(Crawler $crawler, string $html, string $baseUrl): array
    {
        $indicators = [
            'suspicious_urls' => [],
            'suspicious_domains' => [],
            'suspicious_endpoints' => [],
            'risk_level' => 'low',
            'confidence_score' => 0,
            'evidence' => []
        ];

        // Analyze all links
        $crawler->filter('a')->each(function($node) use (&$indicators, $baseUrl) {
            $href = $node->attr('href');
            if (!$href) return;

            $fullUrl = $this->resolveUrl($baseUrl, $href);
            $urlParts = parse_url($fullUrl);

            if (!$urlParts) return;

            // Check domain patterns
            $domain = $urlParts['host'] ?? '';
            foreach ($this->networkPatterns['malicious_domains'] as $pattern) {
                if (preg_match($pattern, $domain)) {
                    $indicators['suspicious_domains'][] = [
                        'domain' => $domain,
                        'pattern_matched' => $pattern,
                        'context' => $node->text(),
                        'full_url' => $fullUrl
                    ];
                    $indicators['confidence_score'] += 10;
                    $indicators['evidence'][] = [
                        'type' => 'suspicious_domain',
                        'value' => $domain,
                        'pattern' => $pattern,
                        'location' => 'href attribute'
                    ];
                }
            }

            // Check endpoint patterns
            $path = $urlParts['path'] ?? '';
            foreach ($this->networkPatterns['suspicious_endpoints'] as $pattern) {
                if (preg_match($pattern, $path)) {
                    $indicators['suspicious_endpoints'][] = [
                        'path' => $path,
                        'pattern_matched' => $pattern,
                        'context' => $node->text(),
                        'full_url' => $fullUrl
                    ];
                    $indicators['confidence_score'] += 15;
                    $indicators['evidence'][] = [
                        'type' => 'suspicious_endpoint',
                        'value' => $path,
                        'pattern' => $pattern,
                        'location' => 'URL path'
                    ];
                }
            }

            // Analyze query parameters
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $params);
                foreach ($params as $key => $value) {
                    if (preg_match('/(?:cmd|exec|eval|system|code|shell)/', $key)) {
                        $indicators['suspicious_urls'][] = [
                            'parameter' => $key,
                            'value' => $value,
                            'context' => $node->text(),
                            'full_url' => $fullUrl
                        ];
                        $indicators['confidence_score'] += 20;
                        $indicators['evidence'][] = [
                            'type' => 'suspicious_parameter',
                            'key' => $key,
                            'value' => $value,
                            'location' => 'URL parameter'
                        ];
                    }
                }
            }
        });

        // Update risk level based on findings
        if ($indicators['confidence_score'] >= 50) {
            $indicators['risk_level'] = 'high';
        } elseif ($indicators['confidence_score'] >= 25) {
            $indicators['risk_level'] = 'medium';
        }

        return $indicators;
    }

    private function findHiddenElements(Crawler $crawler, string $html): array
    {
        $hidden = [
            'css_hidden' => [],
            'js_hidden' => [],
            'obfuscated_content' => [],
            'risk_level' => 'low',
            'confidence_score' => 0,
            'evidence' => []
        ];

        // Check CSS hidden elements
        foreach ($this->hiddenPatterns['css_hidden'] as $style) {
            $crawler->filter("[style*='$style']")->each(function($node) use (&$hidden, $style) {
                $hidden['css_hidden'][] = [
                    'element' => $node->nodeName(),
                    'style' => $node->attr('style'),
                    'content' => $node->text(),
                    'pattern_matched' => $style
                ];
                $hidden['confidence_score'] += 5;
                $hidden['evidence'][] = [
                    'type' => 'hidden_css',
                    'element' => $node->nodeName(),
                    'style' => $style,
                    'location' => 'style attribute'
                ];
            });
        }

        // Check JavaScript hidden elements
        $crawler->filter('script')->each(function($node) use (&$hidden) {
            $script = $node->text();
            foreach ($this->hiddenPatterns['js_hidden'] as $pattern) {
                if (strpos($script, $pattern) !== false) {
                    $hidden['js_hidden'][] = [
                        'pattern' => $pattern,
                        'context' => substr($script, 0, 200),
                        'script_length' => strlen($script)
                    ];
                    $hidden['confidence_score'] += 10;
                    $hidden['evidence'][] = [
                        'type' => 'hidden_js',
                        'pattern' => $pattern,
                        'location' => 'script content'
                    ];
                }
            }
        });

        // Check for obfuscated content
        foreach ($this->hiddenPatterns['obfuscation'] as $pattern) {
            if (strpos($html, $pattern) !== false) {
                $hidden['obfuscated_content'][] = [
                    'pattern' => $pattern,
                    'count' => substr_count($html, $pattern)
                ];
                $hidden['confidence_score'] += 15;
                $hidden['evidence'][] = [
                    'type' => 'obfuscation',
                    'pattern' => $pattern,
                    'count' => substr_count($html, $pattern),
                    'location' => 'HTML content'
                ];
            }
        }

        // Update risk level
        if ($hidden['confidence_score'] >= 40) {
            $hidden['risk_level'] = 'high';
        } elseif ($hidden['confidence_score'] >= 20) {
            $hidden['risk_level'] = 'medium';
        }

        return $hidden;
    }

    private function analyzeJavaScript(Crawler $crawler, string $html): array
    {
        $analysis = [
            'suspicious_patterns' => [],
            'eval_usage' => [],
            'encoded_content' => [],
            'event_handlers' => [],
            'risk_level' => 'low',
            'confidence_score' => 0,
            'evidence' => []
        ];

        // Analyze inline scripts
        $crawler->filter('script')->each(function($node) use (&$analysis) {
            $script = $node->text();

            // Check eval usage
            if (preg_match_all($this->jsPatterns['eval_usage'], $script, $matches)) {
                $analysis['eval_usage'] = array_merge(
                    $analysis['eval_usage'],
                    array_map(function($match) {
                        return [
                            'pattern' => $match,
                            'context' => substr($match, 0, 100)
                        ];
                    }, $matches[0])
                );
                $analysis['confidence_score'] += 20;
                $analysis['evidence'][] = [
                    'type' => 'eval_usage',
                    'count' => count($matches[0]),
                    'location' => 'script content'
                ];
            }

            // Check document.write
            if (preg_match_all($this->jsPatterns['document_write'], $script, $matches)) {
                $analysis['suspicious_patterns'][] = [
                    'type' => 'document_write',
                    'matches' => $matches[0],
                    'count' => count($matches[0])
                ];
                $analysis['confidence_score'] += 10;
                $analysis['evidence'][] = [
                    'type' => 'document_write',
                    'count' => count($matches[0]),
                    'location' => 'script content'
                ];
            }

            // Check suspicious functions
            foreach ($this->jsPatterns['suspicious_functions'] as $func) {
                if (strpos($script, $func) !== false) {
                    $analysis['suspicious_patterns'][] = [
                        'type' => 'suspicious_function',
                        'function' => $func,
                        'context' => substr($script, strpos($script, $func), 100)
                    ];
                    $analysis['confidence_score'] += 15;
                    $analysis['evidence'][] = [
                        'type' => 'suspicious_function',
                        'function' => $func,
                        'location' => 'script content'
                    ];
                }
            }

            // Check encoded content
            foreach ($this->jsPatterns['encoded_strings'] as $method) {
                if (strpos($script, $method) !== false) {
                    $analysis['encoded_content'][] = [
                        'method' => $method,
                        'context' => substr($script, strpos($script, $method), 100)
                    ];
                    $analysis['confidence_score'] += 5;
                    $analysis['evidence'][] = [
                        'type' => 'encoded_content',
                        'method' => $method,
                        'location' => 'script content'
                    ];
                }
            }

            // Check DOM manipulation
            foreach ($this->jsPatterns['dom_manipulation'] as $pattern) {
                if (strpos($script, $pattern) !== false) {
                    $analysis['suspicious_patterns'][] = [
                        'type' => 'dom_manipulation',
                        'pattern' => $pattern,
                        'context' => substr($script, strpos($script, $pattern), 100)
                    ];
                    $analysis['confidence_score'] += 5;
                }
            }
        });

        // Check event handlers in HTML
        foreach ($this->jsPatterns['event_handlers'] as $handler) {
            $crawler->filter("*[$handler]")->each(function($node) use ($handler, &$analysis) {
                $analysis['event_handlers'][] = [
                    'type' => $handler,
                    'element' => $node->nodeName(),
                    'content' => $node->attr($handler)
                ];
                $analysis['confidence_score'] += 5;
                $analysis['evidence'][] = [
                    'type' => 'event_handler',
                    'handler' => $handler,
                    'location' => 'HTML attribute'
                ];
            });
        }

        // Update risk level based on confidence score
        if ($analysis['confidence_score'] >= 50) {
            $analysis['risk_level'] = 'high';
        } elseif ($analysis['confidence_score'] >= 25) {
            $analysis['risk_level'] = 'medium';
        }

        return $analysis;
    }

    private function analyzeRegistrationFlow(Crawler $crawler): array
    {
        $analysis = [
            'required_fields' => [],
            'suspicious_elements' => [],
            'form_analysis' => [],
            'risk_level' => 'low',
            'confidence_score' => 0,
            'evidence' => []
        ];

        // Analyze all forms
        $crawler->filter('form')->each(function($form) use (&$analysis) {
            $formAnalysis = [
                'action' => $form->attr('action'),
                'method' => $form->attr('method'),
                'fields' => []
            ];

            // Analyze form fields
            $form->filter('input, select, textarea')->each(function($input) use (&$analysis, &$formAnalysis) {
                $name = $input->attr('name');
                $type = $input->attr('type');
                $id = $input->attr('id');

                $fieldInfo = [
                    'name' => $name,
                    'type' => $type,
                    'id' => $id
                ];

                // Check for sensitive fields
                foreach ($this->registrationPatterns['sensitive_fields'] as $pattern) {
                    if (stripos($name, $pattern) !== false || stripos($id, $pattern) !== false) {
                        $fieldInfo['sensitivity'] = 'high';
                        $analysis['confidence_score'] += 5;
                        $analysis['evidence'][] = [
                            'type' => 'sensitive_field',
                            'field' => $name,
                            'pattern' => $pattern,
                            'location' => 'form field'
                        ];
                    }
                }

                // Check for suspicious fields
                foreach ($this->registrationPatterns['suspicious_fields'] as $pattern) {
                    if (stripos($name, $pattern) !== false || stripos($id, $pattern) !== false) {
                        $fieldInfo['suspicious'] = true;
                        $analysis['suspicious_elements'][] = [
                            'field' => $name,
                            'pattern' => $pattern,
                            'type' => $type
                        ];
                        $analysis['confidence_score'] += 15;
                        $analysis['evidence'][] = [
                            'type' => 'suspicious_field',
                            'field' => $name,
                            'pattern' => $pattern,
                            'location' => 'form field'
                        ];
                    }
                }

                // Check for vulnerable input types
                if (in_array($type, $this->registrationPatterns['vulnerable_inputs'])) {
                    $fieldInfo['vulnerable'] = true;
                    $analysis['confidence_score'] += 10;
                    $analysis['evidence'][] = [
                        'type' => 'vulnerable_input',
                        'field' => $name,
                        'input_type' => $type,
                        'location' => 'form field'
                    ];
                }

                $formAnalysis['fields'][] = $fieldInfo;
                $analysis['required_fields'][] = $fieldInfo;
            });

            $analysis['form_analysis'][] = $formAnalysis;
        });

        // Update risk level based on findings
        if ($analysis['confidence_score'] >= 40) {
            $analysis['risk_level'] = 'high';
        } elseif ($analysis['confidence_score'] >= 20) {
            $analysis['risk_level'] = 'medium';
        }

        return $analysis;
    }

    private function analyzeRedirects(Crawler $crawler, $response): array
    {
        $analysis = [
            'redirect_chain' => [],
            'suspicious_redirects' => [],
            'js_redirects' => [],
            'risk_level' => 'low',
            'confidence_score' => 0,
            'evidence' => []
        ];

        // Check redirect history
        if ($response->hasHeader('X-Guzzle-Redirect-History')) {
            $redirects = $response->getHeader('X-Guzzle-Redirect-History');
            $analysis['redirect_chain'] = $redirects;

            // Analyze each redirect
            foreach ($redirects as $redirect) {
                $urlParts = parse_url($redirect);
                $domain = $urlParts['host'] ?? '';

                // Check for suspicious domains in redirect chain
                foreach ($this->networkPatterns['malicious_domains'] as $pattern) {
                    if (preg_match($pattern, $domain)) {
                        $analysis['suspicious_redirects'][] = [
                            'url' => $redirect,
                            'pattern_matched' => $pattern,
                            'domain' => $domain
                        ];
                        $analysis['confidence_score'] += 20;
                        $analysis['evidence'][] = [
                            'type' => 'suspicious_redirect',
                            'url' => $redirect,
                            'pattern' => $pattern,
                            'location' => 'redirect chain'
                        ];
                    }
                }
            }
        }

        // Check for JavaScript redirects
        $crawler->filter('script')->each(function($node) use (&$analysis) {
            $script = $node->text();

            // Common JavaScript redirect patterns
            $redirectPatterns = [
                '/window\.location\s*=/',
                '/window\.location\.href\s*=/',
                '/window\.navigate\s*\(/',
                '/document\.location\s*=/',
                '/location\.replace\s*\(/'
            ];

            foreach ($redirectPatterns as $pattern) {
                if (preg_match($pattern, $script, $match)) {
                    $analysis['js_redirects'][] = [
                        'pattern' => $pattern,
                        'context' => substr($script, strpos($script, $match[0]), 100)
                    ];
                    $analysis['confidence_score'] += 10;
                    $analysis['evidence'][] = [
                        'type' => 'js_redirect',
                        'pattern' => $pattern,
                        'location' => 'script content'
                    ];
                }
            }
        });

        // Update risk level
        if ($analysis['confidence_score'] >= 30) {
            $analysis['risk_level'] = 'high';
        } elseif ($analysis['confidence_score'] >= 15) {
            $analysis['risk_level'] = 'medium';
        }

        return $analysis;
    }

    private function resolveUrl($base, $rel) {
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        if ($rel[0] === '#' || $rel[0] === '?') {
            return $base . $rel;
        }

        extract(parse_url($base));

        $path = isset($path) ? preg_replace('#/[^/]*$#', '', $path) : '/';

        if ($rel[0] === '/') {
            $path = '';
        }

        $abs = "$host$path/$rel";
        $re = ['#(/.?/)#', '#/(?!..)[^/]+/../#'];

        for($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}

        return $scheme.'://'.$abs;
    }

    private function getDefaultErrorResponse(): array
    {
        return [
            'network_analysis' => [
                'suspicious_urls' => [],
                'suspicious_domains' => [],
                'suspicious_endpoints' => [],
                'risk_level' => 'unknown',
                'confidence_score' => 0,
                'evidence' => []
            ],
            'hidden_elements' => [
                'css_hidden' => [],
                'js_hidden' => [],
                'obfuscated_content' => [],
                'risk_level' => 'unknown',
                'confidence_score' => 0,
                'evidence' => []
            ],
            'js_analysis' => [
                'suspicious_patterns' => [],
                'eval_usage' => [],
                'encoded_content' => [],
                'event_handlers' => [],
                'risk_level' => 'unknown',
                'confidence_score' => 0,
                'evidence' => []
            ],
            'registration_analysis' => [
                'required_fields' => [],
                'suspicious_elements' => [],
                'form_analysis' => [],
                'risk_level' => 'unknown',
                'confidence_score' => 0,
                'evidence' => []
            ],
            'metadata' => [
                'scan_timestamp' => now(),
                'status' => 'error',
                'error_occurred' => true
            ]
        ];
    }
}
