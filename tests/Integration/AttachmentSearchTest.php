<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * The relation-picker search must find attachments: WordPress stores them
 * as post_status 'inherit', but `NTDST_RelationField::handleRelationSearch()`
 * (the `relation_search` api_data action) defaults to 'publish', so a
 * relation field scoped to attachments (tool.file) could never return a
 * result. The handler drops undeclared target types and requires
 * `edit_others_posts` — hence the tool/attachment fixtures and the
 * administrator user. Lives here because ntdst-core is vendored per-site
 * with no suite of its own.
 */
final class AttachmentSearchTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RelationField self-registers on after_setup_theme, already fired
        // during the wp-phpunit boot — probe rather than assume.
        $this->assertNotFalse(
            has_filter('ntdst/api_data/relation_search'),
            'precondition: the relation_search filter must be registered, or these '
            . 'tests assert against a filter that does not exist',
        );

        // An anonymous caller is rejected by the capability gate — every
        // assertion below would then test the gate, not the widening.
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

        // Rows are getFormattedPosts()'s JSON payload arrays — lowercase
        // `id`, not WP's `ID`.
        return array_map(
            static fn (array $post): int => (int) $post['id'],
            is_array($posts) ? $posts : [],
        );
    }

    /**
     * THE DEFECT: without the widening, 'inherit' is filtered out and the
     * search returns nothing.
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
     * The widening must not become "any status matches". `tool` is the
     * declared ordinary-type target here — `post` is no relation target
     * and would be dropped, testing nothing.
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
     * 'inherit' is added ALONGSIDE 'publish', not replacing it — replacing
     * would break every other type in the same query.
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
     * BACK-COMPAT: a search naming no attachment type keeps the publish-only
     * default — no status widening.
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
