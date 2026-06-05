import { test, expect } from '@playwright/test'

const UNIQUE = Date.now()
const TEST_EMAIL = `test+${UNIQUE}@example.com`
const TEST_PASSWORD = 'Password123!'
const TEST_NAME = 'Test User'

test.describe('Authentication', () => {
  test('register a new account and land on dashboard', async ({ page }) => {
    await page.goto('/register')
    await page.getByLabel(/name/i).fill(TEST_NAME)
    await page.getByLabel(/email/i).fill(TEST_EMAIL)
    await page.getByLabel(/^password$/i).fill(TEST_PASSWORD)
    await page.getByLabel(/confirm password/i).fill(TEST_PASSWORD)
    await page.getByRole('button', { name: /register/i }).click()

    await expect(page).toHaveURL(/\/(dashboard)?$/)
    await expect(page.getByText(TEST_NAME)).toBeVisible()
  })

  test('login with valid credentials', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/email/i).fill(TEST_EMAIL)
    await page.getByLabel(/password/i).fill(TEST_PASSWORD)
    await page.getByRole('button', { name: /login|sign in/i }).click()

    await expect(page).toHaveURL(/\/(dashboard)?$/)
  })

  test('login with wrong password shows error', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/email/i).fill(TEST_EMAIL)
    await page.getByLabel(/password/i).fill('wrongpassword')
    await page.getByRole('button', { name: /login|sign in/i }).click()

    await expect(page.getByText(/invalid credentials|incorrect|failed/i)).toBeVisible()
    await expect(page).toHaveURL(/\/login/)
  })

  test('protected route redirects to login when unauthenticated', async ({ page }) => {
    await page.goto('/dashboard')
    await expect(page).toHaveURL(/\/login/)
  })

  test('logout clears session and redirects to login', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/email/i).fill(TEST_EMAIL)
    await page.getByLabel(/password/i).fill(TEST_PASSWORD)
    await page.getByRole('button', { name: /login|sign in/i }).click()
    await expect(page).toHaveURL(/\/(dashboard)?$/)

    await page.getByRole('button', { name: /logout/i }).click()
    await expect(page).toHaveURL(/\/login/)

    await page.goto('/dashboard')
    await expect(page).toHaveURL(/\/login/)
  })
})
