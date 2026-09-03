<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * The relation-picker search must find attachments: WordPress stores them
 * as post_status 'inherit', but `NTDST_RelationField::handleRelationSearch()`
 * (GET /ntdst/v1/relation/search since 5.0 — the api_data action died with
 * the command dispatcher) defaults to 'publish', so a relation field scoped
 * to attachments (tool.file) could never return a result. The route's
 * permission admits only declared relation targets and requires the target
 * type's `edit_others_posts` — hence the fixtures and the administrator
 * user. `tool` is no longer a declared target through josworld's own
 * schema (cluster B shrank the model to its card — the relation moved into
 * the builder), so this test registers a throwaway fixture model that
 * still points a `relation` field at `tool`, keeping `tool` reachable for
 * the mixed-search/widening assertions below without depending on
 * production schema. Lives here because ntdst-core carries the behaviour
 * and this site is a consumer that depends on it.
 */
final class AttachmentSearchTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keeps `tool` a declared relation target for this test class only —
        // the allow-list is derived from ALL registered schemas, so a
        // fixture model is enough; no production field needs to reference it.
        // @phpstan-ignore function.notFound (ntdst_data() cross-package discovery gap — see sibling test files)
        ntdst_data()->register('fixture_tool_linker', [
            'label' => 'Fixture Tool Linker',
            'fields' => ['linked' => ['type' => 'relation', 'post_type' => 'tool', 'multiple' => true]],
        ]);

        // The route registers through ntdst_rest() on rest_api_init — probe
        // WordPress's own register rather than assume.
        $this->assertArrayHasKey(
            '/ntdst/v1/relation/search',
            rest_get_server()->get_routes('ntdst/v1'),
            'precondition: the relation-search route must be registered, or these '
            . 'tests assert against an endpoint that does not exist',
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
        $request = new \WP_REST_Request('GET', '/ntdst/v1/relation/search');
        $request->set_param('search', $term);
        $request->set_param('post_type', $postTypes);

        $response = rest_do_request($request);

        $this->assertSame(
            200,
            $response->get_status(),
            'the search request itself must succeed — a 4xx here means the '
            . 'permission or arg schema refused it, not that nothing matched',
        );

        $data = $response->get_data();
        $posts = is_array($data) ? ($data['results'] ?? []) : [];

        // Rows carry exactly what the picker reads: lowercase `id` + `title`.
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
