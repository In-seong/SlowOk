let audioCtx: AudioContext | null = null

function getCtx(): AudioContext | null {
  if (typeof window === 'undefined') return null
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)()
  }
  if (audioCtx.state === 'suspended') audioCtx.resume()
  return audioCtx
}

function playNote(ctx: AudioContext, freq: number, startTime: number, duration: number, volume: number, type: OscillatorType = 'sine') {
  const osc = ctx.createOscillator()
  const gain = ctx.createGain()
  osc.connect(gain)
  gain.connect(ctx.destination)
  osc.type = type
  osc.frequency.value = freq
  gain.gain.setValueAtTime(volume, startTime)
  gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration)
  osc.start(startTime)
  osc.stop(startTime + duration)
}

/** 챌린지 시작 — 밝은 하프 글리산도 */
export function playStartSound() {
  const ctx = getCtx()
  if (!ctx) return
  const now = ctx.currentTime
  const notes = [392, 440, 523.25, 587.33, 659.25] // G4 A4 C5 D5 E5
  notes.forEach((freq, i) => {
    playNote(ctx, freq, now + i * 0.06, 0.3, 0.15)
    playNote(ctx, freq * 2, now + i * 0.06, 0.2, 0.05) // 옥타브 위 잔향
  })
}

/** 정답 — 화음 + 반짝이 */
export function playCorrectSound() {
  const ctx = getCtx()
  if (!ctx) return
  const now = ctx.currentTime

  // 메인 멜로디 (도→미)
  playNote(ctx, 523.25, now, 0.2, 0.2)
  playNote(ctx, 659.25, now + 0.1, 0.3, 0.25)

  // 화음 레이어
  playNote(ctx, 783.99, now + 0.1, 0.3, 0.1) // G5
  playNote(ctx, 1046.5, now + 0.15, 0.25, 0.06) // C6 반짝이

  // 부드러운 삼각파 베이스
  playNote(ctx, 261.63, now, 0.3, 0.08, 'triangle') // C4
}

/** 오답 — 짧은 불협화음 버즈 */
export function playWrongSound() {
  const ctx = getCtx()
  if (!ctx) return
  const now = ctx.currentTime

  // 낮은 버즈
  playNote(ctx, 180, now, 0.15, 0.12, 'sawtooth')
  playNote(ctx, 160, now + 0.08, 0.15, 0.1, 'square')

  // 약간 높은 불협화음
  playNote(ctx, 220, now, 0.12, 0.06, 'triangle')
}

/** 챌린지 성공 — 팡파레 화음 */
export function playSuccessSound() {
  const ctx = getCtx()
  if (!ctx) return
  const now = ctx.currentTime

  // 메인 팡파레 (도→미→솔→높은도)
  const melody = [523.25, 659.25, 783.99, 1046.5]
  melody.forEach((freq, i) => {
    const t = now + i * 0.14
    playNote(ctx, freq, t, 0.4, 0.2)
    playNote(ctx, freq * 0.5, t, 0.35, 0.06, 'triangle') // 옥타브 아래 베이스
  })

  // 마지막 화음 (도+미+솔 동시)
  const chordTime = now + melody.length * 0.14
  playNote(ctx, 1046.5, chordTime, 0.6, 0.15) // C6
  playNote(ctx, 1318.5, chordTime, 0.6, 0.1)  // E6
  playNote(ctx, 1568, chordTime, 0.6, 0.08)    // G6

  // 반짝이 장식음
  playNote(ctx, 2093, chordTime + 0.1, 0.3, 0.04) // C7
  playNote(ctx, 2637, chordTime + 0.15, 0.25, 0.03) // E7
}
