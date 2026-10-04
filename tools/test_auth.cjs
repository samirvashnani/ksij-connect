const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.KSIJ_TEST_URL || 'http://localhost/ksij-connect';
const output = path.join(__dirname, '..', 'tmp', 'auth-qa');
fs.mkdirSync(output, { recursive: true });
const browserPath = process.env.PLAYWRIGHT_BROWSER_PATH;

async function check(condition, description) {
  assert.ok(condition, description);
  console.log(`PASS: ${description}`);
}

async function main() {
  const browser = await chromium.launch({ headless: true, ...(browserPath ? { executablePath: browserPath } : {}) });
  try {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${base}/public/member_chat.php`);
    await check(page.url().endsWith('/public/login.php'), 'Anonymous member redirects to login');
    await page.goto(`${base}/public/admin/index.php`);
    await check(page.url().endsWith('/public/staff_login.php'), 'Anonymous admin redirects to separate login');
    await page.goto(`${base}/public/login.php`);
    await check(await page.locator('a[href*="staff_login.php"]').count() === 0, 'No public admin login link');
    const csrfFailure = await context.request.post(`${base}/public/login.php`, { form: { membership_id: 'KSIJ001' } });
    await check(csrfFailure.status() === 403, 'Login requires a valid CSRF token');

    for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
      await page.setViewportSize(viewport);
      for (const file of ['login.php', 'team_login.php', 'staff_login.php']) {
        await page.goto(`${base}/public/${file}`);
        await check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${file} fits ${viewport.width}px viewport`);
        await page.screenshot({ path: path.join(output, `${file.replace('.php', '')}-${viewport.width}.png`), fullPage: true });
      }
    }

    await page.goto(`${base}/public/login.php`);
    await page.getByLabel('Membership ID', { exact: true }).fill('KSIJ001');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.waitForURL('**/public/verify_otp.php');
    const code = await page.locator('.notice strong').textContent();
    await check(/^\d{6}$/.test(code), 'Development OTP is six digits');
    await page.getByLabel('Verification code').fill(code === '111111' ? '222222' : '111111');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await check((await page.locator('[role="alert"]').textContent()).includes('incorrect or expired'), 'Incorrect OTP is rejected');
    const oldCookie = (await context.cookies()).find(cookie => cookie.name === 'PHPSESSID').value;
    await page.getByLabel('Verification code').fill(code);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL('**/public/member_chat.php');
    const newCookie = (await context.cookies()).find(cookie => cookie.name === 'PHPSESSID').value;
    await check(oldCookie !== newCookie, 'Successful member login regenerates session ID');
    await check((await page.locator('main').textContent()).includes('KSIJ001'), 'Member workspace shows authenticated membership');
    await page.screenshot({ path: path.join(output, 'member-mobile.png'), fullPage: true });
    await page.goto(`${base}/public/verify_otp.php`);
    await check(page.url().endsWith('/public/login.php'), 'Verified OTP cannot be reused through the verification page');
    await page.goto(`${base}/public/member_chat.php`);
    await check((await context.request.get(`${base}/public/logout.php`)).status() === 405, 'Logout rejects GET requests');
    await check((await context.request.post(`${base}/public/logout.php`)).status() === 403, 'Logout rejects missing CSRF token');
    await page.getByRole('button', { name: 'Sign out', exact: true }).click();
    await page.goto(`${base}/public/member_chat.php`);
    await check(page.url().endsWith('/public/login.php'), 'Member logout clears access');
    await context.close();

    for (const account of [
      { username: 'volunteer1', login: 'team_login.php', destination: 'staff_dashboard.php', forbidden: 'admin/index.php' },
      { username: 'volunteer3', login: 'team_login.php', destination: 'staff_dashboard.php', forbidden: 'admin/index.php' },
      { username: 'ccmember1', login: 'team_login.php', destination: 'staff_dashboard.php', forbidden: 'admin/index.php' },
      { username: 'admin1', login: 'staff_login.php', destination: 'admin/index.php', forbidden: 'staff_dashboard.php' },
    ]) {
      const staffContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
      try {
        const staffPage = await staffContext.newPage();
        await staffPage.goto(`${base}/public/${account.login}`);
        await staffPage.getByLabel('Username', { exact: true }).fill(account.username);
        await staffPage.getByLabel('Password', { exact: true }).fill('test123');
        await staffPage.getByRole('button', { name: 'Sign in', exact: true }).click();
        await staffPage.waitForURL(`**/public/${account.destination}`);
        await check(staffPage.url().endsWith(account.destination), `${account.username} reaches correct workspace`);
        if (account.username === 'volunteer3') {
          await check((await staffPage.locator('main').textContent()).includes('Not approved'), 'Non-guarantor has no guarantor authority');
        }
        await staffPage.screenshot({ path: path.join(output, `${account.username}-mobile.png`), fullPage: true });
        const denied = await staffPage.goto(`${base}/public/${account.forbidden}`);
        await check(denied.status() === 403, `${account.username} cannot access another staff role workspace`);
        await staffPage.goto(`${base}/public/${account.destination}`);
        await staffPage.getByRole('button', { name: 'Sign out', exact: true }).click();
        await staffPage.goto(`${base}/public/${account.destination}`);
        await check(staffPage.url().endsWith(account.login), `${account.username} logout clears access`);
      } finally {
        await staffContext.close();
      }
    }

    for (const rejection of [
      ['team_login.php', 'volunteer4', 'test123', 'no longer active'],
      ['team_login.php', 'admin1', 'test123', 'use the admin login'],
      ['staff_login.php', 'volunteer1', 'test123', 'administrators only'],
      ['team_login.php', 'volunteer1', 'incorrect', 'incorrect'],
    ]) {
      const rejectionContext = await browser.newContext();
      try {
        const rejectionPage = await rejectionContext.newPage();
        await rejectionPage.goto(`${base}/public/${rejection[0]}`);
        await rejectionPage.getByLabel('Username', { exact: true }).fill(rejection[1]);
        await rejectionPage.getByLabel('Password', { exact: true }).fill(rejection[2]);
        await rejectionPage.getByRole('button', { name: 'Sign in', exact: true }).click();
        await check((await rejectionPage.locator('[role="alert"]').textContent()).includes(rejection[3]), `${rejection[1]} rejected: ${rejection[3]}`);
      } finally {
        await rejectionContext.close();
      }
    }
    console.log('Authentication browser checks passed.');
  } finally {
    await browser.close();
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
