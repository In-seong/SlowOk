export interface Account {
  account_id: number
  username: string
  role: 'USER' | 'ADMIN' | 'MASTER' | 'TEST'
  institution_id: number | null
  is_active: boolean
  last_login_at: string | null
  profile?: UserProfile
  profiles?: UserProfile[]
  institution?: Institution
  permissions?: string[]
}

export interface UserProfile {
  profile_id: number
  account_id: number
  name: string
  phone: string | null
  email: string | null
  birth_date: string | null
  user_type: 'LEARNER' | 'PARENT'
  parent_profile_id: number | null
  decrypted_name?: string
  decrypted_phone?: string
  decrypted_email?: string
}

export interface LearningCategory {
  category_id: number
  name: string
  description: string | null
  icon: string | null
  institution_id: number | null
}

export interface ScreeningTest {
  test_id: number
  title: string
  description: string | null
  test_type: 'MULTIPLE_CHOICE' | 'LIKERT'
  sub_domains: SubDomainDef[] | null
  category_id: number
  question_count: number
  time_limit: number | null
  institution_id: number | null
  category?: LearningCategory
  latest_result?: ScreeningResult | null
}

export interface ScreeningQuestion {
  question_id: number
  test_id: number
  content: string
  question_type: string
  sub_domain: string | null
  options: string[]
  correct_answer: string | null
  order: number
}

export interface SubDomainDef {
  name: string
  description: string
}

export interface SubDomainScore {
  score: number
  max: number
  avg: number
}

export interface ScreeningResult {
  result_id: number
  profile_id: number
  test_id: number
  score: number
  level: string | null
  analysis: Record<string, any> | null
  sub_scores: Record<string, SubDomainScore> | null
  test?: ScreeningTest
  created_at?: string
}

export interface Curriculum {
  curriculum_id: number
  profile_id: number
  category_id: number
  current_level: string | null
  recommended_path: any | null
  category?: LearningCategory
}

export interface LearningContent {
  content_id: number
  category_id: number
  title: string
  content_type: 'VIDEO' | 'QUIZ' | 'GAME' | 'READING'
  content_data: Record<string, any> | null
  difficulty_level: number
  institution_id: number | null
  category?: LearningCategory
  progress?: LearningProgress | null
}

export interface LearningProgress {
  progress_id: number
  profile_id: number
  content_id: number
  status: 'NOT_STARTED' | 'IN_PROGRESS' | 'COMPLETED'
  score: number | null
  attempts: number
  created_at?: string
  updated_at?: string
}

export interface MatchPair {
  left: string
  right: string
  right_image?: string | null
}

export type ChallengeQuestionType = 'multiple_choice' | 'matching' | 'image_choice' | 'image_text' | 'image_voice'

export interface ChallengeQuestion {
  question_id: number
  challenge_id: number
  content: string
  question_type: ChallengeQuestionType | string | null
  options: string[]
  correct_answer: string | null
  image_url: string | null
  order: number
  match_pairs: MatchPair[] | null
  accept_answers: string[] | null
}

export interface Challenge {
  challenge_id: number
  category_id: number
  title: string
  challenge_type: string
  difficulty_level: number
  institution_id: number | null
  category?: LearningCategory
  questions?: ChallengeQuestion[]
  latest_attempt?: ChallengeAttempt | null
}

export interface FileUploadResponse {
  url: string
  path: string
  original_name: string
  size: number
}

export interface ChallengeAttempt {
  attempt_id: number
  profile_id: number
  challenge_id: number
  score: number
  is_passed: boolean
  answers?: Record<string, any>[] | null
  time_spent?: number | null
  created_at?: string
}

export interface RewardCard {
  card_id: number
  name: string
  description: string | null
  image_url: string | null
  rarity: string
  institution_id: number | null
  pivot?: { earned_at: string }
}

export interface Assessment {
  assessment_id: number
  profile_id: number
  category_id: number
  type: string
  score: number
  feedback: Record<string, any> | null
  category?: LearningCategory
  created_at?: string
}

export interface Notification {
  notification_id: number
  account_id: number
  title: string
  message: string
  type: string | null
  is_read: boolean
  created_at?: string
  updated_at?: string
}

