# SlowOK 개발자 참고 문서

> **목적**: 다수의 개발자(또는 Claude CLI 에이전트)가 동일한 코딩 규칙과 패턴으로 개발할 수 있도록 프로젝트 전반의 컨벤션을 정리한 문서.
> **최종 업데이트**: 2026-03-03

---

## 1. 프로젝트 구조

```
slowok/
├── slowok_front/        # Vue 3 + TypeScript 프론트엔드 (Dual SPA)
├── slowok_back/         # Laravel 12 백엔드 (API 서버)
├── CLAUDE.md            # Claude CLI 공통 설정
└── DEV_REFERENCE.md     # 이 문서
```

### 1.1 역할별 서비스 구분

| 역할 | 한국어 | 프론트엔드 경로 | API Prefix | 인증 방식 |
|------|--------|---------------|-----------|---------|
| User (학습자/보호자) | 사용자 | `src/user/` | `/api/user/*` | Username/Password → Sanctum Token |
| Admin (관리자) | 관리자 | `src/admin/` | `/api/admin/*` | Username/Password → Sanctum Token |

### 1.2 프로덕션 도메인

- User 앱: `slowokuser.revuplan.com`
- Admin 앱: `slowokadmin.revuplan.com`

---

## 2. 백엔드 컨벤션 (Laravel 12)

### 2.1 디렉토리 구조

```
slowok_back/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php         # 로그인/회원가입/로그아웃
│   │   │   ├── User/                      # 사용자 전용 API (9개 컨트롤러)
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── ScreeningController.php
│   │   │   │   ├── CurriculumController.php
│   │   │   │   ├── LearningController.php
│   │   │   │   ├── ChallengeController.php
│   │   │   │   ├── RewardCardController.php
│   │   │   │   ├── AssessmentController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   └── ReportController.php
│   │   │   └── Admin/                     # 관리자 전용 API (11개 컨트롤러)
│   │   │       ├── DashboardController.php
│   │   │       ├── UserManagementController.php
│   │   │       ├── ScreeningTestController.php
│   │   │       ├── ScreeningQuestionController.php
│   │   │       ├── LearningCategoryController.php
│   │   │       ├── LearningContentController.php
│   │   │       ├── ChallengeController.php
│   │   │       ├── RewardCardController.php
│   │   │       ├── InstitutionController.php
│   │   │       ├── SubscriptionController.php
│   │   │       └── ReportController.php
│   │   └── Middleware/
│   │       └── CheckRole.php              # 역할 기반 접근 제어
│   ├── Models/                            # 17개 Eloquent 모델
│   └── ...
├── routes/
│   └── api.php                            # 모든 API 라우트
├── database/
│   ├── database.sqlite                    # 개발용 SQLite DB
│   └── migrations/                        # 마이그레이션 파일
└── public/
    ├── user/                              # User 앱 빌드 결과물
    └── admin/                             # Admin 앱 빌드 결과물
```

### 2.2 API 응답 형식 (필수 준수)

모든 API 응답은 아래 형식을 따른다:

```php
// 단건 응답
return response()->json([
    'success' => true,
    'data' => $model,
    'message' => '처리 완료 메시지',
], 200);

// 목록 응답 (컬렉션)
return response()->json([
    'success' => true,
    'data' => $collection,
]);

// 에러 응답
return response()->json([
    'success' => false,
    'message' => '에러 메시지',
], 400|403|404|422);
```

**404 핸들링** (`bootstrap/app.php`):
```php
// ModelNotFoundException과 NotFoundHttpException 자동 처리
return response()->json(['success' => false, 'message' => '리소스를 찾을 수 없습니다.'], 404);
```

### 2.3 컨트롤러 패턴

```php
class ExampleController extends Controller
{
    // 1. 인증된 사용자 확인
    $user = $request->user();

    // 2. 인라인 유효성 검사 (FormRequest 클래스 미사용)
    $validated = $request->validate([
        'title' => 'required|string|max:200',
        'content_data' => 'nullable|array',
    ]);

    // 3. 모델 조회 (firstOrFail 사용)
    $model = Model::where('model_id', $id)->firstOrFail();

    // 4. 표준 응답 반환
    return response()->json([
        'success' => true,
        'data' => $model,
        'message' => '처리 완료',
    ]);
}
```

**핵심 규칙**:
- **FormRequest 클래스를 사용하지 않는다** → 컨트롤러 내 `$request->validate()` 인라인 사용
- **`firstOrFail()`** 사용 → 존재하지 않으면 자동 404
- **soft delete** → `is_active = false`로 처리 (실제 삭제 금지, 해당 컬럼이 있는 경우)

