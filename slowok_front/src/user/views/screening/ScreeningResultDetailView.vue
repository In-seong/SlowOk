<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
import { useScreeningStore } from '../../stores/screeningStore'
import type { ScreeningResult, SubDomainScore, SubDomainDef } from '@shared/types'

const route = useRoute()
const router = useRouter()
const screeningStore = useScreeningStore()

const resultId = computed(() => Number(route.params.resultId))
const loading = ref(true)

const result = computed<ScreeningResult | null>(() => {
  return screeningStore.results.find(r => r.result_id === resultId.value) ?? null
})

const isLikert = computed(() => {
  return result.value?.sub_scores && Object.keys(result.value.sub_scores).length > 0
})

onMounted(async () => {
  if (screeningStore.results.length === 0) {
    await screeningStore.fetchResults()
  }
  loading.value = false
})

// 레이더 차트
const radarData = computed(() => {
  const r = result.value
  if (!r?.sub_scores) return null

  const entries = Object.entries(r.sub_scores) as [string, SubDomainScore][]
  const count = entries.length
  if (count === 0) return null

  const centerX = 140
  const centerY = 140
  const maxR = 70
  const angleStep = (2 * Math.PI) / count

  const outerPoints = entries.map((_, i) => {
    const angle = angleStep * i - Math.PI / 2
    return { x: centerX + maxR * Math.cos(angle), y: centerY + maxR * Math.sin(angle) }
  })

  const dataPoints = entries.map(([, data], i) => {
    const ratio = data.avg / 5
    const angle = angleStep * i - Math.PI / 2
    return { x: centerX + maxR * ratio * Math.cos(angle), y: centerY + maxR * ratio * Math.sin(angle) }
  })

  const gridLines = [1, 2, 3, 4, 5].map(level => {
    const ratio = level / 5
    return entries.map((_, i) => {
      const angle = angleStep * i - Math.PI / 2
      return { x: centerX + maxR * ratio * Math.cos(angle), y: centerY + maxR * ratio * Math.sin(angle) }
    })
  })

  const labels = entries.map(([name, data], i) => {
    const angle = angleStep * i - Math.PI / 2
    const labelR = maxR + 22
    return { name, avg: data.avg, x: centerX + labelR * Math.cos(angle), y: centerY + labelR * Math.sin(angle) }
  })

  const dataPath = dataPoints.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ') + ' Z'

  return { outerPoints, dataPath, gridLines, labels, entries, centerX, centerY }
})

const overallAvg = computed(() => {
  const r = result.value
  if (!r?.sub_scores) return 0
  const entries = Object.values(r.sub_scores) as SubDomainScore[]
  if (entries.length === 0) return 0
  const total = entries.reduce((sum, d) => sum + d.avg, 0)
  return Math.round((total / entries.length) * 10) / 10
})

const DOMAIN_COLOR_PALETTE = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336', '#00BCD4', '#795548', '#607D8B']

function getDomainColor(domain: string): string {
  const r = result.value
  if (!r?.sub_scores) return '#888'
  const domains = Object.keys(r.sub_scores)
  const idx = domains.indexOf(domain)
  if (idx === -1) return '#888'
  return DOMAIN_COLOR_PALETTE[idx % DOMAIN_COLOR_PALETTE.length] ?? '#888'
}

function getLevelLabel(level: string | null): string {
  if (!level) return '-'
  const map: Record<string, string> = { '상': '우수', '중': '보통', '하': '노력' }
  return map[level] ?? level
}

function getLevelColor(level: string | null): string {
  if (!level) return '#888'
  const map: Record<string, string> = { '상': '#4CAF50', '우수': '#4CAF50', '중': '#FF9800', '보통': '#FF9800', '하': '#F44336', '노력': '#F44336' }
  return map[level] ?? '#888'
}

