<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
// import StatusBadge from '@shared/components/ui/StatusBadge.vue'  // 현재 미사용
// import ProgressBar from '@shared/components/ui/ProgressBar.vue'  // 현재 미사용
import { useScreeningStore } from '../../stores/screeningStore'
import type { ScreeningResult, SubDomainScore } from '@shared/types'

const router = useRouter()
const screeningStore = useScreeningStore()

onMounted(async () => {
  await screeningStore.fetchResults()
})

// Category icon paths mapping
const categoryIconPaths: Record<string, string> = {
  '언어': 'M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129',
  '인지': 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
  '정서': 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
  '사회성': 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
}

const defaultIconPath = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'

interface CategoryResultItem {
  category: string
  score: number
  level: string
  iconPath: string
}

// Derive category results from store results
const categoryResults = computed<CategoryResultItem[]>(() => {
  return screeningStore.results.map((result: ScreeningResult) => {
    const categoryName = result.test?.category?.name || '기타'
    return {
      category: categoryName,
      score: result.score,
      level: getLevel(result.score),
      iconPath: categoryIconPaths[categoryName] || defaultIconPath,
    }
  })
})

const averageScore = computed(() => {
  if (categoryResults.value.length === 0) return 0
  return Math.round(categoryResults.value.reduce((sum, r) => sum + r.score, 0) / categoryResults.value.length)
})

const circleRadius = 42
const circleCircumference = 2 * Math.PI * circleRadius

function getLevel(score: number): string {
  if (score >= 80) return '우수'
  if (score >= 50) return '보통'
  return '노력'
}

// 리커트 결과가 있는지 확인
const hasLikertResult = computed(() => {
  return screeningStore.results.some(r => r.sub_scores && Object.keys(r.sub_scores).length > 0)
})

// 가장 최근 리커트 결과
const latestLikertResult = computed<ScreeningResult | null>(() => {
  return screeningStore.results.find(r => r.sub_scores && Object.keys(r.sub_scores).length > 0) ?? null
})

// 레이더 차트 데이터 계산
const radarData = computed(() => {
  const result = latestLikertResult.value
  if (!result?.sub_scores) return null

  const entries = Object.entries(result.sub_scores) as [string, SubDomainScore][]
  const count = entries.length
  if (count === 0) return null

  const centerX = 100
  const centerY = 100
  const maxR = 70

  // 각 축의 각도
  const angleStep = (2 * Math.PI) / count

  // 외곽선 포인트 (최대값)
  const outerPoints = entries.map((_, i) => {
    const angle = angleStep * i - Math.PI / 2
    return { x: centerX + maxR * Math.cos(angle), y: centerY + maxR * Math.sin(angle) }
  })

  // 데이터 포인트 (실제값)
  const dataPoints = entries.map(([, data], i) => {
    const ratio = data.avg / 5 // 5점 만점 기준
    const angle = angleStep * i - Math.PI / 2
    return { x: centerX + maxR * ratio * Math.cos(angle), y: centerY + maxR * ratio * Math.sin(angle) }
  })

  // 그리드 라인 (1~5 단계)
  const gridLines = [1, 2, 3, 4, 5].map(level => {
    const ratio = level / 5
    return entries.map((_, i) => {
      const angle = angleStep * i - Math.PI / 2
      return { x: centerX + maxR * ratio * Math.cos(angle), y: centerY + maxR * ratio * Math.sin(angle) }
    })
  })

  // 라벨 위치 (외곽 바깥)
  const labels = entries.map(([name, data], i) => {
    const angle = angleStep * i - Math.PI / 2
    const labelR = maxR + 22
    return { name, avg: data.avg, x: centerX + labelR * Math.cos(angle), y: centerY + labelR * Math.sin(angle) }
  })

  const outerPath = outerPoints.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ') + ' Z'
  const dataPath = dataPoints.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ') + ' Z'

  return { outerPoints, dataPath, outerPath, gridLines, labels, entries, centerX, centerY }
})

// 전체 평균
const overallAvg = computed(() => {
  const result = latestLikertResult.value
  if (!result?.sub_scores) return 0
  const entries = Object.values(result.sub_scores) as SubDomainScore[]
  if (entries.length === 0) return 0
  const total = entries.reduce((sum, d) => sum + d.avg, 0)
  return Math.round((total / entries.length) * 10) / 10
})

