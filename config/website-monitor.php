
<?php

return [
    'default_settings' => [
        'check_ssl' => true,
        'check_headers' => true,
        'check_content' => false,
        'check_performance' => true,
        'alert_channels' => ['mail'],
        'alert_thresholds' => [
            'uptime' => 99.9,
            'response_time' => 2000,
            'ssl_expiry_days' => 7
        ]
    ],
    
    'security_headers' => [
        'strict-transport-security',
        'content-security-policy',
        'x-content-type-options',
        'x-frame-options',
        'x-xss-protection',
        'referrer-policy'
    ],
    
    'user_agent' => 'WebsiteMonitor/1.0 (+https://example.com)'
];