### 2.4 라우트 컨벤션

```php
// routes/api.php 구조
// 공개 라우트
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// 인증 필요
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', ...);
    Route::get('/auth/me', ...);

    // User 전용 (role:USER)
    Route::prefix('user')->middleware('role:USER')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::get('/screening-tests', [ScreeningController::class, 'index']);
        // ...
    });

    // Admin 전용 (role:ADMIN)
    Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
        Route::apiResource('screening-tests', ScreeningTestController::class);
        Route::apiResource('learning-contents', LearningContentController::class);
        // ...
    });
});
```

**라우트 URL 규칙**:
- 복수형 명사 사용: `screening-tests`, `learning-contents`, `challenges`
- kebab-case: `learning-contents` (NOT `learningContents`)
- apiResource 사용 시: 표준 RESTful 엔드포인트 자동 생성

### 2.5 Model 컨벤션

```php
class ModelName extends Model
{
    protected $table = 'table_name';           // 테이블명 명시
    protected $primaryKey = 'pk_column_name';  // PK 명시 ({entity}_id)

    protected $fillable = [
        'field1', 'field2', ...
    ];

    protected $casts = [
        'content_data' => 'array',             // JSON 컬럼
        'birth_date' => 'date',                // 날짜 컬럼
        'last_login_at' => 'datetime',         // 일시 컬럼
    ];

    protected $hidden = [
        'password_hash',                       // 민감 정보
    ];

    // 관계 정의 (FK와 로컬키 항상 명시)
    public function category(): BelongsTo
    {
        return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id');
    }
}
```

**주의사항**:
- `$fillable`에 누락된 필드는 `create()`/`update()` 시 무시됨 → 새 컬럼 추가 시 반드시 `$fillable` 업데이트
- 테이블명, PK명을 항상 명시 (Laravel 기본 규칙에 의존하지 않음)
- FK 관계 정의 시 두 번째, 세 번째 인자 모두 명시

### 2.6 ID 체계

모든 테이블의 PK는 **auto increment 정수** 사용, 컬럼명은 `{entity}_id`.

| 모델 | 테이블 | PK |
|------|--------|-----|
| Account | account | account_id |
| UserProfile | user_profile | profile_id |
| LearningCategory | learning_category | category_id |
| ScreeningTest | screening_test | test_id |
| ScreeningQuestion | screening_question | question_id |
| ScreeningResult | screening_result | result_id |
| Curriculum | curriculum | curriculum_id |
| LearningContent | learning_content | content_id |
| LearningProgress | learning_progress | progress_id |
| Challenge | challenge | challenge_id |
| ChallengeAttempt | challenge_attempt | attempt_id |
| RewardCard | reward_card | card_id |
| Assessment | assessment | assessment_id |
| Subscription | subscription | subscription_id |
| Notification | notification | notification_id |
| LearningReport | learning_report | report_id |
| Institution | institution | institution_id |

### 2.7 인증/인가

#### 인증 (Sanctum Token)
```
POST /api/auth/login     → { login_id, password, role: 'USER'|'ADMIN' } → Bearer Token
POST /api/auth/register  → { login_id, password, password_confirmation, name, role: 'USER' }
POST /api/auth/logout    → 토큰 무효화
GET  /api/auth/me        → 현재 사용자 정보
```

#### Account 모델 특이사항
- `password_hash` 컬럼 사용 (Laravel 기본 `password` 아님)
- `getAuthPassword()` 오버라이드: `return $this->password_hash`
- 역할 상수: `Account::ROLE_USER = 'USER'`, `Account::ROLE_ADMIN = 'ADMIN'`

#### 역할 기반 접근 제어
- Middleware: `CheckRole` → `$request->user()->role` 확인
- 사용법: `->middleware('role:USER')`, `->middleware('role:ADMIN')`
- 실패 시: `{ success: false, message: '접근 권한이 없습니다.' }` (403)

### 2.8 로컬 스토리지 규칙

```php
// 파일 업로드 (로컬 public disk)
$path = $file->storeAs('videos', $filename, 'public');
// 결과: storage/app/public/videos/filename.mp4

// 파일 URL (public 경로)
$url = '/storage/' . $path;
// 결과: /storage/videos/filename.mp4

// 파일 삭제
Storage::disk('public')->delete($path);
```

