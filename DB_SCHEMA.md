# SlowOK 데이터베이스 스키마

> **DB**: MariaDB 10.6 (`slowok`)
> **최종 업데이트**: 2026-03-04 (SQL dump 기준)
> **테이블 수**: 33개 (비즈니스 29 + 시스템 4)

---

## ER 다이어그램 (텍스트)

```
                            ┌──────────────┐
                            │  institution │
                            └──────┬───────┘
                                   │ 1:N
        ┌──────────────────────────┼──────────────────────────┐
        │                          │                          │
   ┌────▼─────┐            ┌───────▼────────┐         ┌──────▼──────────┐
   │ account  │            │learning_category│         │ screening_test  │
   └────┬─────┘            └───────┬────────┘         └────────┬────────┘
        │ 1:N                      │ 1:N                       │ 1:N
   ┌────▼──────────┐       ┌──────▼──────────┐       ┌────────▼──────────┐
   │ user_profile  │       │learning_content │       │screening_question │
   └────┬──────────┘       └──────┬──────────┘       └───────────────────┘
        │                         │
        │  ┌──────────────────────┤
        │  │                      │
   ┌────▼──▼───────────┐  ┌──────▼──────────┐
   │ learning_progress │  │    challenge     │
   └───────────────────┘  └──────┬──────────┘
                                 │ 1:N
                          ┌──────▼──────────────┐
                          │ challenge_question   │
                          └─────────────────────┘
```

---

## 1. 인증/계정 영역

### account (계정)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **account_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| username | VARCHAR(50) | UNIQUE, NOT NULL | 로그인 ID |
| password_hash | VARCHAR(255) | NOT NULL | 비밀번호 해시 (Laravel 기본 `password` 아님) |
| role | ENUM('USER','ADMIN','MASTER','TEST') | NOT NULL, DEFAULT 'USER' | 역할 |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL 허용 | 소속 기관 (MASTER는 NULL) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 상태 |
| last_login_at | TIMESTAMP | NULL | 최종 로그인 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**인덱스**: `account_username_unique` (username), `fk_account_institution` (institution_id)
**FK**: institution_id → institution.institution_id (ON DELETE SET NULL)

### user_profile (사용자 프로필)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **profile_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| account_id | BIGINT UNSIGNED | FK → account, NOT NULL | 소속 계정 |
| name | VARCHAR(100) | NOT NULL | 이름 |
| phone | VARCHAR(20) | NULL | 연락처 |
| email | VARCHAR(100) | NULL | 이메일 |
| birth_date | DATE | NULL | 생년월일 |
| user_type | ENUM('LEARNER','PARENT','ADMIN') | NOT NULL, DEFAULT 'LEARNER' | **학습자/학부모/관리자 구분** |
| parent_profile_id | BIGINT UNSIGNED | FK → user_profile (자기참조), NULL | 학부모 프로필 ID |
| encrypted_name | TEXT | NULL | 암호화된 이름 |
| encrypted_phone | TEXT | NULL | 암호화된 연락처 |
| encrypted_email | TEXT | NULL | 암호화된 이메일 |
| encrypted_birth_date | TEXT | NULL | 암호화된 생년월일 |
| is_encrypted | TINYINT(1) | NOT NULL, DEFAULT 0 | 암호화 여부 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: account_id → account.account_id (CASCADE), parent_profile_id → user_profile.profile_id (SET NULL)

**관계 구조 (학부모-학습자)**:
```
Account (role: USER) ──1:N──> UserProfile
  ├─ Profile (user_type: PARENT)           ← 학부모 본인
  ├─ Profile (user_type: LEARNER, parent_profile_id: 위 PARENT의 profile_id) ← 자녀1
  └─ Profile (user_type: LEARNER, parent_profile_id: 위 PARENT의 profile_id) ← 자녀2
```

