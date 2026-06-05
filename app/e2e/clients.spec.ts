import { test, expect, Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/email/i).fill(process.env.TEST_EMAIL ?? 'demo@sphereos.app')
  await page.getByLabel(/password/i).fill(process.env.TEST_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /login|sign in/i }).click()
  await expect(page).toHaveURL(/\/(dashboard)?$/)
}

test.describe('Clients', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.goto('/clients')
  })

  test('displays clients list page', async ({ page }) => {
    await expect(page.getByRole('heading', { name: /clients/i })).toBeVisible()
  })

  test('create a new client', async ({ page }) => {
    await page.getByRole('button', { name: /new client|add client/i }).click()

    const NAME = `Acme Corp ${Date.now()}`
    await page.getByLabel(/name/i).fill(NAME)
    await page.getByLabel(/email/i).fill('acme@example.com')
    await page.getByRole('button', { name: /save|create/i }).click()

    await expect(page.getByText(NAME)).toBeVisible()
  })

  test('view client detail', async ({ page }) => {
    const firstClient = page.getByRole('link', { name: /view|detail/i }).first()
    if (await firstClient.isVisible()) {
      await firstClient.click()
      await expect(page.getByRole('heading')).toBeVisible()
    }
  })
})
