<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\ScreeningController;
use App\Http\Controllers\Api\User\CurriculumController;
use App\Http\Controllers\Api\User\LearningController;
use App\Http\Controllers\Api\User\ChallengeController;
use App\Http\Controllers\Api\User\RewardCardController;
use App\Http\Controllers\Api\User\AssessmentController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\ReportController;
use App\Http\Controllers\Api\User\VoiceRecordingController;
use App\Http\Controllers\Api\User\ChildProfileController;
use App\Http\Controllers\Api\User\ParentDashboardController;
use App\Http\Controllers\Api\User\DeviceTokenController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\ScreeningTestController;
use App\Http\Controllers\Api\Admin\ScreeningQuestionController;
use App\Http\Controllers\Api\Admin\ScreeningResultController;
use App\Http\Controllers\Api\Admin\LearningCategoryController;
use App\Http\Controllers\Api\Admin\LearningContentController;
use App\Http\Controllers\Api\Admin\ChallengeController as AdminChallengeController;
use App\Http\Controllers\Api\Admin\RewardCardController as AdminRewardCardController;
use App\Http\Controllers\Api\Admin\InstitutionController;
use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\FileUploadController;
use App\Http\Controllers\Api\Admin\ChallengeQuestionController;
use App\Http\Controllers\Api\Admin\AdminManagementController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\ContentAssignmentController;
use App\Http\Controllers\Api\Admin\ContentPackageController;
use App\Http\Controllers\Api\Admin\RecommendationRuleController;
use App\Http\Controllers\Api\Admin\FeatureController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Api\Admin\InstitutionPlanController;
use App\Http\Controllers\Api\Admin\UserFeatureOverrideController;
use App\Http\Controllers\Api\Admin\ExportController;
use App\Http\Controllers\Api\Admin\VoiceRecordingController as AdminVoiceRecordingController;
use App\Http\Controllers\Api\Admin\LearnerMemoController;
use App\Http\Controllers\Api\User\FeatureCheckController;
use App\Http\Controllers\Api\Admin\AiContentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 인증 API (Public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// 인증 필요 API
Route::middleware('auth:sanctum')->group(function () {

    // 학습자 API
    Route::prefix('user')->middleware(['role:USER', 'active-profile'])->group(function () {
        // 초대코드로 기관 연결
        Route::post('/join-institution', [ProfileController::class, 'joinInstitution']);

        // 프로필
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        // 비밀번호 변경 / 회원 탈퇴
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/delete-account', [AuthController::class, 'deleteAccount']);

        // 진단 검사
        Route::get('/screening-tests', [ScreeningController::class, 'index']);
        Route::get('/screening-tests/{id}/questions', [ScreeningController::class, 'questions']);
        Route::post('/screening-tests/{id}/submit', [ScreeningController::class, 'submit']);
        Route::get('/screening-results', [ScreeningController::class, 'results']);

        // 커리큘럼
        Route::get('/curriculum', [CurriculumController::class, 'index']);

        // 학습 콘텐츠
        Route::get('/learning-contents', [LearningController::class, 'contents']);
        Route::post('/learning-progress', [LearningController::class, 'updateProgress']);

        // 챌린지
        Route::get('/challenges', [ChallengeController::class, 'index']);
        Route::get('/challenges/{id}', [ChallengeController::class, 'show']);
        Route::post('/challenges/{id}/attempt', [ChallengeController::class, 'attempt']);

        // 보상 카드
        Route::get('/reward-cards', [RewardCardController::class, 'index']);

        // 평가
        Route::get('/assessments', [AssessmentController::class, 'index']);

        // 알림
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        // 학습 리포트
        Route::get('/reports', [ReportController::class, 'index']);

        // 기능 접근 체크
        Route::get('/features', [FeatureCheckController::class, 'index']);

        // 디바이스 토큰 (FCM)
        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::post('/device-token/remove', [DeviceTokenController::class, 'destroy']);

        // 음성 녹음
        Route::get('/voice-recordings', [VoiceRecordingController::class, 'index']);
        Route::post('/voice-recordings', [VoiceRecordingController::class, 'store']);
        Route::delete('/voice-recordings/{id}', [VoiceRecordingController::class, 'destroy']);

    });

    // 관리자 API
    Route::prefix('admin')->middleware(['role:ADMIN,MASTER,TEST', 'institution'])->group(function () {
        // 대시보드
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('perm:dashboard_view');

        // 사용자 관리
        Route::middleware('perm:user_manage')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::get('/users/{id}', [UserManagementController::class, 'show']);
            Route::put('/users/{id}', [UserManagementController::class, 'update']);
            Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
            Route::post('/users/{id}/reset-password', [UserManagementController::class, 'resetPassword']);
        });

        // 진단 테스트 관리
        Route::apiResource('screening-tests', ScreeningTestController::class)->middleware('perm:screening_manage');

        // 진단 결과 조회
        Route::middleware('perm:screening_result_view')->group(function () {
            Route::get('/screening-results', [ScreeningResultController::class, 'index']);
            Route::delete('/screening-results/{id}', [ScreeningResultController::class, 'destroy']);
        });

        // 진단 문항 관리
        Route::middleware('perm:screening_manage')->group(function () {
            Route::get('/screening-tests/{testId}/questions', [ScreeningQuestionController::class, 'index']);
            Route::post('/screening-questions', [ScreeningQuestionController::class, 'store']);
            Route::put('/screening-questions/{id}', [ScreeningQuestionController::class, 'update']);
            Route::delete('/screening-questions/{id}', [ScreeningQuestionController::class, 'destroy']);
        });

        // 학습 카테고리 관리
        Route::apiResource('learning-categories', LearningCategoryController::class)->middleware('perm:category_manage');

        // 학습 콘텐츠 관리
        Route::apiResource('learning-contents', LearningContentController::class)->middleware('perm:content_manage');

        // 파일 업로드
        Route::middleware('perm:content_manage')->group(function () {
            Route::post('/upload', [FileUploadController::class, 'upload']);
            Route::post('/upload/delete', [FileUploadController::class, 'delete']);
        });

        // 챌린지 관리
        Route::apiResource('challenges', AdminChallengeController::class)->middleware('perm:challenge_manage');

        // 챌린지 문항 관리
        Route::middleware('perm:challenge_manage')->group(function () {
            Route::get('/challenges/{challengeId}/questions', [ChallengeQuestionController::class, 'index']);
            Route::post('/challenge-questions', [ChallengeQuestionController::class, 'store']);
            Route::put('/challenge-questions/{id}', [ChallengeQuestionController::class, 'update']);
            Route::delete('/challenge-questions/{id}', [ChallengeQuestionController::class, 'destroy']);
        });

        // 보상 카드 관리
        Route::apiResource('reward-cards', AdminRewardCardController::class)->middleware('perm:reward_manage');

        // 기관 관리
        Route::apiResource('institutions', InstitutionController::class)->middleware('perm:institution_manage');

        // 구독 관리
        Route::apiResource('subscriptions', SubscriptionController::class)->middleware('perm:subscription_manage');

        // 리포트 조회
        Route::get('/reports', [AdminReportController::class, 'index'])->middleware('perm:report_view');

        // 콘텐츠 할당 관리
        Route::middleware('perm:content_assign')->group(function () {
            Route::get('/content-assignments', [ContentAssignmentController::class, 'index']);
            Route::post('/content-assignments', [ContentAssignmentController::class, 'store']);
            Route::post('/content-assignments/bulk', [ContentAssignmentController::class, 'bulkAssign']);
            Route::post('/content-assignments/reorder', [ContentAssignmentController::class, 'reorder']);
            Route::delete('/content-assignments/{id}', [ContentAssignmentController::class, 'destroy']);
        });

        // 콘텐츠 패키지 관리
        Route::middleware('perm:package_manage')->group(function () {
            Route::apiResource('content-packages', ContentPackageController::class);
            Route::post('/content-packages/{id}/assign', [ContentPackageController::class, 'assignPackage']);
        });

        // 추천 규칙 관리
        Route::middleware('perm:recommendation_manage')->group(function () {
            Route::apiResource('recommendation-rules', RecommendationRuleController::class)->except(['show']);
        });

        // 음성 녹음 관리 + 피드백
        Route::middleware('perm:user_manage')->prefix('voice-recordings')->group(function () {
            Route::get('/', [AdminVoiceRecordingController::class, 'index']);
            Route::get('/{recordingId}/feedbacks', [AdminVoiceRecordingController::class, 'feedbacks']);
            Route::post('/{recordingId}/feedback', [AdminVoiceRecordingController::class, 'storeFeedback']);
            Route::delete('/feedback/{feedbackId}', [AdminVoiceRecordingController::class, 'destroyFeedback']);
        });

        // 사용자별 기능 override (ADMIN이 관리)
        Route::middleware('perm:user_manage')->group(function () {
            Route::get('/users/{profileId}/features', [UserFeatureOverrideController::class, 'index']);
            Route::post('/users/{profileId}/features', [UserFeatureOverrideController::class, 'store']);
            Route::delete('/users/{profileId}/features/{overrideId}', [UserFeatureOverrideController::class, 'destroy']);
        });

        // 데이터 내보내기 (CSV)
        Route::middleware('perm:user_manage')->prefix('export')->group(function () {
            Route::get('/users', [ExportController::class, 'users']);
            Route::get('/learning-progress', [ExportController::class, 'learningProgress']);
            Route::get('/screening-results', [ExportController::class, 'screeningResults']);
        });

        // 학습자 메모 관리
        Route::middleware('perm:user_manage')->group(function () {
            Route::get('/users/{profileId}/memos', [LearnerMemoController::class, 'index']);
            Route::post('/users/{profileId}/memos', [LearnerMemoController::class, 'store']);
            Route::put('/memos/{memoId}', [LearnerMemoController::class, 'update']);
            Route::delete('/memos/{memoId}', [LearnerMemoController::class, 'destroy']);
            Route::patch('/memos/{memoId}/pin', [LearnerMemoController::class, 'togglePin']);
        });

        // AI 콘텐츠 생성
        Route::middleware('perm:content_manage')->prefix('ai-content')->group(function () {
            Route::get('/usage', [AiContentController::class, 'usage']);
            Route::post('/generate', [AiContentController::class, 'generate']);
            Route::post('/save', [AiContentController::class, 'save']);
        });

        // MASTER 전용 - 관리자 관리
        Route::prefix('master')->middleware('role:MASTER')->group(function () {
            Route::get('/admins', [AdminManagementController::class, 'index']);
            Route::post('/admins', [AdminManagementController::class, 'store']);
            Route::put('/admins/{id}', [AdminManagementController::class, 'update']);
            Route::delete('/admins/{id}', [AdminManagementController::class, 'destroy']);
            Route::post('/admins/{id}/reset-password', [AdminManagementController::class, 'resetPassword']);

            // 권한 관리
            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::get('/permissions/{id}', [PermissionController::class, 'show']);
            Route::put('/permissions/{id}', [PermissionController::class, 'update']);

            // 기능 관리
            Route::apiResource('features', FeatureController::class)->except(['show']);

            // 플랜 관리
            Route::apiResource('plans', PlanController::class);

            // 기관 플랜 배정
            Route::get('/institution-plans', [InstitutionPlanController::class, 'index']);
            Route::post('/institution-plans', [InstitutionPlanController::class, 'store']);
            Route::delete('/institution-plans/{id}', [InstitutionPlanController::class, 'destroy']);
        });
    });
});
