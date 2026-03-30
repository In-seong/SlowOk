import { chromium } from 'playwright';

const OUT = '/Users/scoop/SlowOk/screenshots/feature_graphic.png';
const SHOTS = '/Users/scoop/SlowOk/screenshots';

// 1024x500 feature graphic — rendered at 2x for high-res (2048x1000), then captured at viewport
const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    width: 1024px;
    height: 500px;
    background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 40%, #81C784 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
    overflow: hidden;
    position: relative;
  }

  /* 배경 장식 */
  .bg-circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
  }
  .bg-circle.c1 { width: 300px; height: 300px; top: -80px; left: -60px; }
  .bg-circle.c2 { width: 200px; height: 200px; bottom: -50px; right: 100px; }
  .bg-circle.c3 { width: 150px; height: 150px; top: 50px; right: -30px; }

  /* 왼쪽: 텍스트 영역 */
  .left {
    flex: 0 0 380px;
    padding: 40px 30px 40px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    z-index: 2;
  }
  .app-icon {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
  }
  .title {
    font-size: 42px;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 12px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }
  .subtitle {
    font-size: 17px;
    font-weight: 500;
    color: rgba(255,255,255,0.92);
    line-height: 1.6;
    text-shadow: 0 1px 4px rgba(0,0,0,0.1);
  }

  /* 오른쪽: 스크린샷 영역 */
  .right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    padding-right: 30px;
    z-index: 2;
  }
  .phone-frame {
    width: 185px;
    height: 380px;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    background: #fff;
    position: relative;
  }
  .phone-frame.mid {
    width: 200px;
    height: 410px;
    transform: translateY(-10px);
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
  <div class="bg-circle c3"></div>

  <div class="left">
    <img class="app-icon" src="app_icon.png" />
    <div class="title">느려도<br>괜찮아</div>
    <div class="subtitle">아이의 속도에 맞춘<br>사회성 발달 학습 플랫폼</div>
  </div>

  <div class="right">
    <div class="phone-frame">
      <img src="05_mypage.png" />
    </div>
    <div class="phone-frame mid">
      <img src="01_home.png" />
    </div>
    <div class="phone-frame">
      <img src="03_learning.png" />
    </div>
  </div>
</body>
</html>`;

async function main() {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const page = await browser.newPage({
    viewport: { width: 1024, height: 500 },
    deviceScaleFactor: 2,
  });

  await page.setContent(html, { waitUntil: 'load' });

  // 로컬 이미지를 페이지에 삽입
  // 아이콘
  const iconBuf = (await import('fs')).readFileSync(
    '/Users/scoop/SlowOk/SlowOK_App/android/SlowOK/app/src/main/ic_launcher-playstore.png'
  );
  await page.evaluate((b64) => {
    document.querySelector('.app-icon').src = 'data:image/png;base64,' + b64;
  }, iconBuf.toString('base64'));

  // 스크린샷 이미지들
  const screens = ['05_mypage', '01_home', '03_learning'];
  for (let i = 0; i < screens.length; i++) {
    const buf = (await import('fs')).readFileSync(`${SHOTS}/${screens[i]}.png`);
    await page.evaluate(({ b64, idx }) => {
      document.querySelectorAll('.phone-frame img')[idx].src = 'data:image/png;base64,' + b64;
    }, { b64: buf.toString('base64'), idx: i });
  }

  await page.waitForTimeout(500);
  await page.screenshot({ path: OUT, type: 'png' });
  await browser.close();
  console.log('Feature graphic saved:', OUT);
}

main().catch(e => { console.error(e); process.exit(1); });