**사전 설정 필요**: `php artisan storage:link` (public/storage → storage/app/public 심링크)

---

## 3. 프론트엔드 컨벤션 (Vue 3.5 + TypeScript 5.9)

### 3.1 디렉토리 구조

```
slowok_front/
├── src/
│   ├── shared/                # 공통 모듈 (User/Admin 공용)
│   │   ├── api/
│   │   │   ├── index.ts       # Axios 인스턴스 (인터셉터, 토큰 관리)
│   │   │   ├── assessmentApi.ts
│   │   │   ├── challengeApi.ts
│   │   │   ├── dashboardApi.ts
│   │   │   ├── learningApi.ts
│   │   │   ├── notificationApi.ts
│   │   │   ├── reportApi.ts
│   │   │   └── screeningApi.ts
│   │   ├── components/
│   │   │   ├── form/          # FormInput
│   │   │   ├── layout/        # AppHeader, BackHeader, BottomNav
│   │   │   └── ui/            # ActionButton, CardSection, StatusBadge 등
│   │   ├── styles/
│   │   │   └── main.css       # Tailwind v4 @theme, Pretendard 폰트
│   │   ├── types/
│   │   │   └── index.ts       # 모든 공유 TypeScript 인터페이스
│   │   └── utils/
│   │       └── index.ts       # formatDate(), formatDateTime()
│   ├── user/                  # 사용자 앱
│   │   ├── App.vue
│   │   ├── main.ts
│   │   ├── router/index.ts    # createWebHistory() 사용
│   │   ├── stores/            # 7개 Pinia Store
│   │   │   ├── authStore.ts
│   │   │   ├── assessmentStore.ts
│   │   │   ├── challengeStore.ts
│   │   │   ├── learningStore.ts
│   │   │   ├── notificationStore.ts
│   │   │   ├── reportStore.ts
│   │   │   └── screeningStore.ts
│   │   └── views/             # 12개 View
│   └── admin/                 # 관리자 앱
│       ├── App.vue
│       ├── main.ts
│       ├── router/index.ts    # createWebHashHistory() 사용
│       ├── stores/
│       │   └── adminAuthStore.ts
│       └── views/             # 7개 View
├── user.html                  # User 앱 진입점
├── admin.html                 # Admin 앱 진입점
├── vite.user.config.ts        # User 빌드 설정 (port 5173)
├── vite.admin.config.ts       # Admin 빌드 설정 (port 5175)
├── tsconfig.json
├── tsconfig.app.json          # strict: true, noUnusedLocals: true
└── postcss.config.mjs         # @tailwindcss/postcss
```

### 3.2 Path Alias

```typescript
// tsconfig.app.json + vite.*.config.ts에 정의
import api from '@shared/api'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import type { LearningContent } from '@shared/types'
```

| Alias | 경로 |
|-------|------|
| `@shared` | `src/shared/` |
| `@user` | `src/user/` |
| `@admin` | `src/admin/` |

### 3.3 빌드/개발 명령어

```bash
# 개발 서버
npm run dev:user    # port 5173 (API 프록시 → localhost:8000)
npm run dev:admin   # port 5175 (API 프록시 → localhost:8000)

# 빌드 (→ slowok_back/public/{app}/)
npm run build:user
npm run build:admin
npm run build:all   # 전체 빌드

# 타입 체크
npm run type-check  # vue-tsc -b
```

빌드 결과물은 Laravel `public/` 디렉토리에 저장되어 별도 배포 없이 서빙됨.

### 3.4 Pinia Store 패턴

```typescript
// Composition API 방식 (모든 Store 동일)
export const useExampleStore = defineStore('example', () => {
  // ===== State =====
  const items = ref<Item[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  // ===== Actions =====
  async function fetchItems() {
    loading.value = true
    error.value = null
    try {
      const res = await someApi.getItems()
      if (res.data.success) {
        items.value = res.data.data
      }
    } catch (e: any) {
      error.value = e.response?.data?.message || '데이터를 불러오는데 실패했습니다.'
    } finally {
      loading.value = false
    }
  }

  return { items, loading, error, fetchItems }
})
```

**핵심 규칙**:
- Options API 아닌 **Composition API** (`setup()` 함수 형태) 사용
- State는 `ref()` 사용 (`reactive()` 미사용)
- `loading`, `error` ref 필수
- 응답 확인: `res.data.success` 체크 후 `res.data.data` 접근
- 에러 메시지: `e.response?.data?.message || '한국어 폴백 메시지'`

