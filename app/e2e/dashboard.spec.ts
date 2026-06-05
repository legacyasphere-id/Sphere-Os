import { test, expect, Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/email/i).fill(process.env.TEST_EMAIL ?? 'demo@sphereos.app')
  await page.getByLabel(/password/i).fill(process.env.TEST_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /login|sign in/i }).click()
  await expect(page).toHaveURL(/\/(dashboard)?$/)
}

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('dashboard loads with key metric cards', async ({ page }) => {
    await page.goto('/dashboard')
    await expect(page.getByRole('main')).toBeVisible()
  })

  test('sidebar navigation links work', async ({ page }) => {
    await page.goto('/dashboard')

    for (const [label, urlPattern] of [
      ['Clients', /\/clients/],
      ['Projects', /\/projects/],
      ['Invoices', /\/invoices/],
    ] as const) {
      await page.getByRole('link', { name: new RegExp(label, 'i') }).first().click()
      await expect(page).toHaveURL(urlPattern)
    }
  })
})
