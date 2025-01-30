<?php

return [
    'security_headers' => true,
    'csp' => [
        'enable' => true,
        'report_only' => false,
        'report_uri' => '/csp-report-endpoint'
    ],
    'xss_protection' => true,

    'scanning' => [
        'max_url_length' => 255,
        'timeout' => 15,
        'blocked_domains' => [
            'localhost',
            '127.0.0.1',
            'example.com'
        ]
    ],
    'risk_thresholds' => [
        'backdoor' => [
            'low' => 2,
            'medium' => 5,
            'high' => 10
        ],
        'gambling' => [
            'low' => 2,
            'medium' => 5,
            'high' => 10
        ]
    ]
];
