<?php

/**
 * CrmDownloadedToolService — tags a FluentCRM contact with the tool they downloaded.
 *
 * The download feed can only attach fixed tags, and tools are added by editors, so
 * a contact would otherwise read "downloaded a tool" for all of them. One tag per
 * tool, created on first use, keeps the whole history: a contact who took three
 * tools carries three tags.
 *
 * ABSENCE CONTRACT — FluentCRM is git-untracked, so it may be missing: every call
 * out of this class is guarded, exactly as CrmContactLanguageService does.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\forms;

defined('ABSPATH') || exit;

final class CrmDownloadedToolService
{
    private const TAG_TITLE_PREFIX = 'Tool: ';

    private const TAG_SLUG_PREFIX = 'tool-';

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // A returning visitor who downloads a second tool updates an existing contact.
        add_action('fluent_crm/contact_added_by_fluentform', [$this, 'tagDownloadedTool'], 10, 2);
        add_action('fluent_crm/contact_updated_by_fluentform', [$this, 'tagDownloadedTool'], 10, 2);
    }

    /**
     * @param mixed $subscriber FluentCrm\App\Models\Subscriber
     * @param mixed $entry      the FluentForms submission
     */
    public function tagDownloadedTool($subscriber, $entry): void
    {
        if (! is_object($subscriber) || ! method_exists($subscriber, 'attachTags')) {
            return;
        }

        $toolId = $this->toolIdFrom($entry);

        if ($toolId < 1) {
            return;
        }

        // The hidden field is user-controlled: only a published tool becomes a tag.
        $tool = get_post($toolId);

        if (! $tool instanceof \WP_Post || $tool->post_type !== 'tool' || $tool->post_status !== 'publish') {
            return;
        }

        $tagId = $this->tagIdFor($this->tagDefinition($toolId, get_the_title($tool)));

        if ($tagId > 0) {
            $subscriber->attachTags([$tagId]);
        }
    }

    /**
     * @param mixed $entry the FluentForms submission; its `response` is the submitted inputs as JSON
     */
    public function toolIdFrom($entry): int
    {
        $response = is_object($entry) ? ($entry->response ?? '') : '';
        $inputs = is_string($response) ? json_decode($response, true) : null;
        $toolId = is_array($inputs) ? ($inputs['tool_id'] ?? 0) : 0;

        return is_numeric($toolId) && (int) $toolId > 0 ? (int) $toolId : 0;
    }

    /**
     * Keyed on the id, labelled with the title: a renamed tool updates its tag's
     * label instead of spawning a second tag. get_the_title() returns texturized HTML;
     * a tag label is plain text.
     *
     * @return array{title: string, slug: string}
     */
    public function tagDefinition(int $toolId, string $title): array
    {
        return [
            'title' => self::TAG_TITLE_PREFIX . html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug'  => self::TAG_SLUG_PREFIX . $toolId,
        ];
    }

    /**
     * Looked up by slug, not through importBulk alone: its updateOrCreate matches on
     * slug AND title, so a renamed tool would get a second tag with the same slug.
     *
     * @param array{title: string, slug: string} $definition
     */
    private function tagIdFor(array $definition): int
    {
        if (! function_exists('FluentCrmApi') || ! class_exists(\FluentCrm\App\Models\Tag::class)) {
            return 0;
        }

        $tag = \FluentCrm\App\Models\Tag::where('slug', $definition['slug'])->first();

        if (! is_object($tag)) {
            FluentCrmApi('tags')->importBulk([$definition]);
            $tag = \FluentCrm\App\Models\Tag::where('slug', $definition['slug'])->first();
        } elseif ($tag->title !== $definition['title']) {
            $tag->title = $definition['title'];
            $tag->save();
        }

        return is_object($tag) && isset($tag->id) ? (int) $tag->id : 0;
    }
}