### subscription (구독)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **subscription_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| account_id | BIGINT UNSIGNED | FK → account, NOT NULL | |
| plan_type | VARCHAR(50) | NOT NULL, DEFAULT 'FREE' | 플랜 유형 |
| status | ENUM('ACTIVE','EXPIRED','CANCELLED') | NOT NULL, DEFAULT 'ACTIVE' | |
| started_at | TIMESTAMP | NOT NULL, DEFAULT NOW | |
| expires_at | TIMESTAMP | NULL | |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: account_id → account.account_id (CASCADE)
**UNIQUE**: account_id (1계정 1구독)

### notification (알림)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **notification_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| account_id | BIGINT UNSIGNED | FK → account, NOT NULL | |
| title | VARCHAR(200) | NOT NULL | |
| message | TEXT | NOT NULL | |
| type | VARCHAR(50) | NULL | screening_complete, content_assigned, challenge_passed 등 |
| is_read | TINYINT(1) | NOT NULL, DEFAULT 0 | |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: account_id → account.account_id (CASCADE)
**INDEX**: idx_notification_account (account_id)

### device_token (FCM 디바이스 토큰)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **token_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| account_id | BIGINT UNSIGNED | FK → account, NOT NULL | |
| fcm_token | VARCHAR(500) | NOT NULL | Firebase Cloud Messaging 토큰 |
| device_type | VARCHAR(20) | NOT NULL, DEFAULT 'android' | android, ios, web |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: account_id → account.account_id (CASCADE)
**INDEX**: idx_device_token_account (account_id)
**UNIQUE**: uq_device_token (account_id, fcm_token)

---

## 2. 기관/권한 영역

