const audioCtx = typeof window !== 'undefined' ? new (window.AudioContext || (window as any).webkitAudioContext)() : null

function ensureContext() {
  if (audioCtx?.state === 'suspended') {
    audioCtx.resume()
  }
}

/** 정답 효과음 — 밝은 상승 멜로디 */
export function playCorrectSound() {
  if (!audioCtx) return
  ensureContext()

  const now = audioCtx.currentTime

  // 두 음 연속 (도→미 느낌)
  const notes = [523.25, 659.25] // C5, E5
  notes.forEach((freq, i) => {
    const osc = audioCtx.createOscillator()
    const gain = audioCtx.createGain()
    osc.connect(gain)
    gain.connect(audioCtx.destination)

    osc.type = 'sine'
    osc.frequency.value = freq
    gain.gain.setValueAtTime(0.3, now + i * 0.12)
    gain.gain.exponentialRampToValueAtTime(0.01, now + i * 0.12 + 0.25)

    osc.start(now + i * 0.12)
    osc.stop(now + i * 0.12 + 0.25)
  })
}

/** 오답 효과음 — 낮은 하강 톤 */
export function playWrongSound() {
  if (!audioCtx) return
  ensureContext()

  const now = audioCtx.currentTime

  const osc = audioCtx.createOscillator()
  const gain = audioCtx.createGain()
  osc.connect(gain)
  gain.connect(audioCtx.destination)

  osc.type = 'square'
  osc.frequency.setValueAtTime(300, now)
  osc.frequency.linearRampToValueAtTime(200, now + 0.2)
  gain.gain.setValueAtTime(0.15, now)
  gain.gain.exponentialRampToValueAtTime(0.01, now + 0.3)

  osc.start(now)
  osc.stop(now + 0.3)
}

/** 챌린지 성공 효과음 — 축하 팡파레 */
export function playSuccessSound() {
  if (!audioCtx) return
  ensureContext()

  const now = audioCtx.currentTime
  const notes = [523.25, 659.25, 783.99, 1046.5] // C5, E5, G5, C6

  notes.forEach((freq, i) => {
    const osc = audioCtx.createOscillator()
    const gain = audioCtx.createGain()
    osc.connect(gain)
    gain.connect(audioCtx.destination)

    osc.type = 'sine'
    osc.frequency.value = freq
    gain.gain.setValueAtTime(0.25, now + i * 0.15)
    gain.gain.exponentialRampToValueAtTime(0.01, now + i * 0.15 + 0.4)

    osc.start(now + i * 0.15)
    osc.stop(now + i * 0.15 + 0.4)
  })
}
