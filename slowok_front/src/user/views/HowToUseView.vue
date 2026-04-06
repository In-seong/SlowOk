<script setup lang="ts">
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'

interface GuideStep {
  step: number
  title: string
  description: string
  iconPath: string
  iconBg: string
  iconColor: string
  tips?: string[]
  uiPreview?: {
    type: 'input' | 'button' | 'card' | 'badge' | 'banner'
    content: string
    variant?: string
  }
}

const steps: GuideStep[] = [
  {
    step: 1,
    title: '회원가입',
    description: '앱을 설치하고 회원가입을 진행합니다. 가입 시 기관에서 받은 초대코드를 입력하면 기관에 바로 연결됩니다.',
    iconPath: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
    iconBg: 'bg-[#E3F2FD]',
    iconColor: 'text-[#2196F3]',
    tips: [
      '초대코드는 소속 기관(학교, 센터 등)에서 발급받습니다',
      '초대코드 없이 가입한 경우, 마이페이지에서 나중에 입력 가능합니다',
    ],
    uiPreview: {
      type: 'input',
      content: '초대코드 입력 (선택)',
    },
  },
  {
    step: 2,
    title: '기관 연결 (초대코드)',
    description: '초대코드 없이 가입한 경우, 마이페이지에서 초대코드를 입력하여 기관에 연결할 수 있습니다. 기관 연결 후 학습 콘텐츠와 진단검사를 이용할 수 있습니다.',
    iconPath: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21',
    iconBg: 'bg-[#FFF3E0]',
    iconColor: 'text-[#FF9800]',
    tips: [
      '기관 미연결 시 홈 화면에 안내 배너가 표시됩니다',
      '마이페이지 > 기관 연결 섹션에서 초대코드를 입력합니다',
    ],
    uiPreview: {
      type: 'banner',
      content: '기관 연결이 필요합니다',
      variant: 'warning',
    },
  },
  {
    step: 3,
    title: '진단검사 실시',
    description: '기관에서 배정한 진단검사를 실시합니다. 검사 결과를 바탕으로 맞춤형 챌린지가 제공됩니다.',
    iconPath: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    iconBg: 'bg-[#E3F2FD]',
    iconColor: 'text-[#2196F3]',
    tips: [
      '하단 "진단" 탭을 눌러 진단검사 목록으로 이동합니다',
      '객관식(정답고르기) 또는 리커트 척도(5점) 유형의 검사를 실시합니다',
      '리커트 검사 결과는 하위영역별 레이더 차트와 바 차트로 확인할 수 있습니다',
      '완료된 검사의 "결과 보기" 버튼을 눌러 언제든 상세 결과를 다시 확인할 수 있습니다',
    ],
    uiPreview: {
      type: 'button',
      content: '진단검사',
      variant: 'info',
    },
  },
  {
    step: 4,
    title: '레벨맵에서 챌린지 도전',
    description: '홈 화면의 레벨맵에서 배정된 챌린지를 순서대로 도전합니다. 이전 챌린지를 통과해야 다음 챌린지가 열립니다.',
    iconPath: 'M6 9H4a2 2 0 01-2-2V5a2 2 0 012-2h2M18 9h2a2 2 0 002-2V5a2 2 0 00-2-2h-2M6 3h12v6a6 6 0 01-12 0V3zM12 15v3M8 21h8M10 18h4',
    iconBg: 'bg-[#E8F5E9]',
    iconColor: 'text-[#4CAF50]',
    tips: [
      '홈 화면에 레벨맵이 표시됩니다 — 초록색 원형 버튼이 현재 도전 가능한 챌린지입니다',
      '잠금(회색) 노드는 이전 챌린지를 통과하면 열립니다',
      '완료한 챌린지는 체크 표시와 별(1~3개)이 표시됩니다',
      '카테고리별로 섹션이 구분되어 있습니다',
    ],
    uiPreview: {
      type: 'badge',
      content: '3/10 완료',
      variant: 'success',
    },
  },
  {
    step: 5,
    title: '챌린지 풀기',
    description: '챌린지 노드를 눌러 문제를 풀어보세요. 문항마다 확인 → 피드백 → 계속하기 순서로 진행됩니다. 70% 이상 맞히면 통과!',
    iconPath: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    iconBg: 'bg-[#FFF3E0]',
    iconColor: 'text-[#FF9800]',
    tips: [
      '퀴즈, 매칭, 그림 선택, 텍스트 입력, 음성 입력 등 다양한 유형의 문제가 출제됩니다',
      '정답/오답 피드백이 바로 표시됩니다',
      '통과하면 별 1~3개를 획득합니다 (점수에 따라)',
    ],
    uiPreview: {
      type: 'button',
      content: '도전하기',
      variant: 'warning',
    },
  },
  {
    step: 6,
    title: '학습 리포트 확인',
    description: '마이페이지에서 학습 리포트, 평가 결과, 알림을 확인합니다. 전체 학습 이력과 성과를 한눈에 볼 수 있습니다.',
    iconPath: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    iconBg: 'bg-[#F3E5F5]',
    iconColor: 'text-[#9C27B0]',
    tips: [
      '마이페이지 > 학습 리포트에서 전체 학습 현황을 확인합니다',
      '마이페이지 > 평가 결과에서 진단/챌린지 결과를 확인합니다',
      '진단 완료, 콘텐츠 할당, 챌린지 합격 시 자동으로 알림이 발송됩니다',
    ],
    uiPreview: {
      type: 'card',
      content: '학습 리포트',
    },
  },
  {
    step: 7,
    title: '프로필 수정 / 비밀번호 변경',
    description: '마이페이지에서 프로필 정보(이름, 연락처, 이메일)를 수정하고, 비밀번호를 변경할 수 있습니다.',
    iconPath: 'M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z',
    iconBg: 'bg-[#E8F5E9]',
    iconColor: 'text-[#4CAF50]',
    tips: [
      '마이페이지 > 내 정보 > 프로필 수정에서 정보를 변경합니다',
      '마이페이지 > 내 정보 > 비밀번호 변경에서 비밀번호를 변경합니다 (8자 이상)',
    ],
    uiPreview: {
      type: 'button',
      content: '프로필 수정',
      variant: 'success',
    },
  },
]

