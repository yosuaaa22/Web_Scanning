<?php
namespace App\Rules;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SafeUrlRule
{
    public static function validate()
    {
        return function (string $attribute, mixed $value, $fail) {
            // Validasi struktur URL
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $fail("URL tidak valid.");
                return;
            }

            $parsedUrl = parse_url($value);
            $host = $parsedUrl['host'] ?? '';

            // Cek domain yang diizinkan
            $allowedTlds = ['.com', '.org', '.net', '.id'];
            $validTld = array_reduce($allowedTlds, function($carry, $tld) use ($host) {
                return $carry || str_ends_with($host, $tld);
            }, false);

            if (!$validTld) {
                $fail("Domain tidak diizinkan.");
                return;
            }

            // Cek konektivitas
            try {
                $response = Http::timeout(5)->get($value);
                if (!$response->successful()) {
                    $fail("URL tidak dapat diakses.");
                }
            } catch (\Exception $e) {
                $fail("Gagal terhubung ke URL.");
            }
        };
    }
}