### 3.5 TypeScript 타입 정의 패턴

모든 타입은 `src/shared/types/index.ts`에 정의:

```typescript
// API 응답 래퍼 (필수)
export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

// 모델 타입 (백엔드 필드명과 1:1 대응)
export interface LearningContent {
  content_id: number
  category_id: number
  title: string
  content_type: 'VIDEO' | 'QUIZ' | 'GAME' | 'READING'
  content_data: Record<string, unknown> | null
  difficulty_level: number
  created_at: string
  updated_at: string
  // Eager loaded relations
  category?: LearningCategory
  progress?: LearningProgress
}
```

**타입 필드명 규칙**:
- 백엔드 모델 필드명과 **정확히 일치**해야 함
- Enum은 string literal union: `'VIDEO' | 'QUIZ' | 'GAME' | 'READING'`
- Eager loaded 관계는 optional (`?`) 처리
- PK는 number 타입 (`content_id: number`)

### 3.6 API 서비스 패턴

```typescript
// 객체 export 패턴 (모든 API 파일 동일)
import api from './index'
import type { ApiResponse, LearningContent } from '@shared/types'

export const learningApi = {
  // GET (목록)
  getContents() {
    return api.get<ApiResponse<LearningContent[]>>('/user/learning-contents')
  },

  // POST (생성/액션)
  updateProgress(data: { content_id: number; progress_percentage: number }) {
    return api.post<ApiResponse<LearningProgress>>('/user/learning-progress', data)
  },
}
```

**규칙**:
- 객체 리터럴로 export (`export const xApi = { ... }`)
- 제네릭 타입으로 `ApiResponse<T>` 래핑
- 목록: `ApiResponse<T[]>`, 단건: `ApiResponse<T>`
- URL은 상대 경로 (`/user/...`, `/admin/...`), baseURL `/api`가 자동 추가됨

### 3.7 Axios 인스턴스 설정

```typescript
// src/shared/api/index.ts
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 10000,
})
```

**토큰 관리**:
- localStorage 키: `userToken`, `adminToken` (앱별 분리)
- Request 인터셉터: URL에 `admin` 포함 시 `adminToken`, 아니면 `userToken`
- 401 응답 시 해당 앱의 토큰 + `isLoggedIn` 자동 제거
- FormData 전송 시 `Content-Type` 헤더 자동 제거

**로그인 상태 키**:
- `userIsLoggedIn` / `adminIsLoggedIn` (값: `'true'`)
- Router guard에서 `localStorage.getItem('userIsLoggedIn') === 'true'` 체크

### 3.8 Vue 컴포넌트 패턴

```vue
<template>
  <div class="min-h-screen bg-[#F0F7F0] flex justify-center">
    <div class="w-full max-w-[402px] min-h-screen relative bg-[#F0F7F0]">
      <AppHeader />

      <main class="pt-[80px] pb-[80px] px-5">
        <!-- 페이지 콘텐츠 -->
      </main>

      <BottomNav />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppHeader from '@shared/components/layout/AppHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
</script>
```

**컴포넌트 규칙**:
- `<script setup lang="ts">` (Composition API + TypeScript) 사용
- **`<style>` 블록 미사용** → Tailwind CSS 클래스만 사용
- 모바일 우선 레이아웃: `max-w-[402px]` 컨테이너
- 기본 배경: `bg-[#F0F7F0]` (연한 초록)
- 고정 헤더: 66px, 고정 하단 네비: 60px → 콘텐츠 `pt-[80px] pb-[80px]`

### 3.9 UI 컴포넌트 라이브러리

재사용 컴포넌트는 `src/shared/components/` 하위에 역할별 분류:

| 디렉토리 | 용도 | 주요 컴포넌트 |
|---------|------|-------------|
| `layout/` | 레이아웃 | `AppHeader`, `BackHeader`, `BottomNav` |
| `ui/` | 범용 UI | `ActionButton`, `CardSection`, `StatusBadge`, `ProgressBar`, `SectionTitle`, `InfoRow` |
| `form/` | 폼 입력 | `FormInput` |

**컴포넌트 Props 패턴**:
```typescript
// variant 기반 스타일 매핑
interface Props {
  variant?: 'primary' | 'outline' | 'danger'
  full?: boolean
  disabled?: boolean
}
const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
})
const variantClasses = computed(() => {
  const map: Record<string, string> = {
    primary: 'bg-[#4CAF50] text-white',
    outline: 'border border-[#4CAF50] text-[#4CAF50]',
    danger: 'bg-[#F44336] text-white',
  }
  return map[props.variant] || map.primary
})
```

