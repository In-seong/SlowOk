/**
 * 이미지 파일을 Canvas로 리사이즈 + 압축하여 반환
 * @param file 원본 이미지 파일
 * @param maxWidth 최대 너비 (기본 1200px)
 * @param maxHeight 최대 높이 (기본 1200px)
 * @param quality JPEG 품질 0~1 (기본 0.8)
 * @returns 압축된 File 객체
 */
export function compressImage(
  file: File,
  maxWidth = 1200,
  maxHeight = 1200,
  quality = 0.8,
): Promise<File> {
  return new Promise((resolve, reject) => {
    // 이미 작은 파일은 그대로 반환 (500KB 이하)
    if (file.size <= 500 * 1024) {
      resolve(file)
      return
    }

    const img = new Image()
    const url = URL.createObjectURL(file)

    img.onload = () => {
      URL.revokeObjectURL(url)

      let { width, height } = img

      // 비율 유지하며 리사이즈
      if (width > maxWidth || height > maxHeight) {
        const ratio = Math.min(maxWidth / width, maxHeight / height)
        width = Math.round(width * ratio)
        height = Math.round(height * ratio)
      }

      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height

      const ctx = canvas.getContext('2d')
      if (!ctx) {
        resolve(file)
        return
      }

      ctx.drawImage(img, 0, 0, width, height)

      canvas.toBlob(
        (blob) => {
          if (!blob) {
            resolve(file)
            return
          }
          const ext = file.type === 'image/png' ? '.png' : '.jpg'
          const name = file.name.replace(/\.[^.]+$/, '') + '_compressed' + ext
          const compressed = new File([blob], name, { type: blob.type })
          resolve(compressed)
        },
        file.type === 'image/png' ? 'image/png' : 'image/jpeg',
        quality,
      )
    }

    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('이미지를 불러올 수 없습니다.'))
    }

    img.src = url
  })
}
