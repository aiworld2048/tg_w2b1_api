<?php

namespace App\Services;

use App\Models\User;

class BuffaloGameService
{
    /**
     * Site configuration - Updated for Production
     * IMPORTANT: Must match the site_url in config/buffalo_sites.php
     */
    private const SITE_NAME = 'https://ag.ponewine20x.xyz';
    private const SITE_PREFIX = 'pwf';
    // No secret key needed - API provider uses UUID + token format

    /**
     * Generate UUID (32 characters) for Buffalo API
     * Format: prefix(3) + base64_encoded_username(variable) + padding to 32 chars
     */
    public static function generateUid(string $userName): string
    {
        // Encode username to base64 (URL-safe)
        $encoded = rtrim(strtr(base64_encode($userName), '+/', '-_'), '=');
        
        // Create a 32-character UID: prefix + encoded username + hash padding
        $prefix = self::SITE_PREFIX; // 3 chars: "gam"
        $remaining = 32 - strlen($prefix);
        
        // If encoded username is longer than available space, use hash instead
        if (strlen($encoded) > $remaining - 10) {
            $hash = md5($userName . self::SITE_NAME);
            return $prefix . substr($hash, 0, $remaining);
        }
        
        // Pad with hash to reach 32 characters total
        $padding = substr(md5($userName . self::SITE_NAME), 0, $remaining - strlen($encoded));
        return $prefix . $encoded . $padding;
    }

    /**
     * Generate token (64 characters) for Buffalo API
     */
    public static function generateToken(string $uid): string
    {
        // Generate a 64-character token using SHA256
        return hash('sha256', $uid . self::SITE_NAME . time());
    }

    /**
     * Generate persistent token for user (stored in database)
     * Note: Only uses username for consistency (no user_id to allow verification without full user object)
     */
    public static function generatePersistentToken(User $user): string
    {
        // Generate a persistent token that doesn't change with time
        // Only use username (not user_id) so we can verify with just the username
        return hash('sha256', $user->user_name . self::SITE_NAME . 'buffalo-persistent-token');
    }

    /**
     * Generate complete Buffalo authentication data for a user
     */
    public static function generateBuffaloAuth(User $user): array
    {
        $uid = self::generateUid($user->user_name);
        $token = self::generatePersistentToken($user); // Use persistent token

        return [
            'uid' => $uid,
            'token' => $token,
        ];
    }