### institution (기관)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **institution_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(200) | NOT NULL | 기관명 |
| type | VARCHAR(50) | NULL | 기관 유형 (아동센터, 심리센터 등) |
| contact_info | JSON | NULL | 연락처 정보 |
| address | VARCHAR(300) | NULL | 주소 |
| invite_code | VARCHAR(20) | NULL, UNIQUE | 기관 초대코드 (학부모 가입 시 사용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**멀티테넌시 허브**: account, learning_category, learning_content, screening_test, challenge, reward_card, content_package가 institution_id FK 보유

### admin_permission (관리자 권한 정의)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **permission_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| permission_key | VARCHAR(100) | UNIQUE, NOT NULL | 권한 키 (예: content_manage) |
| permission_name | VARCHAR(100) | NOT NULL | 표시명 |
| description | VARCHAR(500) | NULL | 설명 |
| category | VARCHAR(50) | NOT NULL, DEFAULT 'general' | 카테고리 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### admin_permission_grant (권한 부여)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **grant_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| account_id | BIGINT UNSIGNED | FK → account, NOT NULL | 부여 대상 |
| permission_id | BIGINT UNSIGNED | FK → admin_permission, NOT NULL | 부여 권한 |
| granted_by | BIGINT UNSIGNED | FK → account, NOT NULL | 부여자 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: (account_id, permission_id)
**FK**: 3개 모두 CASCADE

---

## 3. 학습 콘텐츠 영역

### learning_category (학습 카테고리)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **category_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(100) | NOT NULL | |
| description | TEXT | NULL | |
| icon | VARCHAR(100) | NULL | |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 (soft delete) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: institution_id → institution.institution_id (SET NULL)

### learning_content (학습 콘텐츠)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **content_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| category_id | BIGINT UNSIGNED | FK → learning_category, NOT NULL | |
| title | VARCHAR(200) | NOT NULL | |
| content_type | ENUM('VIDEO','QUIZ','GAME','READING') | NOT NULL, DEFAULT 'READING' | |
| content_data | JSON | NULL | 콘텐츠 상세 데이터 |
| difficulty_level | INT | NOT NULL, DEFAULT 1 | |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 (soft delete) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: category_id (CASCADE), institution_id (SET NULL)

### learning_progress (학습 진행)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **progress_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| content_id | BIGINT UNSIGNED | FK → learning_content, NOT NULL | |
| status | ENUM('NOT_STARTED','IN_PROGRESS','COMPLETED') | NOT NULL, DEFAULT 'NOT_STARTED' | |
| score | INT | NULL | |
| attempts | INT | NOT NULL, DEFAULT 0 | 시도 횟수 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: (profile_id, content_id)
**INDEX**: idx_learning_progress_profile (profile_id), idx_learning_progress_content (content_id)

### curriculum (커리큘럼)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **curriculum_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| category_id | BIGINT UNSIGNED | FK → learning_category, NOT NULL | |
| current_level | VARCHAR(50) | NULL | |
| recommended_path | JSON | NULL | 추천 학습 경로 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: (profile_id, category_id)

---

## 4. 진단(스크리닝) 영역

### screening_test (진단 검사)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **test_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| title | VARCHAR(200) | NOT NULL | |
| description | TEXT | NULL | |
| test_type | VARCHAR(30) | NOT NULL, DEFAULT 'MULTIPLE_CHOICE' | MULTIPLE_CHOICE / LIKERT |
| sub_domains | JSON | NULL | 하위영역 목록 (리커트용, 관리자 설정) |
| category_id | BIGINT UNSIGNED | FK → learning_category, NOT NULL | |
| question_count | INT | NOT NULL, DEFAULT 0 | |
| time_limit | INT | NULL | 제한시간 (분) |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 (soft delete) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### screening_question (진단 문항)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **question_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| test_id | BIGINT UNSIGNED | FK → screening_test, NOT NULL | |
| content | TEXT | NOT NULL | 문항 내용 |
| question_type | VARCHAR(50) | DEFAULT 'multiple_choice' | |
| sub_domain | VARCHAR(50) | NULL | 하위영역 (상호작용/자기조절/자기표현/사회적인식) |
| options | JSON | NULL | 선택지 |
| correct_answer | VARCHAR(255) | NULL | 정답 |
| order | INT | DEFAULT 0 | 정렬 순서 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### screening_result (진단 결과)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **result_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| test_id | BIGINT UNSIGNED | FK → screening_test, NOT NULL | |
| score | INT | NOT NULL, DEFAULT 0 | |
| level | VARCHAR(50) | NULL | 진단 수준 |
| analysis | JSON | NULL | 분석 결과 |
| sub_scores | JSON | NULL | 하위영역별 점수 (리커트) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**INDEX**: idx_screening_result_profile (profile_id), idx_screening_result_test (test_id)

---

## 5. 챌린지 영역

### challenge (챌린지)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **challenge_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| category_id | BIGINT UNSIGNED | FK → learning_category, NOT NULL | |
| title | VARCHAR(200) | NOT NULL | |
| challenge_type | VARCHAR(50) | NOT NULL, DEFAULT 'DAILY' | |
| difficulty_level | INT | NOT NULL, DEFAULT 1 | |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 (soft delete) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### challenge_question (챌린지 문항)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **question_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| challenge_id | BIGINT UNSIGNED | FK → challenge, NOT NULL | |
| content | TEXT | NOT NULL | |
| question_type | VARCHAR(50) | NULL | |
| options | JSON | NULL | |
| correct_answer | VARCHAR(255) | NULL | |
| image_url | VARCHAR(500) | NULL | 이미지 |
| order | INT | DEFAULT 0 | |
| match_pairs | JSON | NULL | 매칭게임 쌍 [{left,right}] |
| accept_answers | JSON | NULL | 허용 정답 목록 ["답1","답2"] |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### challenge_attempt (챌린지 시도)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **attempt_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| challenge_id | BIGINT UNSIGNED | FK → challenge, NOT NULL | |
| score | INT | NOT NULL, DEFAULT 0 | |
| is_passed | TINYINT(1) | NOT NULL, DEFAULT 0 | 통과 여부 |
| answers | JSON | NULL | 문항별 답변 상세 |
| time_spent | INT UNSIGNED | NULL | 소요시간(초) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**INDEX**: idx_challenge_attempt_profile (profile_id), idx_challenge_attempt_challenge (challenge_id)

---

## 6. 콘텐츠 할당/패키지 영역

### content_assignment (콘텐츠 할당)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **assignment_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | 할당 대상 |
| assignable_type | ENUM('screening_test','learning_content','challenge') | NOT NULL | 다형성 타입 |
| assignable_id | BIGINT UNSIGNED | NOT NULL | 다형성 ID |
| assigned_by | BIGINT UNSIGNED | FK → account, NOT NULL | 할당자 (관리자) |
| assigned_at | TIMESTAMP | NOT NULL, DEFAULT NOW | |
| due_date | DATE | NULL | 마감일 |
| note | VARCHAR(500) | NULL | 메모 |
| status | ENUM('ASSIGNED','IN_PROGRESS','COMPLETED') | NOT NULL, DEFAULT 'ASSIGNED' | |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: (profile_id, assignable_type, assignable_id)
**인덱스**: idx_assignment_type_id (assignable_type, assignable_id)

### content_package (콘텐츠 패키지)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **package_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(200) | NOT NULL | |
| description | VARCHAR(500) | NULL | |
| created_by | BIGINT UNSIGNED | FK → account, NOT NULL | 생성자 |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### content_package_item (패키지 구성 항목)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **item_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| package_id | BIGINT UNSIGNED | FK → content_package, NOT NULL | |
| assignable_type | ENUM('screening_test','learning_content','challenge') | NOT NULL | |
| assignable_id | BIGINT UNSIGNED | NOT NULL | |
| sort_order | INT UNSIGNED | NOT NULL, DEFAULT 0 | |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: (package_id, assignable_type, assignable_id)

### recommendation_rule (추천 규칙)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **rule_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| category_id | BIGINT UNSIGNED | FK → learning_category, NOT NULL | 진단 카테고리 |
| score_min | INT | NOT NULL, DEFAULT 0 | 최소 점수 (0~100) |
| score_max | INT | NOT NULL, DEFAULT 100 | 최대 점수 (0~100) |
| package_id | BIGINT UNSIGNED | FK → content_package, NOT NULL | 할당할 패키지 |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 (soft delete) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**INDEX**: (category_id, score_min, score_max, institution_id)

---

## 7. 보상/평가/리포트 영역

### reward_card (보상 카드)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **card_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(100) | NOT NULL | |
| description | TEXT | NULL | |
| image_url | VARCHAR(255) | NULL | |
| rarity | VARCHAR(50) | NOT NULL, DEFAULT 'COMMON' | 희귀도 |
| institution_id | BIGINT UNSIGNED | FK → institution, NULL | 기관별 (NULL=공용) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 (soft delete) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### user_reward_card (사용자 보상 카드 - 피벗)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **profile_id** | BIGINT UNSIGNED | PK (복합), FK → user_profile | |
| **card_id** | BIGINT UNSIGNED | PK (복합), FK → reward_card | |
| earned_at | TIMESTAMP | NOT NULL, DEFAULT NOW | 획득일 |

### assessment (평가)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **assessment_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| category_id | BIGINT UNSIGNED | FK → learning_category, NOT NULL | |
| type | VARCHAR(50) | NOT NULL, DEFAULT 'PERIODIC' | 평가 유형 |
| score | INT | NOT NULL, DEFAULT 0 | |
| feedback | JSON | NULL | 피드백 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**INDEX**: idx_assessment_profile (profile_id), idx_assessment_category (category_id)

### learning_report (학습 리포트)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **report_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| period | VARCHAR(50) | NOT NULL | 리포트 기간 |
| summary | JSON | NULL | 요약 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

---

## 8. 플랜/기능 게이트 영역

### feature (기능 정의)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **feature_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| feature_key | VARCHAR(100) | UNIQUE, NOT NULL | 기능 식별 키 (예: voice_recording, ai_analysis) |
| name | VARCHAR(100) | NOT NULL | 기능 표시명 |
| description | VARCHAR(500) | NULL | 설명 |
| category | VARCHAR(50) | NOT NULL, DEFAULT 'general' | 기능 카테고리 |
| sort_order | INT | NOT NULL, DEFAULT 0 | 정렬 순서 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### plan (플랜)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **plan_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| name | VARCHAR(100) | NOT NULL | 플랜명 (Basic, Pro, Max 등) |
| description | VARCHAR(500) | NULL | 설명 |
| price | DECIMAL(10,2) | NULL | 월 가격 |
| sort_order | INT | NOT NULL, DEFAULT 0 | 정렬 순서 |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 활성 여부 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

### plan_feature (플랜-기능 매핑 피벗)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **plan_id** | BIGINT UNSIGNED | PK (복합), FK → plan | |
| **feature_id** | BIGINT UNSIGNED | PK (복합), FK → feature | |

**FK**: plan_id → plan (CASCADE), feature_id → feature (CASCADE)

### institution_plan (기관 플랜 배정)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **institution_plan_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| institution_id | BIGINT UNSIGNED | FK → institution, NOT NULL | 배정 대상 기관 |
| plan_id | BIGINT UNSIGNED | FK → plan, NOT NULL | 배정 플랜 |
| started_at | TIMESTAMP | NOT NULL, DEFAULT NOW | 시작일 |
| expires_at | TIMESTAMP | NULL | 만료일 (NULL=무기한) |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: institution_id (1기관 1플랜)
**FK**: institution_id → institution (CASCADE), plan_id → plan (CASCADE)

### user_feature_override (사용자 기능 오버라이드)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **override_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | 대상 프로필 |
| feature_key | VARCHAR(100) | NOT NULL | 기능 키 |
| enabled | TINYINT(1) | NOT NULL | true=강제활성, false=강제비활성 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**UNIQUE**: (profile_id, feature_key)
**FK**: profile_id → user_profile (CASCADE)

**기능 게이트 체크 흐름**:
```
요청 → feature:key 미들웨어
  ├─ MASTER → 항상 허용 (바이패스)
  ├─ 기관 플랜에 feature_key 포함? → NO → 403 차단
  ├─ 사용자 오버라이드 존재?
  │   ├─ enabled=true → 허용
  │   └─ enabled=false → 차단
  └─ 오버라이드 없음 → 기관 플랜 기본값 (허용)
```

---

## 9. 음성 녹음 영역

### voice_recording (음성 녹음)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **recording_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile, NOT NULL | |
| assignable_type | ENUM('learning_content','challenge') | NOT NULL | |
| assignable_id | BIGINT UNSIGNED | NOT NULL | |
| file_path | VARCHAR(500) | NOT NULL | 서버 경로 |
| file_url | VARCHAR(500) | NOT NULL | 접근 URL |
| original_name | VARCHAR(255) | NULL | 원본 파일명 |
| file_size | INT UNSIGNED | NULL | 파일 크기 (bytes) |
| duration | INT UNSIGNED | NULL | 녹음 시간 (초) |
| memo | VARCHAR(500) | NULL | |
| stt_text | TEXT | NULL | STT 변환 텍스트 |
| stt_confidence | DECIMAL(3,2) | NULL | STT 신뢰도 0~1 |
| question_id | BIGINT UNSIGNED | NULL | 연결 문항 ID |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**인덱스**: idx_recording_profile, idx_recording_type_id

### recording_feedback (녹음 피드백)
| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| **feedback_id** | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| recording_id | BIGINT UNSIGNED | FK → voice_recording, NOT NULL | 대상 녹음 |
| account_id | BIGINT UNSIGNED | FK → account, NOT NULL | 피드백 작성자 |
| comment | TEXT | NOT NULL | 피드백 내용 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: recording_id → voice_recording (CASCADE), account_id → account (CASCADE)
**INDEX**: idx_recording_feedback_recording (recording_id)

---

## 10. 학습자 메모 영역

### learner_memo
학습자별 내부 직원 메모 (학부모/학습자에게 비공개)

| 컬럼 | 타입 | 제약 | 설명 |
|------|------|------|------|
| memo_id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| profile_id | BIGINT UNSIGNED | FK → user_profile | 대상 학습자 |
| account_id | BIGINT UNSIGNED | FK → account | 작성자 |
| category | ENUM('observation','consultation','handover','general') | NOT NULL, DEFAULT 'general' | 카테고리 |
| content | TEXT | NOT NULL | 메모 내용 |
| is_pinned | TINYINT(1) | NOT NULL, DEFAULT 0 | 상단 고정 여부 |
| created_at | TIMESTAMP | NULL | |
| updated_at | TIMESTAMP | NULL | |

**FK**: profile_id → user_profile (CASCADE), account_id → account (CASCADE)
**INDEX**: idx_learner_memo_profile (profile_id), idx_learner_memo_account (account_id)

---

## 11. 시스템 테이블

| 테이블 | 용도 |
|--------|------|
| cache | Laravel 캐시 |
| cache_locks | Laravel 캐시 락 |
| migrations | Laravel 마이그레이션 이력 |
| personal_access_tokens | Sanctum 토큰 |

---

## 12. 멀티테넌시 (기관별 데이터 격리)

institution_id를 FK로 갖는 테이블들 (NULL = 공용/전체):

| 테이블 | ON DELETE |
|--------|-----------|
| account | SET NULL |
| learning_category | SET NULL |
| learning_content | SET NULL |
| screening_test | SET NULL |
| challenge | SET NULL |
| reward_card | SET NULL |
| content_package | SET NULL |

**필터링 로직**: `BelongsToInstitution` Trait → `forInstitution($id)` 스코프
- `$id = null` → 전체 조회 (MASTER 기본)
- `$id = N` → 해당 기관 + 공용(NULL) 데이터

---

## 13. 다형성(Polymorphic) 패턴

assignable_type + assignable_id 조합으로 여러 타입을 하나의 FK로 참조:

| 테이블 | assignable_type 값 | 참조 대상 |
|--------|-------------------|-----------|
| content_assignment | screening_test | screening_test.test_id |
| content_assignment | learning_content | learning_content.content_id |
| content_assignment | challenge | challenge.challenge_id |
| content_package_item | screening_test | screening_test.test_id |
| content_package_item | learning_content | learning_content.content_id |
| content_package_item | challenge | challenge.challenge_id |
| voice_recording | learning_content | learning_content.content_id |
| voice_recording | challenge | challenge.challenge_id |

---

## 14. FK 전체 맵

```
account
  └── institution_id → institution (SET NULL)

user_profile
  ├── account_id → account (CASCADE)
  └── parent_profile_id → user_profile (SET NULL) [자기참조]

admin_permission_grant
  ├── account_id → account (CASCADE)
  ├── permission_id → admin_permission (CASCADE)
  └── granted_by → account (CASCADE)

subscription → account_id → account (CASCADE)
notification → account_id → account (CASCADE)
device_token → account_id → account (CASCADE)

learning_category → institution_id → institution (SET NULL)

learning_content
  ├── category_id → learning_category (CASCADE)
  └── institution_id → institution (SET NULL)

learning_progress
  ├── profile_id → user_profile (CASCADE)
  └── content_id → learning_content (CASCADE)

curriculum
  ├── profile_id → user_profile (CASCADE)
  └── category_id → learning_category (CASCADE)

screening_test
  ├── category_id → learning_category (CASCADE)
  └── institution_id → institution (SET NULL)

screening_question → test_id → screening_test (CASCADE)

screening_result
  ├── profile_id → user_profile (CASCADE)
  └── test_id → screening_test (CASCADE)

challenge
  ├── category_id → learning_category (CASCADE)
  └── institution_id → institution (SET NULL)

challenge_question → challenge_id → challenge (CASCADE)

challenge_attempt
  ├── profile_id → user_profile (CASCADE)
  └── challenge_id → challenge (CASCADE)

content_assignment
  ├── profile_id → user_profile (CASCADE)
  └── assigned_by → account (CASCADE)

content_package
  ├── created_by → account (CASCADE)
  └── institution_id → institution (SET NULL)

content_package_item → package_id → content_package (CASCADE)

recommendation_rule
  ├── category_id → learning_category (CASCADE)
  ├── package_id → content_package (CASCADE)
  └── institution_id → institution (SET NULL)

reward_card → institution_id → institution (SET NULL)

user_reward_card
  ├── profile_id → user_profile (CASCADE)
  └── card_id → reward_card (CASCADE)

assessment
  ├── profile_id → user_profile (CASCADE)
  └── category_id → learning_category (CASCADE)

learning_report → profile_id → user_profile (CASCADE)

voice_recording → profile_id → user_profile (CASCADE)

plan_feature
  ├── plan_id → plan (CASCADE)
  └── feature_id → feature (CASCADE)

institution_plan
  ├── institution_id → institution (CASCADE)
  └── plan_id → plan (CASCADE)

user_feature_override → profile_id → user_profile (CASCADE)

recording_feedback
  ├── recording_id → voice_recording (CASCADE)
  └── account_id → account (CASCADE)

learner_memo
  ├── profile_id → user_profile (CASCADE)
  └── account_id → account (CASCADE)
```

---

## 15. 변경 이력

| 날짜 | 변경 내용 |
|------|----------|
| 2026-03-04 | 초기 문서 생성 (SQL dump 기준 24개 테이블) |
| 2026-03-04 | 멀티테넌시 적용 (7개 테이블에 institution_id 추가) |
| 2026-03-04 | content_package, content_package_item 테이블 추가 |
| 2026-03-04 | voice_recording 테이블 추가 |
| 2026-03-04 | institution 테이블에 invite_code 컬럼 추가 (학부모-자녀 프로필 시스템) |
| 2026-03-04 | 5개 테이블에 is_active 컬럼 추가 (learning_category, learning_content, screening_test, challenge, reward_card) - soft delete 전환 |
| 2026-03-04 | user_profile.user_type ENUM에 'ADMIN' 추가 (LEARNER/PARENT/ADMIN) |
| 2026-03-05 | FK 인덱스 9건 추가 (screening_result, challenge_attempt, assessment, notification 등) |
| 2026-03-05 | subscription.account_id UNIQUE 제약 추가 |
| 2026-03-05 | recommendation_rule 테이블 추가 (진단 결과 → 콘텐츠 패키지 자동 할당) |
| 2026-03-05 | notification 테이블에 type 컬럼 추가 (알림 유형 분류) |
| 2026-03-05 | device_token 테이블 추가 (FCM 푸시 알림용 디바이스 토큰 관리) |
| 2026-03-05 | feature, plan, plan_feature, institution_plan, user_feature_override 5개 테이블 추가 (플랜/기능 게이트 시스템) |
| 2026-03-05 | recording_feedback 테이블 추가 (음성 녹음 피드백 - 관리자/학부모 코멘트) |
| 2026-03-05 | learner_memo 테이블 추가 (학습자별 내부 직원 메모 - 관찰/상담/인수인계/일반) |
| 2026-03-08 | screening_test에 test_type 컬럼 추가 (MULTIPLE_CHOICE/LIKERT) |
| 2026-03-08 | screening_question에 sub_domain 컬럼 추가 (하위영역: 상호작용/자기조절/자기표현/사회적인식) |
| 2026-03-08 | screening_result에 sub_scores JSON 컬럼 추가 (리커트 하위영역별 점수) |
| 2026-03-08 | screening_test에 sub_domains JSON 컬럼 추가 (관리자 설정 하위영역 목록) |
| 2026-03-08 | challenge_question에 match_pairs, accept_answers JSON 컬럼 추가 (매칭게임/허용정답) |
| 2026-03-08 | challenge_attempt에 answers JSON, time_spent INT 컬럼 추가 (답변상세/소요시간) |
| 2026-03-08 | voice_recording에 stt_text, stt_confidence, question_id 컬럼 추가 (STT 결과) |