function formatDate(dateStr?: string): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`
}

// 하위영역 설명 맵
const subDomainDescriptions = computed<Record<string, string>>(() => {
  const test = result.value?.test
  if (!test?.sub_domains || !Array.isArray(test.sub_domains)) return {}
  const map: Record<string, string> = {}
  for (const d of test.sub_domains as SubDomainDef[]) {
    if (d.description) map[d.name] = d.description
  }
  return map
})

</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] max-w-[402px] md:max-w-[600px] mx-auto">
    <BackHeader title="진단 결과 상세" :on-back="() => router.back()" />

    <main class="px-5 pb-[80px] pt-4 space-y-4">
      <!-- 면책 + 출처 -->
      <div class="bg-[#FFF8E1] rounded-[12px] p-3">
        <p class="text-[11px] text-[#F57F17] leading-relaxed">
          본 결과는 의료 진단이 아닌 <strong>교육 목적의 발달 선별 결과</strong>입니다.
        </p>
        <div class="mt-1.5 space-y-0.5">
          <p class="text-[10px] text-[#888]">참고문헌:</p>
          <a href="https://psycnet.apa.org/record/1990-98005-000" target="_blank" rel="noopener" class="block text-[10px] text-[#2196F3] underline">• Gresham & Elliott (1990). SSRS</a>
          <a href="https://doi.org/10.1007/978-1-4419-1698-3" target="_blank" rel="noopener" class="block text-[10px] text-[#2196F3] underline">• Elliott & Gresham (2008). SSIS</a>
        </div>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#999]">불러오는 중...</p>
      </div>

      <!-- 결과 없음 -->
      <div v-else-if="!result" class="flex flex-col items-center justify-center py-20 gap-3">
        <p class="text-[14px] text-[#999]">결과를 찾을 수 없습니다.</p>
        <button
          @click="router.back()"
          class="bg-[#4CAF50] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium"
        >
          돌아가기
        </button>
      </div>

      <template v-else>
        <!-- 상단 결과 카드 -->
        <div class="bg-[#4CAF50] rounded-[16px] p-5 text-white">
          <p class="text-[13px] opacity-90 mb-1">{{ result.test?.title || '진단 검사' }}</p>
          <p class="text-[12px] opacity-70 mb-4">{{ formatDate(result.created_at) }}</p>

          <div class="flex items-center gap-5">
            <!-- 점수 원형 -->
            <div class="relative w-24 h-24 shrink-0">
              <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="8" />
                <circle
                  cx="50" cy="50" r="42" fill="none" stroke="white" stroke-width="8" stroke-linecap="round"
                  :stroke-dasharray="2 * Math.PI * 42"
                  :stroke-dashoffset="2 * Math.PI * 42 - (2 * Math.PI * 42 * result.score) / 100"
                  class="transition-all duration-1000"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-[22px] font-bold">{{ result.score }}</span>
                <span class="text-[11px] opacity-80">점</span>
              </div>
            </div>

            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-[16px] font-bold">{{ result.score }}점</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                  :style="{ backgroundColor: 'rgba(255,255,255,0.2)' }"
                >
                  {{ getLevelLabel(result.level) }}
                </span>
              </div>
              <p class="text-[13px] opacity-90">
                {{ isLikert ? '리커트 척도 (5점)' : '객관식' }}
              </p>
              <p v-if="isLikert" class="text-[12px] opacity-80 mt-1">
                전체 평균: {{ overallAvg }} / 5.0
              </p>
            </div>
          </div>
        </div>

        <!-- 리커트: 레이더 차트 -->
        <template v-if="isLikert && radarData">
          <CardSection>
            <p class="text-[13px] font-semibold text-[#333] mb-3">하위영역 분석</p>
            <div class="flex justify-center">
              <svg viewBox="0 0 280 280" class="w-[280px] h-[280px]">
                <!-- 그리드 -->
                <polygon
                  v-for="(grid, gi) in radarData.gridLines"
                  :key="'grid-' + gi"
                  :points="grid.map(p => `${p.x},${p.y}`).join(' ')"
                  fill="none" stroke="#E8E8E8" stroke-width="0.5"
                />
                <!-- 축선 -->
                <line
                  v-for="(pt, i) in radarData.outerPoints"
                  :key="'axis-' + i"
                  :x1="radarData.centerX" :y1="radarData.centerY"
                  :x2="pt.x" :y2="pt.y"
                  stroke="#E8E8E8" stroke-width="0.5"
                />
                <!-- 데이터 -->
                <path :d="radarData.dataPath" fill="rgba(76,175,80,0.2)" stroke="#4CAF50" stroke-width="2" />
                <!-- 라벨 -->
                <text
                  v-for="label in radarData.labels"
                  :key="'label-' + label.name"
                  :x="label.x" :y="label.y"
                  text-anchor="middle" dominant-baseline="central"
                  class="text-[9px] font-semibold" fill="#333"
                >
                  {{ label.name }}({{ label.avg }})
                </text>
              </svg>
            </div>
          </CardSection>

          <!-- 영역별 상세 -->
          <div class="space-y-3">
            <CardSection v-for="[domain, data] in radarData.entries" :key="domain">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <span
                    class="w-3 h-3 rounded-full shrink-0"
                    :style="{ backgroundColor: getDomainColor(domain) }"
                  />
                  <span class="text-[14px] font-bold text-[#333]">{{ domain }}</span>
                </div>
                <span class="text-[14px] font-bold" :style="{ color: getDomainColor(domain) }">{{ data.avg }} / 5.0</span>
              </div>
              <div class="bg-[#F0F0F0] rounded-full h-3 overflow-hidden mb-2">
                <div
                  class="h-full rounded-full transition-all duration-700"
                  :style="{ width: `${(data.avg / 5) * 100}%`, backgroundColor: getDomainColor(domain) }"
                />
              </div>
              <p class="text-[11px] text-[#999] mb-2">{{ data.score }}점 / {{ data.max }}점</p>
              <p
                v-if="subDomainDescriptions[domain]"
                class="text-[13px] text-[#555] leading-relaxed bg-[#F8F8F8] rounded-[8px] px-3 py-2.5"
              >
                {{ subDomainDescriptions[domain] }}
              </p>
            </CardSection>
          </div>
        </template>

        <!-- 객관식: 단순 결과 -->
        <CardSection v-if="!isLikert">
          <div class="text-center py-4">
            <p class="text-[14px] text-[#555] mb-2">
              {{ result.test?.question_count || '-' }}문항 중
              <span class="font-bold" :style="{ color: getLevelColor(result.level) }">{{ result.score }}%</span>
              정답
            </p>
            <p class="text-[13px] text-[#888]">
              등급:
              <span class="font-bold" :style="{ color: getLevelColor(result.level) }">{{ getLevelLabel(result.level) }}</span>
            </p>
          </div>
        </CardSection>

      </template>
    </main>

    <BottomNav />
  </div>
</template>