const DOMAIN_COLOR_PALETTE = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336', '#00BCD4', '#795548', '#607D8B']

function getDomainColor(domain: string): string {
  const result = latestLikertResult.value
  if (!result?.sub_scores) return '#888'
  const domains = Object.keys(result.sub_scores)
  const idx = domains.indexOf(domain)
  if (idx === -1) return '#888'
  const color = DOMAIN_COLOR_PALETTE[idx % DOMAIN_COLOR_PALETTE.length]
  return color ?? '#888'
}

/* --- 현재 미사용 (영역별 결과 / 맞춤 추천) ---
function getLevelBadgeVariant(level: string): 'success' | 'warning' | 'danger' | 'default' { ... }
function getProgressVariant(level: string): ProgressVariant { ... }
function getIconCircleClasses(level: string): string { ... }
const recommendations = computed(() => { ... })
function goToLearning(): void { ... }
--- */

const isEmpty = computed(() => !screeningStore.loading && screeningStore.results.length === 0)

function goToResultDetail(resultId: number): void {
  router.push({ name: 'screening-result-detail', params: { resultId } })
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] max-w-[402px] md:max-w-[600px] mx-auto">
    <BackHeader title="진단 결과" />

    <main class="px-5 pb-[80px] pt-4 space-y-4">
      <!-- 면책 + 출처 안내 -->
      <div class="bg-[#FFF8E1] rounded-[12px] p-3">
        <p class="text-[11px] text-[#F57F17] leading-relaxed">
          본 결과는 의료 진단이 아닌 <strong>교육 목적의 발달 선별 결과</strong>입니다.
          정확한 진단 및 치료는 전문 의료기관을 방문해주세요.
        </p>
        <div class="mt-1.5 space-y-0.5">
          <p class="text-[10px] text-[#888]">참고문헌:</p>
          <a href="https://psycnet.apa.org/record/1990-98005-000" target="_blank" rel="noopener" class="block text-[10px] text-[#2196F3] underline">• Gresham & Elliott (1990). Social Skills Rating System (SSRS)</a>
          <a href="https://doi.org/10.1007/978-1-4419-1698-3" target="_blank" rel="noopener" class="block text-[10px] text-[#2196F3] underline">• Elliott & Gresham (2008). Social Skills Improvement System (SSIS)</a>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="screeningStore.loading" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#999]">불러오는 중...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="screeningStore.error" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#F44336]">{{ screeningStore.error }}</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="isEmpty" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#999]">아직 진단 결과가 없습니다</p>
      </div>

      <template v-else>
        <!-- Overall Result Card -->
        <div class="bg-[#4CAF50] rounded-[16px] p-5 text-white">
          <p class="text-[13px] font-medium opacity-90 mb-4">종합 진단 결과</p>

          <div class="flex items-center gap-5">
            <!-- SVG Circle Progress Ring -->
            <div class="relative w-24 h-24 shrink-0">
              <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                <circle
                  cx="50" cy="50" :r="circleRadius"
                  fill="none"
                  stroke="rgba(255,255,255,0.2)"
                  stroke-width="8"
                />
                <circle
                  cx="50" cy="50" :r="circleRadius"
                  fill="none"
                  stroke="white"
                  stroke-width="8"
                  stroke-linecap="round"
                  :stroke-dasharray="circleCircumference"
                  :stroke-dashoffset="circleCircumference - (circleCircumference * averageScore) / 100"
                  class="transition-all duration-1000"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-[22px] font-bold">{{ averageScore }}</span>
                <span class="text-[11px] opacity-80">점</span>
              </div>
            </div>

            <!-- Summary Text -->
            <div class="flex-1">
              <p class="text-[16px] font-bold mb-1">평균 {{ averageScore }}점</p>
              <p class="text-[13px] opacity-90">{{ categoryResults.length }}개 완료</p>
              <p class="text-[12px] opacity-80 mt-2 leading-relaxed">남은 영역을 완료하면 더 정확한 분석을 받을 수 있어요</p>
            </div>
          </div>
        </div>

        <!-- 리커트 하위영역 차트 -->
        <div v-if="hasLikertResult && radarData && latestLikertResult">
          <h2 class="text-[15px] font-bold text-[#333] mb-3">하위영역 분석</h2>

          <!-- 레이더 차트 -->
          <CardSection class="mb-3">
            <p class="text-[13px] text-[#888] mb-3">{{ latestLikertResult.test?.title || '사회성 진단' }} 결과</p>
            <div class="flex justify-center">
              <svg viewBox="0 0 200 200" class="w-[220px] h-[220px]">
                <!-- 그리드 라인 -->
                <polygon
                  v-for="(grid, gi) in radarData.gridLines"
                  :key="'grid-' + gi"
                  :points="grid.map(p => `${p.x},${p.y}`).join(' ')"
                  fill="none"
                  stroke="#E8E8E8"
                  stroke-width="0.5"
                />
                <!-- 축선 -->
                <line
                  v-for="(pt, i) in radarData.outerPoints"
                  :key="'axis-' + i"
                  :x1="radarData.centerX" :y1="radarData.centerY"
                  :x2="pt.x" :y2="pt.y"
                  stroke="#E8E8E8" stroke-width="0.5"
                />
                <!-- 데이터 영역 -->
                <path :d="radarData.dataPath" fill="rgba(76,175,80,0.2)" stroke="#4CAF50" stroke-width="2" />
                <!-- 라벨 -->
                <text
                  v-for="label in radarData.labels"
                  :key="'label-' + label.name"
                  :x="label.x" :y="label.y"
                  text-anchor="middle"
                  dominant-baseline="central"
                  class="text-[9px] font-semibold" fill="#333"
                >
                  {{ label.name }}({{ label.avg }})
                </text>
              </svg>
            </div>
            <p class="text-center text-[13px] text-[#888] mt-2">전체 평균: <span class="font-bold text-[#4CAF50]">{{ overallAvg }}</span> / 5.0</p>
          </CardSection>

          <!-- 바 차트 -->
          <CardSection>
            <p class="text-[13px] font-semibold text-[#333] mb-3">영역별 점수</p>
            <div class="space-y-3">
              <div v-for="[domain, data] in radarData.entries" :key="domain">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-[13px] font-medium text-[#333]">{{ domain }}</span>
                  <span class="text-[13px] font-bold" :style="{ color: getDomainColor(domain) }">{{ data.avg }} / 5.0</span>
                </div>
                <div class="bg-[#F0F0F0] rounded-full h-3 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-700"
                    :style="{ width: `${(data.avg / 5) * 100}%`, backgroundColor: getDomainColor(domain) }"
                  />
                </div>
                <p class="text-[11px] text-[#888] mt-0.5">{{ data.score }}점 / {{ data.max }}점</p>
              </div>
            </div>
          </CardSection>
        </div>

        <!-- 검사 이력 (각 결과 클릭 → 상세) -->
        <div v-if="screeningStore.results.length > 0">
          <h2 class="text-[15px] font-bold text-[#333] mb-3">검사 이력</h2>
          <div class="space-y-2">
            <CardSection
              v-for="r in screeningStore.results"
              :key="r.result_id"
              class="cursor-pointer active:bg-[#F8F8F8] transition-colors"
              @click="goToResultDetail(r.result_id)"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-0.5">
                    <p class="text-[14px] font-semibold text-[#333] truncate">{{ r.test?.title || '진단 검사' }}</p>
                    <span
                      v-if="r.sub_scores"
                      class="shrink-0 inline-block bg-[#E3F2FD] text-[#2196F3] px-1.5 py-0.5 rounded text-[10px] font-bold"
                    >리커트</span>
                  </div>
                  <p class="text-[12px] text-[#888]">
                    {{ r.created_at ? new Date(r.created_at).toLocaleDateString('ko-KR') : '-' }}
                  </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                  <span class="text-[15px] font-bold text-[#4CAF50]">{{ r.score }}점</span>
                  <svg class="w-4 h-4 text-[#CCC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </CardSection>
          </div>
        </div>

        <!-- Category Results (현재 미사용) -->
        <!-- <div>
          <h2 class="text-[15px] font-bold text-[#333] mb-3">영역별 결과</h2>
          <div class="space-y-3">
            <CardSection v-for="result in categoryResults" :key="result.category">
              ...
            </CardSection>
          </div>
        </div> -->

        <!-- Recommendations (현재 미사용) -->
        <!-- <div v-if="recommendations.length > 0">
          ...
        </div> -->
      </template>
    </main>

    <BottomNav />
  </div>
</template>
