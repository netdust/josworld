import { test, expect } from '@playwright/test';

/**
 * The footer signup is FluentForms form 2 (→ FluentCRM list `nieuwsbrief`), not YOOtheme's
 * Newsletter element, whose Mailchimp provider had no API key and answered "Invalid API key."
 * The look is the element's it replaced: a 44px pill field and a raspberry pill button on one row.
 */
test.describe('footer newsletter (desktop) @smoke @e2e', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  const footerForm = (page: import('@playwright/test').Page) => page.locator('footer form.frm-fluent-form');

  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.locator('button:has-text("Afwijzen")').first().click({ timeout: 2000 }).catch(() => {});
    await page.keyboard.press('End');
  });

  test('the signup is the FluentForms newsletter form, in Dutch', async ({ page }) => {
    await expect(page.locator('footer form.js-form-newsletter')).toHaveCount(0);
    const form = footerForm(page);
    await expect(form).toHaveCount(1);
    await expect(form).toHaveAttribute('data-form_id', '2');
    await expect(form.locator('input[type=email]')).toHaveAttribute('placeholder', 'E-mail');
    await expect(form.locator('button[type=submit]')).toHaveText('Inschrijven');
  });

  test('a wrong address is answered in Dutch', async ({ page }) => {
    const form = footerForm(page);
    await form.locator('input[type=email]').fill('naam@domein');
    await form.locator('button[type=submit]').click();
    await expect(form.locator('.error')).toContainText('geldig e-mailadres');
  });

  test('field and button keep the look of the element they replaced', async ({ page }) => {
    const form = footerForm(page);
    const look = await form.evaluate((f) => {
      const i = f.querySelector('input[type=email]')!;
      const b = f.querySelector('button[type=submit]')!;
      const cs = (e: Element) => getComputedStyle(e);
      const r = (e: Element) => e.getBoundingClientRect();
      return {
        inputBg: cs(i).backgroundColor,
        inputRadius: cs(i).borderRadius,
        inputHeight: Math.round(r(i).height),
        buttonBg: cs(b).backgroundColor,
        buttonRadius: cs(b).borderRadius,
        buttonFont: cs(b).fontFamily,
        sameRow: Math.abs(r(i).top - r(b).top) < 6,
        inputWidth: Math.round(r(i).width),
        formHeight: Math.round(f.getBoundingClientRect().height),
      };
    });
    expect(look.inputBg).toBe('rgba(252, 252, 247, 0.1)');
    expect(look.inputRadius).toBe('999px');
    expect(look.inputHeight).toBe(44);
    expect(look.buttonBg).toBe('rgb(230, 11, 102)');
    expect(look.buttonRadius).toBe('999px');
    expect(look.buttonFont).toContain('Fraunces');
    expect(look.sameRow).toBe(true);
    expect(look.inputWidth).toBeGreaterThan(400);
    expect(look.formHeight).toBeLessThanOrEqual(48);
  });
});

test.describe('footer newsletter (phone) @smoke @e2e', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('field and button stack with a small gap, as in the design', async ({ page }) => {
    await page.goto('/');
    const form = page.locator('footer form.frm-fluent-form');
    await form.scrollIntoViewIfNeeded();
    const m = await form.evaluate((f) => {
      const i = f.querySelector('input[type=email]')!.getBoundingClientRect();
      const b = f.querySelector('button[type=submit]')!.getBoundingClientRect();
      return { gap: Math.round(b.top - i.bottom), inputWidth: Math.round(i.width), buttonWidth: Math.round(b.width), formWidth: Math.round(f.getBoundingClientRect().width) };
    });
    expect(m.gap).toBeGreaterThan(0);
    expect(m.gap).toBeLessThanOrEqual(16);
    expect(m.inputWidth).toBe(m.formWidth);
    expect(m.buttonWidth).toBe(m.formWidth);
  });
});

