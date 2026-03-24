import api from '@shared/api'

function downloadBlob(data: Blob, filename: string) {
  const url = window.URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

async function downloadCsv(endpoint: string, fallbackFilename: string) {
  const res = await api.get(endpoint, { responseType: 'blob' })
  const disposition = res.headers['content-disposition'] as string | undefined
  let filename = fallbackFilename
  if (disposition) {
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    if (match?.[1]) {
      filename = match[1].replace(/['"]/g, '')
    }
  }
  downloadBlob(res.data as Blob, filename)
}

export const exportApi = {
  users() {
    return downloadCsv('/admin/export/users', 'users_export.csv')
  },
  learningProgress() {
    return downloadCsv('/admin/export/learning-progress', 'learning_progress_export.csv')
  },
  screeningResults() {
    return downloadCsv('/admin/export/screening-results', 'screening_results_export.csv')
  },
}