---

## 4. 스타일링 규칙 (Tailwind CSS v4)

### 4.1 Tailwind v4 설정

Tailwind CSS v4는 CSS-first 설정 사용 (tailwind.config.js 없음):

```css
/* src/shared/styles/main.css */
@import "tailwindcss";

@theme {
  --color-primary: #4CAF50;
  --color-primary-light: #E8F5E9;
  --color-primary-hover: #388E3C;
  --color-secondary: #FF9800;
  --color-secondary-light: #FFF3E0;
  --color-accent: #2196F3;
  --color-accent-light: #E3F2FD;
  --font-family-pretendard: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
}
```

**PostCSS 설정** (`postcss.config.mjs`):
```javascript
export default { plugins: { '@tailwindcss/postcss': {} } }
```

### 4.2 색상 팔레트

| 용도 | 색상 코드 | Tailwind 클래스 |
|------|----------|----------------|
| Primary (주 강조) | `#4CAF50` | `bg-[#4CAF50]`, `text-[#4CAF50]` |
| Primary Light | `#E8F5E9` | `bg-[#E8F5E9]` |
| Primary Hover | `#388E3C` | `hover:bg-[#388E3C]` |
| Secondary (보조) | `#FF9800` | `bg-[#FF9800]` |
| Accent (강조) | `#2196F3` | `bg-[#2196F3]` |
| 텍스트 기본 | `#333` | `text-[#333]` |
| 텍스트 보조 | `#555`, `#888` | `text-[#555]`, `text-[#888]` |
| 보더 | `#E8E8E8`, `#E0E0E0` | `border-[#E8E8E8]` |
| 배경 | `#F0F7F0` | `bg-[#F0F7F0]` |

### 4.3 글꼴 크기 가이드

- 페이지 제목: `text-[15px] font-bold`
- 카드 제목: `text-[14px] font-semibold`
- 본문 텍스트: `text-[13px]` ~ `text-[14px]`
- 보조 정보: `text-[12px]`
- 메타 정보: `text-[11px]`

### 4.4 라운딩 & 그림자

- 카드: `rounded-[16px]` + `shadow-[0_0_10px_rgba(0,0,0,0.1)]`
- 입력 필드: `rounded-[12px]` 또는 `rounded-[10px]`
- 뱃지/칩: `rounded-full`
- 버튼: `rounded-[12px]`

---

## 5. DB 스키마 규칙

### 5.1 테이블 네이밍

- **단수형** 사용: `account`, `challenge`, `learning_content` (NOT `accounts`)
- **snake_case**: `learning_content`, `screening_test`
- **PK 컬럼명**: `{entity}_id` (예: `content_id`, `test_id`)
- **FK 컬럼명**: `{참조테이블}_id` (예: `category_id`, `account_id`)
- **상태 컬럼**: `status` (string, Enum 값: `'NOT_STARTED'`, `'IN_PROGRESS'`, `'COMPLETED'`)

### 5.2 공통 컬럼 패턴

```sql
-- 모든 테이블에 존재 (Laravel timestamps)
created_at TIMESTAMP NULL,
updated_at TIMESTAMP NULL,
```

### 5.3 마이그레이션 네이밍

`{YYYY}_{MM}_{DD}_{sequential_number}_create_{table_name}_table.php`

예: `2026_03_01_000001_create_account_table.php`

---

## 6. 알려진 주의사항 (Pitfalls)

### 6.1 Account 모델 특이사항

- `password_hash` 사용 (Laravel 기본 `password` 아님)
- `getAuthPassword()` 오버라이드 필수
- extends `Authenticatable`, uses `HasApiTokens`
- `$hidden = ['password_hash']`

### 6.2 SQLite 개발 환경

- 개발 DB: `database/database.sqlite`
- MySQL과 다른 점: JSON 컬럼은 text로 저장, boolean은 0/1 정수
- `$casts`에서 `'array'` 캐스트로 JSON 컬럼 처리

### 6.3 날짜 포맷

- 백엔드 응답: ISO 8601 (`2026-03-03T12:30:00.000000Z`)
- 프론트엔드 표시: `YYYY.MM.DD` (ko-KR locale)
- 포맷 유틸: `src/shared/utils/index.ts`의 `formatDate()`, `formatDateTime()`

### 6.4 라우터 히스토리 모드

