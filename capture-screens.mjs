import { chromium } from 'playwright';
import { join } from 'path';

const OUT = '/Users/scoop/SlowOk/screenshots';
const BASE = 'https://slowokuser.revuplan.com';
const mobile = { width: 390, height: 844, deviceScaleFactor: 2 };

async function main() {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({
    viewport: mobile,
    deviceScaleFactor: mobile.deviceScaleFactor,
    locale: 'ko-KR',
  });
  const page = await context.newPage();

  // 로그인
  await page.goto(BASE + '/login', { waitUntil: 'networkidle', timeout: 15000 });
  await page.waitForTimeout(500);
  await page.fill('input[placeholder*="아이디"]', 'test');
  await page.fill('input[placeholder*="비밀번호"]', '123456');
  await page.click('button:has-text("로그인")');
  await page.waitForTimeout(3000);

  // 1. 홈/대시보드
  await page.screenshot({ path: join(OUT, '01_home.png') });
  console.log('captured: home — URL:', page.url());

  // 2. 주요 페이지들 순회
  const routes = [
    ['/screening', '02_screening'],
    ['/learning', '03_learning'],
    ['/challenges', '04_challenges'],
    ['/mypage', '05_mypage'],
    ['/how-to-use', '06_howto'],
  ];

  for (const [path, name] of routes) {
    try {
      await page.goto(BASE + path, { waitUntil: 'networkidle', timeout: 10000 });
      await page.waitForTimeout(1500);
      await page.screenshot({ path: join(OUT, name + '.png') });
      console.log(`captured: ${name} — URL: ${page.url()}`);
    } catch (e) {
      console.log(`skip: ${name} — ${e.message}`);
    }
  }

  await browser.close();
  console.log('done');
}

main().catch(e => { console.error(e); process.exit(1); });
