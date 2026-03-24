<?php

namespace App\Services;

use App\Models\UserProfile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EncryptionService
{
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function decrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }

    /**
     * 프로필 데이터에서 encrypted_* 필드를 자동 생성한다.
     *
     * @param array $data name, phone, email, birth_date 키를 포함할 수 있는 배열
     * @return array encrypted_* 필드 + is_encrypted 플래그가 포함된 배열
     */
    public function encryptProfileData(array $data): array
    {
        $encrypted = [];

        if (isset($data['name'])) {
            $encrypted['encrypted_name'] = $this->encrypt($data['name']);
        }

        if (array_key_exists('phone', $data)) {
            $encrypted['encrypted_phone'] = $data['phone']
                ? $this->encrypt($data['phone'])
                : null;
        }

        if (array_key_exists('email', $data)) {
            $encrypted['encrypted_email'] = $data['email']
                ? $this->encrypt($data['email'])
                : null;
        }

        if (array_key_exists('birth_date', $data)) {
            $encrypted['encrypted_birth_date'] = $data['birth_date']
                ? $this->encrypt($data['birth_date'])
                : null;
        }

        if (!empty($encrypted)) {
            $encrypted['is_encrypted'] = true;
        }

        return $encrypted;
    }

    public function migrateProfile(UserProfile $profile): void
    {
        $data = [];

        if ($profile->name) {
            $data['encrypted_name'] = $this->encrypt($profile->name);
        }

        if ($profile->phone) {
            $data['encrypted_phone'] = $this->encrypt($profile->phone);
        }

        if ($profile->email) {
            $data['encrypted_email'] = $this->encrypt($profile->email);
        }

        if ($profile->birth_date) {
            $data['encrypted_birth_date'] = $this->encrypt($profile->birth_date->format('Y-m-d'));
        }

        $data['is_encrypted'] = true;

        $profile->update($data);
    }
}