function getPreviewBgColor(variant?: string): string {
  switch (variant) {
    case 'warning': return 'bg-[#FFF3E0]'
    case 'success': return 'bg-[#E8F5E9]'
    case 'info': return 'bg-[#E3F2FD]'
    default: return 'bg-[#F5F5F5]'
  }
}

function getPreviewTextColor(variant?: string): string {
  switch (variant) {
    case 'warning': return 'text-[#E65100]'
    case 'success': return 'text-[#4CAF50]'
    case 'info': return 'text-[#1976D2]'
    default: return 'text-[#555]'
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[402px] md:max-w-[600px] min-h-screen relative bg-[#F5F5F5] flex flex-col">
      <BackHeader title="사용 가이드" />

      <main class="flex-1 px-5 pb-[80px] pt-3 space-y-4 overflow-y-auto">
        <!-- App Info -->
        <div class="bg-[#E8F5E9] rounded-[16px] p-5 text-center">
          <h2 class="text-[20px] font-bold text-[#4CAF50] mb-1">느려도 괜찮아</h2>
          <p class="text-[13px] text-[#555]">느린 학습자를 위한 맞춤형 학습 플랫폼</p>
        </div>

        <!-- Guide Header -->
        <div class="px-1">
          <h3 class="text-[16px] font-bold text-[#333]">사용 가이드</h3>
          <p class="text-[12px] text-[#888] mt-1">학습 콘텐츠를 진행하고 챌린지에 도전하세요</p>
        </div>

        <!-- Steps -->
        <div class="space-y-4">
          <div
            v-for="(guideStep, index) in steps"
            :key="guideStep.step"
            class="bg-white rounded-[16px] shadow-[0_0_6px_rgba(0,0,0,0.06)] overflow-hidden"
          >
            <!-- Step Header -->
            <div class="flex items-center gap-3 px-4 pt-4 pb-2">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                :class="guideStep.iconBg"
              >
                <svg
                  class="w-5 h-5"
                  :class="guideStep.iconColor"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path :d="guideStep.iconPath" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-[11px] font-bold text-white bg-[#4CAF50] rounded-full w-5 h-5 flex items-center justify-center shrink-0">
                    {{ guideStep.step }}
                  </span>
                  <h4 class="text-[15px] font-bold text-[#333]">{{ guideStep.title }}</h4>
                </div>
              </div>
            </div>

            <!-- Description -->
            <div class="px-4 pb-3">
              <p class="text-[13px] text-[#555] leading-relaxed">{{ guideStep.description }}</p>
            </div>

            <!-- UI Preview -->
            <div v-if="guideStep.uiPreview" class="mx-4 mb-3">
              <!-- Input Preview -->
              <div v-if="guideStep.uiPreview.type === 'input'" class="border border-dashed border-[#CCC] rounded-[10px] p-3 bg-[#FAFAFA]">
                <div class="flex items-center gap-2">
                  <div class="flex-1 bg-white border border-[#E8E8E8] rounded-[8px] px-3 py-2 text-[13px] text-[#AAA]">
                    {{ guideStep.uiPreview.content }}
                  </div>
                </div>
                <p class="text-[10px] text-[#AAA] mt-1.5 text-center">회원가입 화면 예시</p>
              </div>

              <!-- Button Preview -->
              <div v-if="guideStep.uiPreview.type === 'button'" class="border border-dashed border-[#CCC] rounded-[10px] p-3 bg-[#FAFAFA] flex flex-col items-center gap-2">
                <div
                  class="px-5 py-2 rounded-[10px] text-[13px] font-semibold text-white"
                  :class="{
                    'bg-[#4CAF50]': guideStep.uiPreview.variant === 'success',
                    'bg-[#2196F3]': guideStep.uiPreview.variant === 'info',
                    'bg-[#FF9800]': guideStep.uiPreview.variant === 'warning',
                  }"
                >
                  {{ guideStep.uiPreview.content }}
                </div>
                <p class="text-[10px] text-[#AAA]">버튼 예시</p>
              </div>

              <!-- Card Preview -->
              <div v-if="guideStep.uiPreview.type === 'card'" class="border border-dashed border-[#CCC] rounded-[10px] p-3 bg-[#FAFAFA]">
                <div class="bg-white rounded-[10px] shadow-sm p-3 text-center">
                  <p class="text-[13px] font-semibold text-[#555]">{{ guideStep.uiPreview.content }}</p>
                </div>
                <p class="text-[10px] text-[#AAA] mt-1.5 text-center">화면 예시</p>
              </div>

              <!-- Badge Preview -->
              <div v-if="guideStep.uiPreview.type === 'badge'" class="border border-dashed border-[#CCC] rounded-[10px] p-3 bg-[#FAFAFA] flex flex-col items-center gap-2">
                <div class="flex items-center gap-3 w-full bg-white rounded-[8px] p-2.5">
                  <div class="flex-1">
                    <div class="h-2 bg-[#E8F5E9] rounded-full overflow-hidden">
                      <div class="h-full bg-[#4CAF50] rounded-full" style="width: 60%"></div>
                    </div>
                  </div>
                  <span class="text-[12px] font-semibold text-[#4CAF50]">{{ guideStep.uiPreview.content }}</span>
                </div>
                <p class="text-[10px] text-[#AAA]">진행률 예시</p>
              </div>

              <!-- Banner Preview -->
              <div v-if="guideStep.uiPreview.type === 'banner'" class="border border-dashed border-[#CCC] rounded-[10px] p-3 bg-[#FAFAFA]">
                <div
                  class="rounded-[8px] p-3 flex items-center gap-2"
                  :class="getPreviewBgColor(guideStep.uiPreview.variant)"
                >
                  <svg class="w-4 h-4 shrink-0" :class="getPreviewTextColor(guideStep.uiPreview.variant)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                  </svg>
                  <span class="text-[12px] font-semibold" :class="getPreviewTextColor(guideStep.uiPreview.variant)">
                    {{ guideStep.uiPreview.content }}
                  </span>
                </div>
                <p class="text-[10px] text-[#AAA] mt-1.5 text-center">홈 화면 배너 예시</p>
              </div>
            </div>

            <!-- Tips -->
            <div v-if="guideStep.tips && guideStep.tips.length > 0" class="mx-4 mb-4 bg-[#F8F9FA] rounded-[10px] p-3">
              <div class="flex items-center gap-1.5 mb-2">
                <svg class="w-3.5 h-3.5 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
                <span class="text-[11px] font-bold text-[#4CAF50]">TIP</span>
              </div>
              <ul class="space-y-1">
                <li
                  v-for="(tip, tipIndex) in guideStep.tips"
                  :key="tipIndex"
                  class="text-[12px] text-[#666] leading-relaxed flex items-start gap-1.5"
                >
                  <span class="text-[#4CAF50] shrink-0 mt-0.5">&#8226;</span>
                  <span>{{ tip }}</span>
                </li>
              </ul>
            </div>

            <!-- Connector line -->
            <div
              v-if="index < steps.length - 1"
              class="flex justify-center -mb-4 relative z-10"
            >
              <div class="w-0.5 h-4 bg-[#E0E0E0]"></div>
            </div>
          </div>
        </div>

        <!-- FAQ Section -->
        <div class="mt-6 space-y-3">
          <h3 class="text-[14px] font-bold text-[#333] px-1">자주 묻는 질문</h3>

          <div class="bg-white rounded-[12px] shadow-[0_0_6px_rgba(0,0,0,0.06)] p-4 space-y-3">
            <div>
              <p class="text-[13px] font-semibold text-[#333] mb-1">Q. 초대코드는 어디서 받나요?</p>
              <p class="text-[12px] text-[#666] leading-relaxed">소속 기관(학교, 치료센터, 복지관 등)의 담당자에게 문의하세요. 기관 관리자가 초대코드를 발급합니다.</p>
            </div>
            <div class="border-t border-[#F0F0F0] pt-3">
              <p class="text-[13px] font-semibold text-[#333] mb-1">Q. 학습 콘텐츠가 보이지 않아요</p>
              <p class="text-[12px] text-[#666] leading-relaxed">기관에 연결되어 있는지 확인하세요. 기관 연결 후, 관리자가 콘텐츠를 배정해야 학습 목록에 표시됩니다.</p>
            </div>
          </div>
        </div>

        <!-- Version Info -->
        <div class="text-center py-4">
          <p class="text-[11px] text-[#AAA]">v1.0.0</p>
        </div>
      </main>

      <BottomNav />
    </div>
  </div>
</template>
