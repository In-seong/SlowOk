# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 필수 참고 문서
- **DEV_REFERENCE.md**: 프로젝트 전체 컨벤션 (백엔드/프론트엔드/DB/스타일링 규칙) — 새 기능 구현, 코드 수정 전에 반드시 참고
- **DB_SCHEMA.md**: 전체 데이터베이스 스키마 문서 — DB 관련 작업 전에 반드시 참고, 스키마 변경 시 "변경 이력" 섹션도 반드시 업데이트

## 언어 규칙
- 모든 답변은 반드시 한국어(한글)로 작성한다

## 빌드/개발 명령어

### 프론트엔드 (slowok_front/)
```bash
npm run dev:user          # User 앱 개발 서버 (port 5173, API 프록시 → localhost:8000)
npm run dev:admin         # Admin 앱 개발 서버 (port 5175, API 프록시 → localhost:8000)
npm run build:user        # User 앱 빌드 (vue-tsc + vite build → slowok_back/public/user/)
npm run build:admin       # Admin 앱 빌드 (vue-tsc + vite build → slowok_back/public/admin/)
npm run build:all         # 전체 빌드 (type-check → user build → admin build)
npm run type-check        # vue-tsc -b (TypeScript 타입 검증)
```

### 백엔드 (slowok_back/)
```bash
php artisan serve         # 개발 서버 (port 8000)
php artisan route:list    # 등록된 API 라우트 확인
php artisan test          # PHPUnit 테스트 실행
php artisan pint          # 코드 스타일 포맷팅 (Laravel Pint)
```

## 프로젝트 아키텍처

### 전체 구조: Dual SPA + API 모노레포
- **slowok_front/**: Vue 3.5 + TypeScript 5.9 + Tailwind CSS v4 프론트엔드 (User/Admin 2개 SPA)
- **slowok_back/**: Laravel 12 REST API 백엔드 (PHP 8.2+, Sanctum 토큰 인증, SQLite 개발DB)
- **SlowOK_App/**: 모바일 앱 (Android/iOS WebView 래퍼)
- **deploy/**: 배포 스크립트 및 nginx 설정

### 프론트엔드 Dual SPA 구조
두 개의 독립된 SPA가 별도 Vite 설정(vite.user.config.ts, vite.admin.config.ts)과 진입점(user.html, admin.html)으로 빌드됨.

- **src/shared/**: 공통 모듈 — API 클라이언트, 타입 정의(types/index.ts), UI 컴포넌트, 유틸리티
- **src/user/**: 사용자(학습자/학부모) 앱 — `createWebHistory()`, Pinia stores 7개
- **src/admin/**: 관리자 앱 — `createWebHashHistory()`, Pinia stores 1개

Path alias: `@shared`, `@user`, `@admin` (tsconfig.app.json + vite configs에 정의)

### 백엔드 역할 분리
- `/api/auth/*`: 공통 인증 (AuthController)
- `/api/user/*`: 사용자 전용 API — `role:USER` 미들웨어
- `/api/admin/*`: 관리자 전용 API — `role:ADMIN` 미들웨어

### 멀티테넌시 구조
- `InjectInstitutionScope` 미들웨어 + `BelongsToInstitution` Trait으로 기관별 데이터 격리
- MASTER 역할: `X-Institution-Id` 헤더로 기관 전환
- ADMIN 역할: 본인 소속 institution_id 강제

### 인증/계정 구조
- `account`(인증) + `user_profile`(프로필) 분리: 1 account : N profile
- Account 모델: `password_hash` 컬럼 사용 (Laravel 기본 `password` 아님), `getAuthPassword()` 오버라이드
- 학부모(PARENT) 프로필 → `children()` 자기참조로 자녀(LEARNER) 연결
- Sanctum Token 인증, localStorage 키: `userToken`/`adminToken` (앱별 분리)

### 관리자 권한 시스템
- `admin_permission` + `admin_permission_grant` 테이블
- `CheckPermission` 미들웨어로 세분화된 권한 제어
- `CheckFeature` 미들웨어로 기능 플래그 제어

## TypeScript 코드 작성 규칙
- **strict null check 준수**: 배열 인덱스 접근(`arr[i]`)은 반드시 `undefined` 가능성을 처리 (가드 또는 optional chaining)
- **미사용 import 금지**: 코드 작성/수정 후 import 목록에서 실제로 사용하지 않는 타입이나 함수가 남아있지 않은지 반드시 확인 (`noUnusedLocals: true` 설정)
- **작업 완료 후**: `npm run type-check` (vue-tsc) 에러가 발생하지 않도록 위 사항을 코드 작성 시점에 적용

## DB 작업 규칙
- 마이그레이션 파일을 직접 생성하거나 수정하지 않는다
- DB 스키마 변경이 필요한 경우 실행할 SQL 쿼리만 제공한다
- `php artisan migrate` 등 DB 관련 명령어를 직접 실행하지 않는다

## 사용 가이드 자동 업데이트 규칙
- **기능 구현/수정 작업이 완료되면** 반드시 아래 가이드 페이지도 함께 업데이트한다
- 사용자 앱 가이드: `slowok_front/src/user/views/HowToUseView.vue` (parentSteps / learnerSteps 배열)
- 관리자 앱 가이드: `slowok_front/src/admin/views/HowToUseView.vue` (masterSections / adminSections 배열 + 권한 비교표)
- **업데이트 대상 판단 기준**:
  - 새 화면/메뉴가 추가된 경우 → 해당 역할 가이드에 단계 추가
  - 기존 화면의 동작이 변경된 경우 → 해당 단계의 description/tips 수정
  - 새 권한/역할이 추가된 경우 → 권한 비교표 업데이트
  - 순수 백엔드 내부 로직만 변경된 경우 → 가이드 업데이트 불필요