    /**
     * Extract user_name from Buffalo UID (32 characters)
     */
    public static function extractUserNameFromUid(string $uid): ?string
    {
        // Verify prefix
        if (!str_starts_with($uid, self::SITE_PREFIX)) {
            \Log::warning('Buffalo UID has invalid prefix', ['uid' => $uid]);
            return null;
        }
        
        // Remove prefix (3 chars: "pwf")
        $withoutPrefix = substr($uid, strlen(self::SITE_PREFIX));
        
        // Try to extract encoded username by trying different lengths
        // We know the format is: encodedUsername + hashPadding
        // Try to find a valid base64 decoded username
        for ($i = 1; $i <= strlen($withoutPrefix); $i++) {
            $possibleEncoded = substr($withoutPrefix, 0, $i);
            
            try {
                // Decode from URL-safe base64
                $decoded = base64_decode(strtr($possibleEncoded, '-_', '+/'));
                
                // Check if decoded string is valid UTF-8 and not empty
                if ($decoded && strlen($decoded) > 0 && mb_check_encoding($decoded, 'UTF-8')) {
                    try {
                        $user = \App\Models\User::where('user_name', $decoded)->first();
                        if ($user) {
                            // Verify by regenerating UID
                            $regeneratedUid = self::generateUid($decoded);
                            if ($regeneratedUid === $uid) {
                                \Log::info('Buffalo UID extracted successfully', [
                                    'uid' => $uid,
                                    'username' => $decoded
                                ]);
                                return $decoded;
                            }
                        }
                    } catch (\Exception $dbError) {
                        // Skip this decoded value if it causes DB error (invalid UTF-8)
                        \Log::debug('Buffalo: Skipping invalid decoded value', [
                            'decoded_hex' => bin2hex($decoded),
                            'error' => $dbError->getMessage()
                        ]);
                        continue;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback: search by regenerating UIDs for all users (limit to reasonable amount)
        \Log::info('Buffalo UID extraction fallback to database search', ['uid' => $uid]);
        $users = \App\Models\User::whereNotNull('user_name')
            ->where('user_name', '!=', '')
            ->limit(1000)
            ->get();
            
        foreach ($users as $user) {
            $generatedUid = self::generateUid($user->user_name);
            if ($generatedUid === $uid) {
                \Log::info('Buffalo UID found via fallback', [
                    'uid' => $uid,
                    'username' => $user->user_name
                ]);
                return $user->user_name;
            }
        }
        
        \Log::error('Buffalo UID could not be matched to any user', ['uid' => $uid]);
        return null;
    }

    /**
     * Verify token for Buffalo API (no secret key verification)
     */
    public static function verifyToken(string $uid, string $token): bool
    {
        // Find the user first
        $userName = self::extractUserNameFromUid($uid);
        
        if (!$userName) {
            \Log::warning('Buffalo token verification failed: Could not extract username from UID', [
                'uid' => $uid
            ]);
            return false;
        }
        
        $user = \App\Models\User::where('user_name', $userName)->first();
        
        if (!$user) {
            \Log::warning('Buffalo token verification failed: User not found', [
                'uid' => $uid,
                'extracted_username' => $userName
            ]);
            return false;
        }
        
        // Generate the expected token and compare
        $expectedToken = self::generatePersistentToken($user);
        $isValid = hash_equals($expectedToken, $token);
        
        if (!$isValid) {
            \Log::warning('Buffalo token verification failed: Token mismatch', [
                'uid' => $uid,
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'provided_token' => $token,
                'expected_token' => $expectedToken,
                'token_generation_string' => $user->user_name . self::SITE_NAME . 'buffalo-persistent-token'
            ]);
        } else {
            \Log::info('Buffalo token verified successfully', [
                'uid' => $uid,
                'user_name' => $user->user_name
            ]);
        }
        
        return $isValid;
    }

    /**
     * Get site information
     */
    public static function getSiteInfo(): array
    {
        return [
            'site_name' => self::SITE_NAME,
            'site_prefix' => self::SITE_PREFIX,
        ];
    }

    /**
     * Generate Buffalo game URL (Exact format from provider)
     * Based on provider examples: http://prime7.wlkfkskakdf.com/?gameId=23&roomId=1&uid=...&token=...&lobbyUrl=...
     */
    public static function generateGameUrl(User $user, int $roomId = 1, string $lobbyUrl = ''): string
    {
        // Use HTTP exactly as provider examples show
        $baseUrl = 'http://prime7.wlkfkskakdf.com/';
        $gameId = 23; // Buffalo game ID from provider examples
        
        // Use provided lobby URL or default to production site
        // $finalLobbyUrl = $lobbyUrl ?: 'https://africanbuffalo.vip';

        $finalLobbyUrl = $lobbyUrl ?: 'https://m.ponewine20x.xyz';
        
        // Generate the base URL without auth (auth will be added by controller)
        $gameUrl = $baseUrl . '?gameId=' . $gameId . 
                   '&roomId=' . $roomId . 
                   '&lobbyUrl=' . urlencode($finalLobbyUrl);
        
        return $gameUrl;
    }
    

    /**
     * Get room configuration
     */
    public static function getRoomConfig(): array
    {
        return [
            1 => ['min_bet' => 50, 'name' => '50 အခန်း', 'level' => 'Low'],
            2 => ['min_bet' => 500, 'name' => '500 အခန်း', 'level' => 'Medium'],
            3 => ['min_bet' => 5000, 'name' => '5000 အခန်း', 'level' => 'High'],
            4 => ['min_bet' => 10000, 'name' => '10000 အခန်း', 'level' => 'VIP'],
        ];
    }

    /**
     * Get available rooms for user based on balance
     */
    public static function getAvailableRooms(User $user): array
    {
        $userBalance = $user->balanceFloat; // Use bavix wallet trait
        $rooms = self::getRoomConfig();
        $availableRooms = [];

        foreach ($rooms as $roomId => $config) {
            if ($userBalance >= $config['min_bet']) {
                $availableRooms[$roomId] = $config;
            }
        }

        return $availableRooms;
    }

}
