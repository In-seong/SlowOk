<template>
  <div class="p-6">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-[14px] text-[#888]">사용자 학습 리포트를 조회합니다.</p>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- 콘텐츠 -->
    <div v-else>
      <div v-if="reports.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-10 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F5F5F5] flex items-center justify-center">
          <svg class="w-6 h-6 text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        </div>
        <p class="text-[14px] text-[#888]">등록된 학습 리포트가 없습니다.</p>
        <p class="text-[12px] text-[#AAA] mt-1">사용자가 학습을 진행하면 리포트가 자동 생성됩니다.</p>
      </div>

      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <table class="w-full text-left">
          <thead class="bg-[#FAFAFA] border-b border-[#E8E8E8]">
            <tr>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">ID</th>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">사용자</th>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">기간</th>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">총 학습시간</th>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">완료 항목</th>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">연속 출석</th>
              <th class="px-4 py-3 text-[12px] font-semibold text-[#888] uppercase tracking-wide">생성일</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="report in reports"
              :key="report.report_id"
              class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors"
            >
              <td class="px-4 py-3 text-[13px] text-[#555]">{{ report.report_id }}</td>
              <td class="px-4 py-3 text-[13px] text-[#333] font-medium">
                {{ report.profile?.name || '-' }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="periodClass(report.period)"
                >
                  {{ periodLabel(report.period) }}
                </span>
              </td>
              <td class="px-4 py-3 text-[13px] text-[#555]">
                {{ report.summary?.total_time || report.summary?.totalTime || '-' }}
              </td>
              <td class="px-4 py-3 text-[13px] text-[#555]">
                {{ report.summary?.completed_items ?? report.summary?.completedItems ?? '-' }}
              </td>
              <td class="px-4 py-3 text-[13px] text-[#555]">
                {{ report.summary?.streak_days ?? report.summary?.streakDays ?? '-' }}
              </td>
              <td class="px-4 py-3 text-[13px] text-[#888]">
                {{ formatDate(report.created_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import type { LearningReport } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

interface ReportWithProfile extends LearningReport {
  profile?: { name: string } | null
}

const toast = useToastStore()

const reports = ref<ReportWithProfile[]>([])
const loading = ref(false)

function periodLabel(period: string): string {
  if (period === 'weekly') return '주간'
  if (period === 'monthly') return '월간'
  return period || '기타'
}

function periodClass(period: string): string {
  if (period === 'weekly') return 'bg-[#E3F2FD] text-[#2196F3]'
  if (period === 'monthly') return 'bg-[#F3E5F5] text-[#9C27B0]'
  return 'bg-[#F5F5F5] text-[#888]'
}

function formatDate(dateStr?: string): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function fetchReports() {
  loading.value = true
  try {
    const res = await adminApi.getReports()
    if (res.data.success) reports.value = res.data.data as ReportWithProfile[]
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

onMounted(fetchReports)
</script>
