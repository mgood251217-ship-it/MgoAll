<?php

final class LoginRateLimiter
{
    private $path;

    public function __construct($path = null)
    {
        $this->path = $path ?: sys_get_temp_dir() . '/mgo-login-' . hash('sha256', dirname(__DIR__, 2)) . '.json';
    }

    public function consume($username, $address, $now = null)
    {
        $now = $now ?? time();
        $window = 300;
        $limits = [
            'account:' . hash('sha256', strtolower(trim($username))) => 10,
            'ip:' . hash('sha256', $address) => 100,
        ];
        $handle = @fopen($this->path, 'c+');
        if (!$handle) {
            throw new RuntimeException('Cannot open login rate limit storage');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock login rate limit storage');
            }
            @chmod($this->path, 0600);
            $raw = stream_get_contents($handle);
            $entries = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($entries)) {
                throw new RuntimeException('Invalid login rate limit storage');
            }
            foreach ($entries as $key => $entry) {
                if (!is_array($entry) || !isset($entry['expires'], $entry['count'])) {
                    throw new RuntimeException('Invalid login rate limit entry');
                }
                if ($entry['expires'] <= $now) unset($entries[$key]);
            }
            $retryAfter = 0;
            foreach ($limits as $key => $limit) {
                if (isset($entries[$key]) && $entries[$key]['count'] >= $limit) {
                    $retryAfter = max($retryAfter, $entries[$key]['expires'] - $now);
                }
            }
            if ($retryAfter > 0) return $retryAfter;
            if (count($entries) > 20000) {
                throw new RuntimeException('Login rate limit storage capacity reached');
            }
            foreach ($limits as $key => $limit) {
                if (!isset($entries[$key])) $entries[$key] = ['count' => 0, 'expires' => $now + $window];
                $entries[$key]['count']++;
            }
            $encoded = json_encode($entries, JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new RuntimeException('Cannot persist login rate limit');
            }
            return 0;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
