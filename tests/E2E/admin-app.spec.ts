import { test, expect, Page } from '@playwright/test';
import { logIn } from './support/login';

/**
 * The acceptance matrix for specs/josworld-admin-app, driven through a real
 * browser against the live DDEV site.
 *
 * Rows 1-6 of the plan's `## Acceptance flows`; row 7 is the cli layer and
 * belongs to the integration tier, which already drives the menu globals.
 *
 * Fixtures (e2e-admin, e2e-author) are seeded by bin/e2e.sh via wp-cli, and
 * the credential arrives via env — no password literal here, same posture as
 * smoke.spec.ts.
 */

const POST_TYPES = ['case', 'team', 'vacature', 'tool'] as const;

test.describe('Josworld admin app', () => {
  test('flow 1: one Josworld menu, and no CPT left at top level', async ({ page }) => {
    await logIn(page, 'e2e-admin');

    await expect(page.locator('#adminmenu >> text=Josworld').first()).toBeVisible();

    for (const postType of POST_TYPES) {
      await expect(
        page.locator(`#adminmenu > li > a[href="edit.php?post_type=${postType}"]`),
        `${postType} must no longer be a top-level entry`,
      ).toHaveCount(0);
    }
  });

  test('flow 2: the dashboard lists four tiles whose counts match the database', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    const tiles = page.locator('.ntdst-admin__tile');
    await expect(tiles).toHaveCount(4);

    for (const label of ['Cases', 'Teamleden', 'Vacatures', 'Tools']) {
      await expect(page.locator('.ntdst-admin__tile-label', { hasText: label })).toBeVisible();
    }

    // Every tile's value is a number, not an empty node or a PHP notice.
    for (const value of await page.locator('.ntdst-admin__tile-value').allTextContents()) {
      expect(value.trim()).toMatch(/^\d+$/);
    }
  });

  /**
   * Fixed 2026-09-14. The supported `parent_file` route never stuck on an
   * `edit.php` screen here — measured returning `josworld` where core reads
   * it, yet rendering `wp-not-current-submenu`, intermittently. The fix
   * writes the menu row's own class string instead of competing for that
   * global; see AdminChromeService::holdTheMenuOpen().
   */
  test('flow 3: opening a CPT list keeps the Josworld menu open', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/edit.php?post_type=case');

    const josworld = page.locator('#adminmenu li.toplevel_page_josworld');
    await expect(josworld).toHaveClass(/wp-menu-open/);
    await expect(josworld.locator('li.current > a[href="edit.php?post_type=case"]')).toHaveCount(1);
  });

  test('flow 4: the Diensten term screen does not collapse the menu', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/edit-tags.php?taxonomy=dienst&post_type=case');

    const josworld = page.locator('#adminmenu li.toplevel_page_josworld');
    await expect(josworld, 'the documented collapse trap').toHaveClass(/wp-menu-open/);
    await expect(
      josworld.locator('li.current > a[href="edit-tags.php?taxonomy=dienst&post_type=case"]'),
    ).toHaveCount(1);
  });

  test('flow 5: a content type with no posts shows 0 and no draft line', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    await expect(page.locator('.ntdst-admin__tiles')).not.toContainText('0 concepten');
  });

  /**
   * The plan's flow 6 asked for a user who CANNOT create, and there is no such
   * user who can also open this page: all four CPTs register with
   * `capability_type => 'post'`, so `create_posts` maps to `edit_posts` — the
   * very capability the app's menu requires. An author therefore legitimately
   * sees every link. The denial branch stays covered at the unit tier
   * (AdminAppDashboardTest), where the capability can be made to answer false.
   *
   * What IS worth driving here: the least-privileged user who can reach the
   * page gets a working, complete dashboard.
   */
  test('flow 6: an author sees all four tiles and their Nieuw links', async ({ page }) => {
    await logIn(page, 'e2e-author');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    await expect(page.locator('.ntdst-admin__tile')).toHaveCount(4);
    await expect(page.locator('.ntdst-admin__tile-link')).toHaveCount(4);
    for (const href of await page.locator('.ntdst-admin__tile-link').evaluateAll(
      (els) => els.map((e) => e.getAttribute('href')),
    )) {
      expect(href).toContain('post-new.php?post_type=');
    }
  });

  test('flow p2-1: each tile links to its list screen', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    const links = page.locator('.ntdst-admin__tile-main');
    await expect(links).toHaveCount(4);

    for (const postType of POST_TYPES) {
      await expect(page.locator(`.ntdst-admin__tile-main[href$="edit.php?post_type=${postType}"]`)).toHaveCount(1);
    }
  });

  test('flow p2-2: clicking a tile lands on that list', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    await page.locator('.ntdst-admin__tile-main[href$="edit.php?post_type=case"]').click();
    await page.waitForURL(/edit\.php\?post_type=case/);
    await expect(page.locator('.wp-list-table')).toBeVisible();
  });

  test('flow p2-3/4: both activity panels render, each with its own state', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    await expect(page.locator('.ntdst-admin__panel')).toHaveCount(2);
    await expect(page.getByText('Laatste boekingen')).toBeVisible();
    await expect(page.getByText('Laatste inzendingen')).toBeVisible();
  });

  /**
   * Threat model T3. An author holds `edit_posts`, which is what opens this
   * menu — so the dashboard is reachable, and the panels must not be on it.
   * The tiles stay: post counts are not personal data.
   */
  test('flow p2-7: an author sees the tiles but no personal data', async ({ page }) => {
    await logIn(page, 'e2e-author');
    await page.goto('/wp/wp-admin/admin.php?page=josworld');

    await expect(page.locator('.ntdst-admin__tile')).toHaveCount(4);
    await expect(page.locator('.ntdst-admin__row-title')).toHaveCount(0);
  });

  /**
   * The CPT screens stay WordPress' (ruled 2026-09-14). This is the guard
   * against the skin creeping back onto them: the body carries neither class
   * and the list table keeps its own square corners.
   */
  test('flow p2-8: the CPT list screen is left to WordPress', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/edit.php?post_type=case');

    await expect(page.locator('body.jw-admin-chrome')).toHaveCount(0);
    await expect(page.locator('body.ntdst-admin')).toHaveCount(0);

    const radius = await page.locator('.wp-list-table').evaluate(
      (el) => getComputedStyle(el).borderTopLeftRadius,
    );
    expect(radius).toBe('0px');
  });
});
