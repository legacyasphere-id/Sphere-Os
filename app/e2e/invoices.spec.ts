import { test, expect, Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/email/i).fill(process.env.TEST_EMAIL ?? 'demo@sphereos.app')
  await page.getByLabel(/password/i).fill(process.env.TEST_PASSWORD ?? 'password')
  await page.getByRole('button', { name: /login|sign in/i }).click()
  await expect(page).toHaveURL(/\/(dashboard)?$/)
}

test.describe('Invoices', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.goto('/invoices')
  })

  test('displays invoices list page', async ({ page }) => {
    await expect(page.getByRole('heading', { name: /invoices/i })).toBeVisible()
  })

  test('open new invoice form', async ({ page }) => {
    await page.getByRole('button', { name: /new invoice|create invoice/i }).click()
    await expect(page.getByRole('dialog').or(page.getByRole('form'))).toBeVisible()
  })

  test('invoice status chips are visible', async ({ page }) => {
    const hasInvoices = await page.getByRole('row').count() > 1
    if (hasInvoices) {
      await expect(page.getByText(/draft|sent|paid/i).first()).toBeVisible()
    }
  })
})
