<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Regression cover for the relation-picker attachment fix (2026-08-03,
 * Cluster B review finding CB-B-1), re-targeted at the surface that now
 * carries it: `NTDST_RelationField::handleRelationSearch()` behind the
 * `relation_search` api_data action (ntdst-core/admin/RelationField.php).
 * The public `search_posts` example action these tests originally exercised
 * was deleted from the framework with the rest of the example surface.
 *
 * THE DEFECT. A `relation` field scoped to `post_type => 'attachment'` — the
 * idiomatic way to model "pick a downloadable file", and what josworld's
 * `tool.file` field uses — rendered a working autocomplete that could never
 * return a result: the search path defaults `post_status` to `'publish'`,
 * correct for every ordinary content type, but WordPress stores attachments
 * as `'inherit'` (set by `wp_insert_attachment()`, never promoted by the
 * media library). So the search matched nothing, always, and an editor
 * simply could not attach a file.
 *
 * THE SURFACE'S OWN GATES, which these tests must satisfy to reach the
 * behaviour under cover (they are the handler's contract, not incidental):
 *  - the requested type must be a DECLARED relation target — derived from
 *    the registered schemas, so `attachment` (tool.file) and `tool`
 *    (tool.related_tools) qualify; a type nobody points a relation field at
 *    (e.g. `post`) is silently dropped from the request;
 *  - the caller must hold the target type's `edit_others_posts` — hence the
 *    administrator fixture in setUp().
 *
 * This test lives in josworld because ntdst-core is vendored per-site with no
 * suite of its own; it travels with the fix, like
 * RelationFieldReverseLookupTest.
 */
final class AttachmentSearchTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // NTDST_RelationField self-registers on `after_setup_theme` @ 20
        // (RelationField.php's unconditional bootstrap), which has already
        // fired during the wp-phpunit boot. Probe rather than assume.
        $this->assertNotFalse(
            has_filter('ntdst/api_data/relation_search'),
            'precondition: the relation_search filter must be registered, or these '
            . 'tests assert against a filter that does not exist',
        );

        // handleRelationSearch() requires the target type's
        // `edit_others_posts`; an anonymous caller gets forbidden_post_type
        // and every assertion below would test the gate, not the widening.
        wp_set_current_user(
            self::factory()->user->create(['role' => 'administrator']),
        );
    }

    private function makeAttachment(string $title): int
    {
        // Exactly what the media library produces: post_status 'inherit'.
        return (int) wp_insert_post([
            'post_type' => 'attachment',
            'post_title' => $title,
            'post_status' => 'inherit',
            'post_mime_type' => 'application/pdf',
        ]);
    }

    /**
     * @param list<string> $postTypes
     * @return list<int>
     */
    private function searchIds(string $term, array $postTypes): array
    {
        $result = apply_filters(
            'ntdst/api_data/relation_search',
            null,
            ['search' => $term, 'post_types' => $postTypes],
        );

        $posts = is_array($result) ? ($result['results'] ?? []) : [];

        // The handler returns getFormattedPosts()'s JSON payload shape, not
        // WP_Post objects: rows are arrays keyed id/title/excerpt/... —
        // lowercase `id`, not WP's `ID`. Probed rather than assumed.
        return array_map(
            static fn (array $post): int => (int) $post['id'],
            is_array($posts) ? $posts : [],
        );
    }

    /**
     * THE DEFECT ITSELF. Fails without the fix — the search returns nothing
     * because the attachment's `inherit` status is filtered out.
     */
    public function test_the_relation_picker_search_finds_an_uploaded_attachment(): void
    {
        $id = $this->makeAttachment('Josworld Whitepaper');

        $this->assertContains(
            $id,
            $this->searchIds('Whitepaper', ['attachment']),
            'a relation field scoped to attachments must be able to find an uploaded file — '
            . "attachments are stored with post_status 'inherit', not 'publish'",
        );
    }

    /**
     * The widening must not turn into "any status matches". A draft is not an
     * uploaded file and must stay out of the picker. `tool` is the declared
     * ordinary-type relation target here (tool.related_tools) — `post` is not
     * a target of any josworld relation field and would be dropped, testing
     * nothing.
     */
    public function test_a_draft_post_is_still_excluded_from_a_mixed_search(): void
    {
        $draft = (int) wp_insert_post([
            'post_type' => 'tool',
            'post_title' => 'Whitepaper draft',
            'post_status' => 'draft',
        ]);

        $this->assertNotContains(
            $draft,
            $this->searchIds('Whitepaper', ['attachment', 'tool']),
            'widening to inherit must not admit drafts — the picker shows publishable '
            . 'content and real uploads, nothing else',
        );
    }

    /**
     * A mixed search must return BOTH kinds. This is why the fix adds
     * 'inherit' alongside 'publish' rather than replacing it — replacing
     * would fix attachments by breaking every other type in the same query.
     */
    public function test_a_mixed_search_returns_both_published_posts_and_attachments(): void
    {
        $attachment = $this->makeAttachment('Mixed Whitepaper');
        $published = (int) wp_insert_post([
            'post_type' => 'tool',
            'post_title' => 'Mixed Whitepaper article',
            'post_status' => 'publish',
        ]);

        $found = $this->searchIds('Mixed Whitepaper', ['attachment', 'tool']);

        $this->assertContains($attachment, $found, 'the attachment must be found');
        $this->assertContains($published, $found, 'the published tool must still be found');
    }

    /**
     * BACK-COMPAT — the path every relation field scoped to an ordinary type
     * uses. A search that names no attachment type must behave exactly as
     * before: published only, drafts excluded, no status widening.
     */
    public function test_a_search_without_attachments_is_unchanged(): void
    {
        $published = (int) wp_insert_post([
            'post_type' => 'tool',
            'post_title' => 'Legacy Whitepaper published',
            'post_status' => 'publish',
        ]);
        $draft = (int) wp_insert_post([
            'post_type' => 'tool',
            'post_title' => 'Legacy Whitepaper draft',
            'post_status' => 'draft',
        ]);

        $found = $this->searchIds('Legacy Whitepaper', ['tool']);

        $this->assertContains($published, $found);
        $this->assertNotContains(
            $draft,
            $found,
            'a non-attachment search must keep the publish-only default — this is the '
            . 'path every relation field scoped to an ordinary type relies on',
        );
    }
}
