export function formatDate(date: string | null): string {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ko-KR')
}

export function formatDateTime(date: string | null): string {
  if (!date) return '-'
  return new Date(date).toLocaleString('ko-KR')
}
