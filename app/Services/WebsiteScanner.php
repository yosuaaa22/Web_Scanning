<?php

namespace App\Services;

use App\Models\Website;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DomCrawler\Crawler;

class WebsiteScanner
{
    private Client $client;
    private Website $website;

    public function __construct(Website $website)
    {
        $this->website = $website;
        $this->client = new Client([
            'timeout' => 15,
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
            ],
            'headers' => [
                'User -Agent' => config('app.name') . '/' . config('app.version')
            ]
        ]);
    }

    public function fullScan(): array
    {
        $response = $this->fetchWebsite();
        
        $analysis = [
            'status' => $response ? 'up' : 'down',
            'response_time' => $response ? $this->measureResponseTime($response) : null,
            'ssl' => $this->checkSSL(),
            'headers' => $this->analyzeHeaders($response),
            'security' => $this->checkSecurityHeaders($response),
            'performance' => $this->analyzePerformance($response),
            'seo' => $this->analyzeSEO($response),
            'issues' => []
        ];

        $this->saveResults($analysis);
        return $analysis;
    }

    private function fetchWebsite(): ?Response
    {
        try {
            $start = microtime(true);
            $response = $this->client->get($this->website->url);
            $responseTime = (microtime(true) - $start) * 1000; // in milliseconds
            
            // Attach response time to the response object
            $response->getBody()->responseTime = $responseTime; // This won't work directly; see note below
            
            return $response;
        } catch (RequestException $e) {
            logger()->error("Failed to fetch website {$this->website->url}: " . $e->getMessage());
            return null;
        }
    }

    private function checkSSL(): array
    {
        return (new SslChecker($this->website->url))->analyze();
    }

    private function analyzeHeaders(?Response $response): array
    {
        return $response 
            ? (new HeaderAnalyzer($response->getHeaders()))->analyze()
            : [];
    }

    private function saveResults(array $analysis): void
    {
        $this->website->update([
            'status' => $analysis['status'],
            'last_checked' => now(),
            'analysis_data' => $analysis
        ]);
    }

    private function measureResponseTime(Response $response): float
    {
        // Assuming you have a way to get the response time from the response object
        return $response->getBody()->responseTime ?? 0; // Adjust this based on how you store response time
    }

    private function checkSecurityHeaders(?Response $response): array
    {
        // Implement your security header checks here
        return [];
    }

    private function analyzePerformance(?Response $response): array
    {
        // Implement your performance analysis here
        return [];
    }

    private function analyzeSEO(?Response $response): array
    {
        // Implement your SEO analysis here
        return [];
    }
}