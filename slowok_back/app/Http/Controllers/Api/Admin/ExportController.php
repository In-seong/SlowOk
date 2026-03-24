<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\LearningProgress;
use App\Models\ScreeningResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends BaseAdminController
{
    /**
     * 사용자 목록 CSV 내보내기
     */
    public function users(Request $request): StreamedResponse
    {
        $instId = $this->getInstitutionId($request);

        $users = Account::where('role', 'USER')
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->with('profile')
            ->latest()
            ->get();

        $headers = ['아이디', '이름', '연락처', '이메일', '유형', '상태', '마지막 로그인', '가입일'];

        return $this->streamCsv('users_export', $headers, function () use ($users) {
            foreach ($users as $user) {
                $profile = $user->profile;
                yield [
                    $user->username,
                    $profile?->decrypted_name ?? $profile?->name ?? '',
                    $profile?->decrypted_phone ?? $profile?->phone ?? '',
                    $profile?->decrypted_email ?? $profile?->email ?? '',
                    $profile?->user_type ?? '',
                    $user->is_active ? '활성' : '비활성',
                    $user->last_login_at ? date('Y-m-d H:i', strtotime($user->last_login_at)) : '',
                    $user->created_at ? $user->created_at->format('Y-m-d H:i') : '',
                ];
            }
        });
    }

    /**
     * 학습 진행 현황 CSV 내보내기
     */
    public function learningProgress(Request $request): StreamedResponse
    {
        $instId = $this->getInstitutionId($request);

        $progress = LearningProgress::with(['profile', 'content'])
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('profile', function ($pq) use ($instId) {
                    $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                });
            })
            ->latest()
            ->get();

        $headers = ['학습자', '콘텐츠', '유형', '난이도', '상태', '점수', '시도횟수', '시작일', '최종학습일'];

        return $this->streamCsv('learning_progress_export', $headers, function () use ($progress) {
            foreach ($progress as $p) {
                $statusMap = [
                    'NOT_STARTED' => '미시작',
                    'IN_PROGRESS' => '진행중',
                    'COMPLETED' => '완료',
                ];
                yield [
                    $p->profile?->decrypted_name ?? $p->profile?->name ?? '',
                    $p->content?->title ?? '',
                    $p->content?->content_type ?? '',
                    $p->content?->difficulty_level ?? '',
                    $statusMap[$p->status] ?? $p->status,
                    $p->score ?? '',
                    $p->attempts,
                    $p->created_at ? $p->created_at->format('Y-m-d H:i') : '',
                    $p->updated_at ? $p->updated_at->format('Y-m-d H:i') : '',
                ];
            }
        });
    }

    /**
     * 진단 결과 CSV 내보내기
     */
    public function screeningResults(Request $request): StreamedResponse
    {
        $instId = $this->getInstitutionId($request);

        $results = ScreeningResult::with(['test', 'profile'])
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('profile', function ($pq) use ($instId) {
                    $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                });
            })
            ->latest()
            ->get();

        $headers = ['학습자', '진단명', '점수', '레벨', '검사일'];

        return $this->streamCsv('screening_results_export', $headers, function () use ($results) {
            foreach ($results as $r) {
                yield [
                    $r->profile?->decrypted_name ?? $r->profile?->name ?? '',
                    $r->test?->title ?? '',
                    $r->score,
                    $r->level ?? '',
                    $r->created_at ? $r->created_at->format('Y-m-d H:i') : '',
                ];
            }
        });
    }

    /**
     * CSV 스트리밍 응답 생성
     */
    private function streamCsv(string $filename, array $headers, callable $rowGenerator): StreamedResponse
    {
        $date = date('Ymd');
        $fullFilename = "{$filename}_{$date}.csv";

        return response()->streamDownload(function () use ($headers, $rowGenerator) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM (Excel 한글 깨짐 방지)
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rowGenerator() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fullFilename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
