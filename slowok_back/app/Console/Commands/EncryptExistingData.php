<?php

namespace App\Console\Commands;

use App\Models\UserProfile;
use App\Services\EncryptionService;
use Illuminate\Console\Command;

class EncryptExistingData extends Command
{
    protected $signature = 'encrypt:existing-data';
    protected $description = '기존 평문 사용자 데이터를 암호화합니다.';

    public function handle(EncryptionService $encryptionService): int
    {
        $query = UserProfile::where('is_encrypted', false);
        $total = $query->count();

        if ($total === 0) {
            $this->info('암호화할 데이터가 없습니다.');
            return self::SUCCESS;
        }

        $this->info("총 {$total}건의 프로필을 암호화합니다...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = 0;

        $query->chunkById(100, function ($profiles) use ($encryptionService, $bar, &$failed) {
            foreach ($profiles as $profile) {
                try {
                    $encryptionService->migrateProfile($profile);
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("프로필 ID {$profile->profile_id} 암호화 실패: {$e->getMessage()}");
                }
                $bar->advance();
            }
        }, 'profile_id');

        $bar->finish();
        $this->newLine(2);

        $success = $total - $failed;
        $this->info("완료: 성공 {$success}건, 실패 {$failed}건");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
