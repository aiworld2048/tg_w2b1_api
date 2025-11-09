<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BuffaloGameMultiSiteService
{
    /**
     * Get site configuration by prefix
     */
    public static function getSiteConfig(string $prefix): ?array
    {
        $sites = Config::get('buffalo_sites.sites', []);
        
        return $sites[$prefix] ?? null;
    }

    /**
     * Get all enabled sites
     */
    public static function getEnabledSites(): array
    {
        $sites = Config::get('buffalo_sites.sites', []);
        
        return array_filter($sites, function($site) {
            return $site['enabled'] ?? false;
        });
    }

    /**
     * Extract site prefix from UID
     */
    public static function extractPrefix(string $uid): string
    {
        // First 3 characters are the prefix
        return substr($uid, 0, 3);
    }

    /**
     * Extract original UID (without prefix)
     */
    public static function extractOriginalUid(string $uid): string
    {
        // Remove prefix and separator (first 4 characters: "pwf-")
        // If no separator, just remove first 3 characters
        if (substr($uid, 3, 1) === '-') {
            return substr($uid, 4);
        }
        
        return substr($uid, 3);
    }

    /**
     * Generate UID with site prefix
     */
    public static function generateUid(string $userName, string $sitePrefix = null): string
    {
        if ($sitePrefix === null) {
            $sitePrefix = Config::get('buffalo_sites.default_site', 'pwf');
        }

        $siteConfig = self::getSiteConfig($sitePrefix);
        if (!$siteConfig) {
            throw new \Exception("Invalid site prefix: {$sitePrefix}");
        }

        // Encode username to base64 (URL-safe)
        $encoded = rtrim(strtr(base64_encode($userName), '+/', '-_'), '=');
        
        // Create a 32-character UID: prefix + encoded username + hash padding
        $prefix = $siteConfig['prefix']; // 3 chars
        $remaining = 32 - strlen($prefix);
        
        // If encoded username is longer than available space, use hash instead
        if (strlen($encoded) > $remaining - 10) {
            $hash = md5($userName . $siteConfig['site_url']);
            return $prefix . substr($hash, 0, $remaining);
        }
        
        // Pad with hash to reach 32 characters total
        $padding = substr(md5($userName . $siteConfig['site_url']), 0, $remaining - strlen($encoded));
        return $prefix . $encoded . $padding;
    }

    /**
     * Generate token with site prefix
     * Note: Buffalo provider uses UID-based tokens without secret keys
     */
    public static function generateToken(string $uid, string $sitePrefix = null): string
    {
        if ($sitePrefix === null) {
            $sitePrefix = self::extractPrefix($uid);
        }

        $siteConfig = self::getSiteConfig($sitePrefix);
        if (!$siteConfig) {
            throw new \Exception("Invalid site prefix: {$sitePrefix}");
        }

        // Generate a 64-character token using SHA256
        // Buffalo provider uses UID + site identifier + timestamp
        return hash('sha256', $uid . $siteConfig['site_url'] . time());
    }

    /**
     * Generate persistent token for user (stored in database)
     * Note: Buffalo provider uses UID-based tokens without secret keys
     */
    public static function generatePersistentToken(string $userName, string $sitePrefix = null): string
    {
        if ($sitePrefix === null) {
            $sitePrefix = Config::get('buffalo_sites.default_site', 'pwf');
        }

        $siteConfig = self::getSiteConfig($sitePrefix);
        if (!$siteConfig) {
            throw new \Exception("Invalid site prefix: {$sitePrefix}");
        }

        // Generate persistent token using SHA256
        // Buffalo provider uses username + site identifier for persistent tokens
        $uniqueString = $userName . $siteConfig['site_url'] . 'buffalo-persistent-token';
        return hash('sha256', $uniqueString);
    }

    /**
     * Verify token
     */
    public static function verifyToken(string $uid, string $token): bool
    {
        try {
            $prefix = self::extractPrefix($uid);
            $siteConfig = self::getSiteConfig($prefix);
            
            if (!$siteConfig) {
                Log::warning('Buffalo Multi-Site: Invalid site prefix', ['prefix' => $prefix, 'uid' => $uid]);
                return false;
            }

            // Extract username from UID
            $userName = self::extractUserNameFromUid($uid, $prefix);
            
            if (!$userName) {
                Log::warning('Buffalo Multi-Site: Could not extract username', ['uid' => $uid]);
                return false;
            }

            // Find user
            $user = User::where('user_name', $userName)->first();
            
            if (!$user) {
                Log::warning('Buffalo Multi-Site: User not found', ['userName' => $userName]);
                return false;
            }

            // Generate expected token
            $expectedToken = self::generatePersistentToken($userName, $prefix);

            $isValid = hash_equals($expectedToken, $token);

            if ($isValid) {
                Log::info('Buffalo Multi-Site: Token verified successfully', [
                    'site' => $siteConfig['name'],
                    'prefix' => $prefix,
                    'user' => $userName
                ]);
            } else {
                Log::warning('Buffalo Multi-Site: Token verification failed', [
                    'site' => $siteConfig['name'],
                    'prefix' => $prefix,
                    'user' => $userName,
                    'provided_token' => $token,
                    'expected_token' => $expectedToken,
                    'token_generation_string' => $userName . $siteConfig['site_url'] . 'buffalo-persistent-token'
                ]);
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Buffalo Multi-Site: Token verification error', [
                'error' => $e->getMessage(),
                'uid' => $uid
            ]);
            return false;
        }
    }

    /**
     * Extract username from UID
     */
    public static function extractUserNameFromUid(string $uid, string $prefix = null): ?string
    {
        if ($prefix === null) {
            $prefix = self::extractPrefix($uid);
        }

        $siteConfig = self::getSiteConfig($prefix);
        if (!$siteConfig) {
            return null;
        }

        // Remove prefix (first 3 characters)
        $uidWithoutPrefix = substr($uid, 3);
        
        // Try to decode the base64 encoded part
        try {
            // Find the encoded username part (before the hash padding)
            // Try different lengths to find valid base64
            for ($len = strlen($uidWithoutPrefix); $len >= 4; $len--) {
                $encodedPart = substr($uidWithoutPrefix, 0, $len);
                
                // Add back padding if needed
                $paddedEncoded = $encodedPart . str_repeat('=', (4 - strlen($encodedPart) % 4) % 4);
                
                // Try to decode
                $decoded = base64_decode(strtr($paddedEncoded, '-_', '+/'), true);
                
                // Check if decoded string is valid UTF-8 and not empty
                if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8') && !empty($decoded)) {
                    // Check if this username exists (use raw query to avoid encoding issues)
                    try {
                        $user = User::where('user_name', $decoded)->first();
                        if ($user) {
                            Log::info('Buffalo Multi-Site: Username extracted successfully', [
                                'uid' => $uid,
                                'username' => $decoded
                            ]);
                            return $decoded;
                        }
                    } catch (\Exception $dbError) {
                        // Skip this decoded value if it causes DB error
                        Log::debug('Buffalo Multi-Site: Skipping invalid decoded value', [
                            'decoded' => bin2hex($decoded),
                            'error' => $dbError->getMessage()
                        ]);
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Buffalo Multi-Site: Failed to decode UID', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: Search by UID pattern in database (more efficient query)
        Log::info('Buffalo Multi-Site: Using fallback UID search', ['uid' => $uid]);
        
        // Get users with similar patterns (limit to reasonable amount)
        $users = User::whereNotNull('user_name')
            ->where('user_name', '!=', '')
            ->limit(1000)
            ->get();
            
        foreach ($users as $user) {
            $generatedUid = self::generateUid($user->user_name, $prefix);
            if ($generatedUid === $uid) {
                Log::info('Buffalo Multi-Site: Username found via fallback', [
                    'uid' => $uid,
                    'username' => $user->user_name
                ]);
                return $user->user_name;
            }
        }

        Log::warning('Buffalo Multi-Site: Could not extract username from UID', [
            'uid' => $uid,
            'prefix' => $prefix
        ]);

        return null;
    }

    /**
     * Get game URL for user
     */
    public static function getGameUrl(User $user, int $roomId = 2, string $sitePrefix = null): string
    {
        if ($sitePrefix === null) {
            $sitePrefix = Config::get('buffalo_sites.default_site', 'pwf');
        }

        $siteConfig = self::getSiteConfig($sitePrefix);
        if (!$siteConfig) {
            throw new \Exception("Invalid site prefix: {$sitePrefix}");
        }

        $uid = self::generateUid($user->user_name, $sitePrefix);
        $token = self::generatePersistentToken($user->user_name, $sitePrefix);

        $data = [
            "gameId" => Config::get('buffalo_sites.game_id', 23),
            "roomId" => $roomId,
            "uid" => $uid,
            "token" => $token,
            "lobbyUrl" => $siteConfig['lobby_url'],
        ];

        $baseUrl = Config::get('buffalo_sites.base_game_url', 'http://prime7.wlkfkskakdf.com/');
        return $baseUrl . '?' . http_build_query($data);
    }

    /**
     * Check if site should be handled locally
     */
    public static function isLocalSite(string $prefix): bool
    {
        $siteConfig = self::getSiteConfig($prefix);
        return $siteConfig ? ($siteConfig['is_local'] ?? false) : false;
    }

    /**
     * Get external API endpoint URL
     */
    public static function getExternalApiUrl(string $prefix, string $endpoint): ?string
    {
        $siteConfig = self::getSiteConfig($prefix);
        
        if (!$siteConfig || $siteConfig['is_local']) {
            return null;
        }

        $apiUrl = $siteConfig['api_url'];
        $endpointPath = $siteConfig['api_endpoints'][$endpoint] ?? null;

        if (!$endpointPath) {
            return null;
        }

        return rtrim($apiUrl, '/') . $endpointPath;
    }
}

