import { test, expect } from '@playwright/test';

const demo = async (page, role = 'student') => {
  await page.goto('/login');
  await page
    .getByRole('button', {
      name:
        role === 'student'
          ? 'Explore the student demo'
          : `${role === 'lecturer' ? 'Faculty' : 'Admin'} demo`,
    })
    .click();
  await expect(page.getByRole('heading', { name: /Hey,/ })).toBeVisible();
};
test('student campus flows work on desktop and mobile without page errors or horizontal overflow', async ({
  page,
}) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await demo(page);
  await expect(page.getByRole('heading', { name: 'Your communities' })).toBeVisible();
  await page.getByRole('link', { name: 'Discover', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'There’s a place for you.' })).toBeVisible();
  const reading = page
    .locator('article')
    .filter({ has: page.getByRole('heading', { name: 'The reading room' }) });
  await expect(reading).toBeVisible();
  if (await reading.getByRole('button', { name: 'Join The reading room', exact: true }).isVisible())
    await reading.getByRole('button', { name: 'Join The reading room', exact: true }).click();
  await reading.getByRole('link', { name: 'Open The reading room' }).click();
  await expect(page.getByRole('heading', { name: 'The reading room', exact: true })).toBeVisible();
  const message = `A campus thought ${Date.now()}`;
  await page.getByRole('textbox', { name: 'Write a message' }).fill(message);
  await page.getByRole('button', { name: 'Send message', exact: true }).click();
  await expect(page.getByText(message, { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByText(message, { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'My profile', exact: true }).click();
  await page.getByLabel('A little about you').fill('Making good campus connections.');
  await page.getByRole('button', { name: 'Save my profile' }).click();
  await expect(page.getByRole('status')).toContainText('Your profile is looking good.');
  await page.getByRole('button', { name: 'Switch to dark theme' }).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.getByRole('button', { name: 'Switch to light theme' }).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /Hey,/ })).toBeVisible();
  await expect(page.locator('.recent-item').first()).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  );
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await page.getByRole('link', { name: 'Messages', exact: false }).first().click();
  await expect(page.getByRole('heading', { name: 'Messages', exact: false })).toBeVisible();
  await page.getByRole('link', { name: /Sophia Chen/ }).click();
  await expect(page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
  await page.getByRole('button', { name: 'Back to conversations' }).click();
  await expect(page.getByRole('textbox', { name: 'Search conversations' })).toBeVisible();
  expect(errors).toEqual([]);
});
test('two users receive live messages, anonymous identity stays hidden, and faculty can review reports', async ({
  browser,
}) => {
  const first = await browser.newContext();
  const second = await browser.newContext();
  const author = await first.newPage();
  const peer = await second.newPage();
  await demo(author);
  await peer.goto('/login');
  await peer.getByLabel('College email').fill('sophia@college.edu');
  await peer.getByLabel('Password', { exact: true }).fill('CampusDemo!2026');
  await peer.getByRole('button', { name: 'Sign in', exact: true }).click();
  await author.getByRole('link', { name: /Sophia Chen/ }).click();
  await peer.getByRole('link', { name: /Akhil Rao/ }).click();
  await expect(peer.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
  await expect(peer.getByText('You’re connected', { exact: true })).toBeVisible();
  await expect(author.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
  const toggle = author.getByRole('button', { name: 'Anonymous off' });
  if (await toggle.isVisible()) await toggle.click();
  await expect(author.getByRole('button', { name: 'Anonymous on' })).toBeVisible();
  const text = `Anonymous browser check ${Date.now()}`;
  await author.getByRole('textbox', { name: 'Write a message' }).fill(text);
  await author.getByRole('button', { name: 'Send message', exact: true }).click();
  const row = peer.locator('.message-row').filter({ hasText: text });
  await expect(row).toBeVisible();
  await expect(row.locator('.message-author')).toHaveText('Anonymous');
  await expect(
    author.locator('.message-row').filter({ hasText: text }).locator('.message-meta'),
  ).toContainText('Read');
  await row.getByRole('button', { name: 'Report message from Anonymous' }).click();
  await peer.getByLabel('Send to faculty').selectOption({ label: 'Evelyn Reed' });
  await peer.getByLabel('Anything else?').fill('Browser test report');
  await peer.getByRole('button', { name: 'Send report' }).click();
  await expect(peer.getByRole('status')).toContainText('Report sent');
  await author.getByRole('button', { name: 'Anonymous on' }).click();
  await author.getByRole('button', { name: 'Sign out', exact: true }).click();
  await demo(author, 'lecturer');
  await author.getByRole('link', { name: 'Moderation', exact: true }).click();
  const report = author.locator('article').filter({ hasText: text });
  await expect(report).toContainText('Akhil Rao');
  await report.getByRole('button', { name: 'Mark reviewed' }).click();
  await author.getByRole('button', { name: /Reviewed/ }).click();
  await expect(author.locator('article').filter({ hasText: text })).toBeVisible();
  await first.close();
  await second.close();
});
test('new student registration joins the correct class and private community creation persists', async ({
  page,
}) => {
  const suffix = Date.now();
  await page.goto('/register');
  await page.getByLabel('First name').fill('Jamie');
  await page.getByLabel('Last name').fill('Campus');
  await page.getByLabel('College email').fill(`jamie${suffix}@college.edu`);
  await page.getByLabel('Roll number').fill(`J${suffix}`);
  await page.getByLabel('Section', { exact: true }).selectOption('CSE-A');
  await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass!');
  await page.getByLabel('Confirm password').fill('BrowserTestPass!');
  await page.getByRole('button', { name: 'Create my account' }).click();
  await expect(page.getByRole('heading', { name: 'Hey, Jamie' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'CSE-A · Year 3' })).toBeVisible();
  await page.getByRole('link', { name: 'My groups', exact: true }).click();
  await page.getByRole('button', { name: 'Create a space', exact: true }).click();
  await page.getByRole('button', { name: /Private group/ }).click();
  const name = `Project circle ${suffix}`;
  await page.getByLabel('Space name').fill(name);
  await page.getByRole('button', { name: 'Create my space' }).click();
  await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
});
test('admin can create, edit, deactivate and reactivate campus sections', async ({ page }) => {
  await demo(page, 'admin');
  await page.getByRole('link', { name: 'Administration', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'The bigger picture.' })).toBeVisible();
  await page.getByRole('button', { name: 'New section' }).click();
  const code = `T${Date.now().toString().slice(-8)}`;
  await page.getByLabel('Section code').fill(code);
  await page.getByLabel('Section name').fill('Browser test section');
  await page.getByLabel('Department', { exact: true }).fill('Engineering');
  await page.getByRole('button', { name: 'Save section' }).click();
  await page.getByRole('button', { name: 'Sections', exact: true }).click();
  const row = page.getByRole('row').filter({ hasText: code });
  await expect(row).toBeVisible();
  await row.getByRole('button', { name: 'Deactivate' }).click();
  await expect(row).toContainText('Inactive');
  await row.getByRole('button', { name: 'Activate', exact: true }).click();
  await expect(row).toContainText('Active');
  await row.getByRole('button', { name: `Edit ${code}` }).click();
  await page.getByLabel('Section name').fill('Updated browser section');
  await page.getByRole('button', { name: 'Save section' }).click();
  await expect(row).toContainText('Updated browser section');
});
