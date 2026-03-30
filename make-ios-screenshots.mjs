import { chromium } from 'playwright';
import { readFileSync } from 'fs';

const SHOTS = '/Users/scoop/SlowOk/screenshots';
const OUT = '/Users/scoop/SlowOk/screenshots/ios';
const ICON_PATH = '/Users/scoop/SlowOk/SlowOK_App/android/SlowOK/app/src/main/ic_launcher-playstore.png';

// iPhone 6.5" (1242x2688) — 세로형
const WIDTH = 1242;
const HEIGHT = 2688;

const screens = [
  { img: '01_home.png', title: '챌린지 맵으로\n재미있게 학습해요', sub: '단계별 도전으로 성취감 UP' },
  { img: '03_learning.png', title: '맞춤 콘텐츠로\n오늘의 학습 시작', sub: '수준에 맞는 영상·텍스트·음성 학습' },
  { img: '05_mypage.png', title: '학습 현황을\n한눈에 확인', sub: '챌린지 달성, 획득한 별, 알림 관리' },
];

function makeHtml(screen) {
  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    width: ${WIDTH / 3}px;
    height: ${HEIGHT / 3}px;
    background: linear-gradient(180deg, #4CAF50 0%, #66BB6A 50%, #81C784 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: -apple-system, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
    overflow: hidden;
    position: relative;
  }

  .bg-circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
  }
  .bg-circle.c1 { width: 250px; height: 250px; top: -60px; right: -80px; }
  .bg-circle.c2 { width: 180px; height: 180px; bottom: 200px; left: -50px; }

  .text-area {
    padding: 60px 30px 30px;
    text-align: center;
    z-index: 2;
  }
  .title {
    font-size: 32px;
    font-weight: 800;
    color: #fff;
    line-height: 1.3;
    margin-bottom: 10px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.12);
    white-space: pre-line;
  }
  .sub {
    font-size: 15px;
    font-weight: 500;
    color: rgba(255,255,255,0.88);
    text-shadow: 0 1px 4px rgba(0,0,0,0.08);
  }

  .phone-area {
    flex: 1;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 0;
    z-index: 2;
  }
  .phone-frame {
    width: 300px;
    height: 600px;
    border-radius: 30px 30px 0 0;
    overflow: hidden;
    box-shadow: 0 -4px 30px rgba(0,0,0,0.2);
    background: #fff;
  }
  .phone-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top;
  }
</style>
</head>
<body>
  <div class="bg-circle c1"></div>
  <div class="bg-circle c2"></div>
  <div class="text-area">
    <div class="title">${screen.title}</div>
    <div class="sub">${screen.sub}</div>
  </div>
  <div class="phone-area">
    <div class="phone-frame">
      <img id="shot" src="" />
    </div>
  </div>
</body>
</html>`;
}

async function main() {
  const { mkdirSync } = await import('fs');
  mkdirSync(OUT, { recursive: true });

  const browser = await chromium.launch({ headless: true, channel: 'chrome' });

  for (let i = 0; i < screens.length; i++) {
    const s = screens[i];
    const page = await browser.newPage({
      viewport: { width: Math.round(WIDTH / 3), height: Math.round(HEIGHT / 3) },
      deviceScaleFactor: 3,
    });

    await page.setContent(makeHtml(s), { waitUntil: 'load' });

    // 스크린샷 이미지 삽입
    const buf = readFileSync(`${SHOTS}/${s.img}`);
    await page.evaluate((b64) => {
      document.getElementById('shot').src = 'data:image/png;base64,' + b64;
    }, buf.toString('base64'));

    await page.waitForTimeout(300);
    await page.screenshot({ path: `${OUT}/ios_${i + 1}.png`, type: 'png' });
    console.log(`saved: ios_${i + 1}.png (${s.title.replace(/\n/g, ' ')})`);
    await page.close();
  }

  await browser.close();
  console.log('done');
}

main().catch(e => { console.error(e); process.exit(1); });