export interface LearningReport {
  report_id: number
  profile_id: number
  period: string
  summary: Record<string, any> | null
  created_at?: string
}

export interface Subscription {
  subscription_id: number
  account_id: number
  plan_type: string
  status: 'ACTIVE' | 'EXPIRED' | 'CANCELLED'
  started_at: string
  expires_at: string | null
  account?: Account
}

export interface Institution {
  institution_id: number
  name: string
  type: string | null
  contact_info: Record<string, any> | null
  address: string | null
  invite_code?: string | null
  is_active: boolean
  admins?: Account[]
}

export interface ContentAssignment {
  assignment_id: number
  profile_id: number
  assignable_type: 'screening_test' | 'learning_content' | 'challenge'
  assignable_id: number
  assigned_by: number
  assigned_at: string
  due_date: string | null
  note: string | null
  status: 'ASSIGNED' | 'IN_PROGRESS' | 'COMPLETED'
  assignable_title?: string
  profile?: UserProfile
  assigner?: Account
}

export interface AdminPermission {
  permission_id: number
  permission_key: string
  permission_name: string
  description: string | null
  category: string
}

export interface ApiResponse<T = any> {
  success: boolean
  data: T
  message?: string
}

export interface UserDetailData {
  account_id: number
  username: string
  role: string
  is_active: boolean
  last_login_at: string | null
  profile?: UserProfile
  screening_results?: ScreeningResult[]
  learning_progress?: (LearningProgress & { content?: LearningContent })[]
  challenge_attempts?: (ChallengeAttempt & { challenge?: Challenge })[]
  assessments?: Assessment[]
  assignments?: ContentAssignment[]
}

export interface RecordingFeedback {
  feedback_id: number
  recording_id: number
  account_id: number
  comment: string
  account?: Account
  created_at?: string
}

export interface VoiceRecording {
  recording_id: number
  profile_id: number
  assignable_type: 'learning_content' | 'challenge'
  assignable_id: number
  file_path: string
  file_url: string
  original_name: string | null
  file_size: number | null
  duration: number | null
  memo: string | null
  stt_text: string | null
  stt_confidence: number | null
  question_id: number | null
  feedbacks?: RecordingFeedback[]
  profile?: UserProfile
  created_at?: string
}

export interface ContentPackage {
  package_id: number
  name: string
  description: string | null
  created_by: number
  is_active: boolean
  institution_id: number | null
  items?: ContentPackageItem[]
  creator?: Account
  created_at?: string
}

export interface ContentPackageItem {
  item_id: number
  package_id: number
  assignable_type: 'screening_test' | 'learning_content' | 'challenge'
  assignable_id: number
  sort_order: number
  assignable_title?: string
}

export interface RecommendationRule {
  rule_id: number
  category_id: number
  score_min: number
  score_max: number
  package_id: number
  institution_id: number | null
  is_active: boolean
  category?: LearningCategory
  package?: ContentPackage
  created_at?: string
}

export interface DashboardData {
  total_users: number
  total_learners: number
  total_contents: number
  total_screenings: number
}

export interface ChildDashboardData {
  profile_id: number
  name: string
  decrypted_name?: string
  birth_date: string | null
  latest_screening: {
    score: number
    level: string | null
    test_title: string | null
    date: string
  } | null
  learning: {
    total: number
    completed: number
    in_progress: number
  }
  challenge: {
    total: number
    passed: number
  }
  latest_activity_at: string | null
}

export interface Feature {
  feature_id: number
  feature_key: string
  name: string
  description: string | null
  category: string
  sort_order: number | null
}

export interface Plan {
  plan_id: number
  name: string
  description: string | null
  price: number | null
  sort_order: number | null
  is_active: boolean
  features?: Feature[]
}

export interface InstitutionPlanData {
  institution_plan_id: number
  institution_id: number
  plan_id: number
  started_at: string
  expires_at: string | null
  institution?: Institution
  plan?: Plan
}

export interface UserFeatureOverrideData {
  override_id: number
  profile_id: number
  feature_key: string
  enabled: boolean
}

export interface LearnerMemo {
  memo_id: number
  profile_id: number
  account_id: number
  category: 'observation' | 'consultation' | 'handover' | 'general'
  content: string
  is_pinned: boolean
  account?: Account
  created_at?: string
  updated_at?: string
}
