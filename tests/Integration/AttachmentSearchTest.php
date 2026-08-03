<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Regression cover for the ntdst-core `search_posts` attachment fix
 * (2026-08-03, Cluster B review finding CB-B-1).
 *
 * THE DEFECT. A `relation` field scoped to `post_type => 'attachment'` — the
 * idiomatic way to model "pick a downloadable file", and what josworld's
 * `tool.file` field uses — rendered a working autocomplete that could never
 * return a result. `getPostsFast()` defaults `post_status` to `'publish'`
 * (Data.php:1792), correct for every ordinary content type; but WordPress
 * stores attachments as `'inherit'` (set by `wp_insert_attachment()`, never
 * promoted by the media library). So the search matched nothing, always, and
 * an editor simply could not attach a file.
 *
 * Verified before the fix: an uploaded attachment searched through the same
 * path returned 0 results, and 1 as soon as `post_status => 'inherit'` was
 * passed.
 *
 * WHY IT WENT UNNOTICED: no sibling ntdst site had ever scoped a relation
 * field at attachments — the field type was only ever pointed at ordinary
 * content types, where the `publish` default is right.
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

        // The search_posts filter is registered by
        // NTDST_Endpoints::register_example_actions(), hooked on
        // `rest_api_init` — so it only exists during a REST request. Fire
        // that here so these tests exercise the REAL registered filter
        // rather than a hand-rolled stand-in. (Probed first: without this,
        // has_filter('ntdst/api_data/search_posts') is false and every
        // assertion below tests nothing.)
        if (! has_filter('ntdst/api_data/search_posts')) {
            do_action('rest_api_init');
        }

        $this->assertNotFalse(
            has_filter('ntdst/api_data/search_posts'),
            'precondition: the search_posts filter must be registered, or these '
            . 'tests assert against a filter that does not exist',
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
            'ntdst/api_data/search_posts',
            null,
            ['search' => $term, 'post_types' => $postTypes],
        );

        $posts = is_array($result) ? ($result['results'] ?? []) : [];

        // The endpoint returns its JSON payload shape, not WP_Post objects:
        // rows are arrays keyed id/title/excerpt/content/permalink/... —
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
     * uploaded file and must stay out of the picker.
     */
    public function test_a_draft_post_is_still_excluded_from_a_mixed_search(): void
    {
        $draft = (int) wp_insert_post([
            'post_type' => 'post',
            'post_title' => 'Whitepaper draft',
            'post_status' => 'draft',
        ]);

        $this->assertNotContains(
            $draft,
            $this->searchIds('Whitepaper', ['attachment', 'post']),
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
            'post_type' => 'post',
            'post_title' => 'Mixed Whitepaper article',
            'post_status' => 'publish',
        ]);

        $found = $this->searchIds('Mixed Whitepaper', ['attachment', 'post']);

        $this->assertContains($attachment, $found, 'the attachment must be found');
        $this->assertContains($published, $found, 'the published post must still be found');
    }

    /**
     * BACK-COMPAT — the path every other ntdst site actually uses. A search
     * that names no attachment type must behave exactly as before: published
     * only, drafts excluded, no status widening.
     */
    public function test_a_search_without_attachments_is_unchanged(): void
    {
        $published = (int) wp_insert_post([
            'post_type' => 'post',
            'post_title' => 'Legacy Whitepaper published',
            'post_status' => 'publish',
        ]);
        $draft = (int) wp_insert_post([
            'post_type' => 'post',
            'post_title' => 'Legacy Whitepaper draft',
            'post_status' => 'draft',
        ]);

        $found = $this->searchIds('Legacy Whitepaper', ['post']);

        $this->assertContains($published, $found);
        $this->assertNotContains(
            $draft,
            $found,
            'a non-attachment search must keep the publish-only default — this is the '
            . 'path every other ntdst site uses and it must not change',
        );
    }
}
