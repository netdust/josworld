import { test, expect } from '@playwright/test';

/**
 * Design fixes checked against the Figma file (Jos-website, page "Copywriting + Dev",
 * 2026-09-10): button text 18px, the intro role on page titles, the sticky footer
 * reveal, the logo marquee, the case pills, the blog quote leading and the eye.
 */
const fontSize = (el: Element) => getComputedStyle(el).fontSize;

test.describe('design fixes (desktop)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('every pill button and tertiary link reads at 18px, also the small ones', async ({ page }) => {
    await page.goto('/');
    const sizes = await page.locator('main .uk-button').evaluateAll((els) => els.map((el) => getComputedStyle(el).fontSize));
    expect(sizes.length).toBeGreaterThan(3);
    expect(new Set(sizes)).toEqual(new Set(['18px']));
  });

  test('homepage H1 is the T1 role and the typewriter intro the 32px intro role', async ({ page }) => {
    await page.goto('/');
    expect(await page.locator('h1').evaluate(fontSize)).toBe('76px');
    expect(await page.locator('[x-data*=typewriter]').evaluate(fontSize)).toBe('32px');
  });

  for (const path of ['/ons-werk/', '/jobs-stages/', '/diensten/', '/privacy-cookie-policy/']) {
    test(`page title on ${path} is the 32px Inter intro role, like Blog & nieuws`, async ({ page }) => {
      await page.goto(path);
      const h1 = page.locator('h1').first();
      expect(await h1.evaluate(fontSize)).toBe('32px');
      expect(await h1.evaluate((el) => getComputedStyle(el).fontFamily)).toContain('Inter');
    });
  }

  test('case pills are 26px tall', async ({ page }) => {
    await page.goto('/ons-werk/');
    const h = await page.locator('a[rel=tag]').first().evaluate((el) => el.getBoundingClientRect().height);
    expect(h).toBeGreaterThanOrEqual(26);
  });

  test('team tiles space the text under the photo like the tool tiles', async ({ page }) => {
    const gap = async (path: string) => {
      await page.goto(path);
      return page.locator('main .el-item').first().evaluate((it) => {
        const img = it.querySelector('img')!.getBoundingClientRect();
        return Math.round(it.querySelector('.el-title')!.getBoundingClientRect().top - img.bottom);
      });
    };
    expect(await gap('/team/')).toBe(await gap('/gratis-aanbod/'));
  });

  test('Waarom Jos: the buttons sit a full margin under the intro', async ({ page }) => {
    await page.goto('/waarom-jos/');
    const gap = await page.locator('h1').first().evaluate((h1) => {
      const col = h1.closest('.uk-panel, [class*=uk-width]')!;
      const btn = col.querySelector('.uk-button')!;
      const prev = btn.parentElement!.previousElementSibling!;
      return btn.getBoundingClientRect().top - prev.getBoundingClientRect().bottom;
    });
    expect(gap).toBeGreaterThanOrEqual(24);
  });

  /**
   * The whole vacancy row is a link, AND it keeps the one-line design. Both
   * halves are asserted because they fight: YOOtheme's "Link the panel"
   * wraps title, meta and link in an `<a>`, which is exactly what collapsed
   * this row into a stack before `.jw-vacature-row` put its grid on
   * whichever element holds the three parts.
   */
  test('Jobs: a vacancy row is one link and stays on one line', async ({ page }) => {
    await page.goto('/jobs-stages/');
    const row = page.locator('.jw-vacature-row .el-item').first();

    const link = row.locator(':scope > a.uk-link-toggle');
    await expect(link).toHaveCount(1);
    await expect(link).toHaveAttribute('href', /\/jobs-stages\/[^/]+\//);
    await expect(link.locator('.el-title')).toHaveCount(1);

    const y = async (s: string) => (await row.locator(s).first().boundingBox())!.y;
    const titleY = await y('.el-title');
    expect(Math.abs(titleY - (await y('.el-meta')))).toBeLessThan(30);
    expect((await row.boundingBox())!.height).toBeLessThan(120);
  });

  test('blog quote keeps the T2 60/60 leading', async ({ page }) => {
    await page.goto('/stageverhalen-een-frisse-wind-bij-josworld/');
    expect(await page.locator('.jw-blog-body blockquote p').first().evaluate((el) => getComputedStyle(el).lineHeight)).toBe('60px');
  });

  test('footer column headings render a real Fraunces italic', async ({ page }) => {
    await page.goto('/');
    const italic = await page.evaluate(async () => {
      await document.fonts.ready;
      return [...document.fonts].some((f) => f.family.replace(/"/g, '') === 'Fraunces' && f.style === 'italic' && f.status === 'loaded');
    });
    expect(italic).toBe(true);
  });

  test('footer section carries the builder Reveal sticky effect', async ({ page }) => {
    await page.goto('/');
    const section = page.locator('footer .uk-section').first();
    expect(await section.getAttribute('uk-sticky')).toContain('position: bottom');
    expect(await section.evaluate((el) => el.classList.contains('uk-position-z-index-negative'))).toBe(true);
  });

  test('client logos autoplay in an endless loop, not on scroll', async ({ page }) => {
    await page.goto('/');
    const wall = page.locator('.jw-logo-wall').first();
    const slider = (await wall.getAttribute('uk-slider')) ?? '';
    expect(slider).toMatch(/autoplay: (1|true)/);
    expect(slider).not.toContain('parallax: true');
    expect(slider).not.toContain('finite: true');
  });

  test('language dropdown has rounded corners and the toolbar text size', async ({ page }) => {
    await page.goto('/');
    const drop = page.locator('.tm-toolbar .uk-dropdown').first();
    expect(await drop.evaluate((el) => getComputedStyle(el).borderRadius)).toBe('8px');
    expect(await drop.locator('.uk-dropdown-nav a').first().evaluate(fontSize)).toBe('12px');
  });

  test('the logo pupil touches the eye contour when the cursor is off to the side', async ({ page }) => {
    await page.goto('/');
    await page.locator('.tm-header .uk-logo svg g[data-jw-eye]').waitFor();
    await page.mouse.move(1400, 100);
    await page.waitForTimeout(800);
    const reach = await page.locator('.tm-header .uk-logo svg').evaluate((svg) => {
      const eye = [...svg.querySelectorAll('ellipse')].find((e) => !e.closest('mask'))!;
      const pupil = svg.querySelector<SVGGElement>('g[data-jw-eye]')!;
      const ctm = eye.getScreenCTM()!;
      const scale = Math.hypot(ctm.a, ctm.b);
      const centre = new DOMPoint(0, 0).matrixTransform(ctm);
      const box = pupil.getBBox();
      const pc = new DOMPoint(box.x + box.width / 2, box.y + box.height / 2).matrixTransform(pupil.getScreenCTM()!);
      const opening = (eye.rx.baseVal.value - parseFloat(getComputedStyle(eye).strokeWidth) / 2) * scale;
      return Math.abs(pc.x - centre.x) + (box.width / 2) * scale - opening;
    });
    expect(reach).toBeGreaterThanOrEqual(0);
  });

  test('the navbar that comes back on scroll-up has a white bar with padding', async ({ page }) => {
    await page.goto('/');
    await page.mouse.wheel(0, 1600); await page.waitForTimeout(400);
    await page.mouse.wheel(0, -300); await page.waitForTimeout(800);
    const bar = page.locator('.uk-navbar-container.uk-navbar-sticky').first();
    await bar.waitFor();
    const st = await bar.evaluate((el) => { const c = getComputedStyle(el); return [c.backgroundColor, c.paddingTop, c.paddingBottom]; });
    expect(st).toEqual(['rgb(255, 255, 255)', '12px', '12px']);
  });
});

test.describe('mobile menu (phone)', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

  test('the burger opens the Figma dropbar: lemon panel, two nav sizes, language row, tall CTA', async ({ page }) => {
    await page.goto('/');
    await page.locator('.tm-header-mobile .uk-navbar-toggle').click();
    const panel = page.locator('#tm-dialog-mobile.uk-dropbar');
    await expect(panel).toBeVisible();
    const lemon = panel.locator('.tm-height-min-1-1');
    expect(await lemon.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(255, 247, 148)');
    expect(Math.round((await lemon.boundingBox())!.y)).toBe(0);
    expect((await lemon.boundingBox())!.height).toBeLessThan(844 - 60);
    expect(await panel.getAttribute('uk-drop')).toContain('reveal-top');
    expect(await panel.locator('.uk-subnav a').first().evaluate((el) => getComputedStyle(el).fontSize)).toBe('16px');
    expect(await panel.locator('.uk-nav-primary > li > a').first().evaluate((el) => getComputedStyle(el).fontSize)).toBe('24px');
    expect(await panel.locator('.uk-nav-primary > li').count()).toBe(4);
    expect(await panel.locator('.uk-nav-secondary > li > a').first().evaluate((el) => getComputedStyle(el).fontSize)).toBe('16px');
    expect(await panel.locator('.uk-subnav-divider > li').count()).toBe(3);
    const btn = panel.locator('.uk-button-secondary').first();
    expect(Math.round((await btn.boundingBox())!.height)).toBe(48);
    expect(Math.round((await btn.boundingBox())!.width)).toBe(390 - 2 * 16);
    expect(await page.locator('#tm-dialog-mobile .uk-nav-default').count()).toBe(0);
  });
});

test.describe('mobile design fixes (phone)', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

  test('doelgroep header: H1 on two lines above a smaller intro, over a tall hero photo', async ({ page }) => {
    await page.goto('/voor-overheden/');
    const h1 = page.locator('h1').first();
    expect(await h1.evaluate((el) => parseFloat(getComputedStyle(el).fontSize))).toBeGreaterThanOrEqual(52);
    expect(await h1.locator('br').count()).toBe(0);
    expect(await page.locator('.uk-text-large').first().evaluate((el) => getComputedStyle(el).fontSize)).toBe('24px');
    const hero = page.locator('main img[src*="jos-team-hero"]:visible').first();
    expect((await hero.boundingBox())!.height).toBeGreaterThan(400);
  });

  test('H2 is a step under the phone H1', async ({ page }) => {
    await page.goto('/');
    expect(await page.locator('.uk-h2').first().evaluate((el) => getComputedStyle(el).fontSize)).toBe('36px');
  });

  test('stacked tiles keep a 64px row gap', async ({ page }) => {
    await page.goto('/gratis-aanbod/');
    const gap = await page.locator('main .el-item').first().evaluate((first) => { const next = first.closest('.uk-grid > div')!.nextElementSibling!; return Math.round(next.getBoundingClientRect().top - first.closest('.uk-grid > div')!.getBoundingClientRect().bottom); });
    expect(gap).toBe(64);
  });

  test('review slider shows its dots on a phone', async ({ page }) => {
    await page.goto('/voor-overheden/');
    const dots = page.locator('.jw-review .uk-dotnav');
    await dots.scrollIntoViewIfNeeded();
    await expect(dots).toBeVisible();
  });

  test('contact CTA card: photo first, then the details, both centred', async ({ page }) => {
    await page.goto('/');
    const card = page.locator('.jw-band-round-bottom .uk-grid-stack').last();
    await card.scrollIntoViewIfNeeded();
    const m = await card.evaluate((row) => { const i = [...row.querySelectorAll('img')].find((e) => e.offsetParent)!; const t = row.querySelector('.uk-panel:not(:has(img))')!; const ir = i.getBoundingClientRect(); const tr = t.getBoundingClientRect(); return { imgTop: ir.top, centreDelta: ir.left + ir.width / 2 - (tr.left + tr.width / 2), txtTop: tr.top, align: getComputedStyle(t).textAlign }; });
    expect(m.imgTop).toBeLessThan(m.txtTop);
    expect(Math.abs(m.centreDelta)).toBeLessThan(4);
    expect(m.align).toBe('center');
  });

  test('newsletter field and button stack with a small gap', async ({ page }) => {
    await page.goto('/');
    const gap = await page.locator('footer .uk-grid-small').first().locator('> div').nth(1).evaluate((el) => parseFloat(getComputedStyle(el).marginTop));
    expect(gap).toBeLessThanOrEqual(16);
  });
});