- User 앱: `createWebHistory()` → HTML5 History API (서버 사이드 fallback 필요)
- Admin 앱: `createWebHashHistory()` → Hash 기반 (서버 설정 불필요)

### 6.5 CORS 설정

`config/cors.php`에 허용된 origin:
- `http://localhost:5173` (User dev)
- `http://localhost:5175` (Admin dev)
- `https://slowokuser.revuplan.com`
- `https://slowokadmin.revuplan.com`

---

## 7. 개발 워크플로우

### 7.1 새 기능 추가 순서

1. **DB 확인**: 테이블/컬럼 존재 여부 확인 (없으면 SQL 쿼리 제공)
2. **Model 생성/수정**: `$table`, `$primaryKey`, `$fillable`, `$casts`, 관계 정의
3. **Controller 생성**: 인라인 유효성 검사, 표준 응답
4. **Route 등록**: `routes/api.php`에 적절한 그룹/미들웨어 내 등록
5. **TypeScript 타입 정의**: `src/shared/types/index.ts`에 추가, 백엔드 필드명과 1:1 매칭
6. **API 서비스 함수**: `src/shared/api/`에 추가
7. **Pinia Store**: Composition API 패턴
8. **View/Component**: 모바일 레이아웃, Tailwind 스타일링
9. **타입 체크**: `npm run type-check` 통과 확인

### 7.2 기존 기능 수정 시

1. 백엔드 Model `$fillable` 확인 (새 필드 누락 주의)
2. 프론트엔드 타입 정의 동기화
3. API 서비스 함수 파라미터/응답 타입 업데이트
4. Store와 View에서 필드명 일관성 확인

### 7.3 빌드 검증

```bash
# 타입 검증 (필수)
cd slowok_front && npm run type-check

# 빌드 테스트
npm run build:user
npm run build:admin

# 백엔드 라우트 확인
cd slowok_back && php artisan route:list
```

---

## 8. 주요 파일 Quick Reference

### 백엔드

| 파일 | 용도 |
|------|------|
| `routes/api.php` | 모든 API 라우트 정의 |
| `app/Http/Middleware/CheckRole.php` | 역할 기반 접근 제어 |
| `app/Http/Controllers/Api/AuthController.php` | 인증 (로그인/회원가입) |
| `app/Http/Controllers/Api/User/*.php` | 사용자 API (9개) |
| `app/Http/Controllers/Api/Admin/*.php` | 관리자 API (11개) |
| `app/Models/*.php` | Eloquent 모델 (17개) |
| `config/cors.php` | CORS 허용 origin 설정 |
| `bootstrap/app.php` | 미들웨어 등록, 404 핸들링 |

### 프론트엔드

| 파일 | 용도 |
|------|------|
| `src/shared/api/index.ts` | Axios 인스턴스, 인터셉터 |
| `src/shared/types/index.ts` | 모든 공유 타입 정의 |
| `src/shared/api/*.ts` | API 서비스 모듈 (7개) |
| `src/shared/styles/main.css` | Tailwind v4 @theme 설정 |
| `src/shared/utils/index.ts` | formatDate, formatDateTime |
| `src/user/stores/*.ts` | User Pinia Store (7개) |
| `src/admin/stores/*.ts` | Admin Pinia Store (1개) |
| `src/user/router/index.ts` | User 라우트 (WebHistory) |
| `src/admin/router/index.ts` | Admin 라우트 (HashHistory) |
| `vite.user.config.ts` | User 빌드/개발 설정 |
| `vite.admin.config.ts` | Admin 빌드/개발 설정 |

---

## 9. 금지사항 체크리스트

- [ ] FormRequest 클래스 사용 금지 (인라인 validate 사용)
- [ ] Options API 사용 금지 (Composition API만 사용)
- [ ] `reactive()` 대신 `ref()` 사용 (Store state)
- [ ] `<style>` 블록 사용 금지 (Tailwind 클래스만 사용)
- [ ] DB 직접 조작 금지 (SQL 쿼리만 제공, 마이그레이션 파일 직접 생성/수정 금지)
- [ ] `php artisan migrate` 직접 실행 금지
- [ ] 미사용 import 금지 (코드 수정 후 반드시 확인)
- [ ] Model에 새 컬럼 추가 시 `$fillable` 누락 금지
- [ ] 프론트엔드 타입 필드명과 백엔드 필드명 불일치 금지
- [ ] 하드코딩된 API URL 금지 (baseURL `/api` 자동 적용)
