<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use josworld\services\forms\CrmDownloadedToolService;
use Mockery;
use NtdstTests\Support\WpPostStub;

/**
 * A download contact should say WHICH tool they took, not only that they took one.
 * The hidden `tool_id` is user-controlled, so it becomes a tag only for a published
 * tool; the tag is keyed on the id (a renamed slug must not spawn a second tag).
 */
final class CrmDownloadedToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../Support/WpPostStub.php';
        require_once __DIR__
            . '/../../app/content/mu-plugins/josworld-core/services/forms/CrmDownloadedToolService.php';
    }

    private function entry(string $response): object
    {
        return (object) ['response' => $response];
    }

    public function test_it_listens_when_a_form_creates_or_updates_a_contact(): void
    {
        Actions\expectAdded('fluent_crm/contact_added_by_fluentform')->once();
        Actions\expectAdded('fluent_crm/contact_updated_by_fluentform')->once();

        new CrmDownloadedToolService();
    }

    /**
     * @dataProvider responses
     */
    public function test_the_tool_id_is_read_from_the_stored_submission(string $response, int $expected): void
    {
        Functions\when('add_action')->justReturn(true);

        $this->assertSame($expected, (new CrmDownloadedToolService())->toolIdFrom($this->entry($response)));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function responses(): array
    {
        return [
            'a numeric string' => ['{"tool_id":"44","email":"a@b.c"}', 44],
            'an integer'       => ['{"tool_id":163}', 163],
            'a form without it' => ['{"email":"a@b.c"}', 0],
            'not a number'     => ['{"tool_id":"abc"}', 0],
            'negative'         => ['{"tool_id":"-5"}', 0],
            'malformed json'   => ['{nope', 0],
            'empty'            => ['', 0],
        ];
    }

    public function test_the_tag_is_keyed_on_the_id_and_labelled_with_the_title(): void
    {
        Functions\when('add_action')->justReturn(true);

        $this->assertSame(
            ['title' => 'Tool: Canvas gedrag', 'slug' => 'tool-160'],
            (new CrmDownloadedToolService())->tagDefinition(160, 'Canvas gedrag'),
        );
    }

    /**
     * get_the_title() hands back wptexturize'd HTML ("Canvas &#8211; Gedrag"); a tag
     * label is plain text, so an editor's dash must not reach the CRM as an entity.
     */
    public function test_the_label_is_plain_text_not_texturized_html(): void
    {
        Functions\when('add_action')->justReturn(true);

        $this->assertSame(
            'Tool: Canvas – Gedrag & co',
            (new CrmDownloadedToolService())->tagDefinition(160, 'Canvas &#8211; Gedrag &amp; co')['title'],
        );
    }

    public function test_a_submission_without_a_tool_touches_nothing(): void
    {
        Functions\when('add_action')->justReturn(true);
        $subscriber = Mockery::mock();
        $subscriber->shouldNotReceive('attachTags');

        (new CrmDownloadedToolService())->tagDownloadedTool($subscriber, $this->entry('{"email":"a@b.c"}'));

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider notTaggable
     * @param mixed $post
     */
    public function test_anything_but_a_published_tool_is_not_tagged($post): void
    {
        Functions\when('add_action')->justReturn(true);
        Functions\when('get_post')->justReturn($post);
        $subscriber = Mockery::mock();
        $subscriber->shouldNotReceive('attachTags');

        (new CrmDownloadedToolService())->tagDownloadedTool($subscriber, $this->entry('{"tool_id":"7"}'));

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notTaggable(): array
    {
        return [
            'no such post'  => [null],
            'a draft tool'  => [self::post('tool', 'draft')],
            'a page'        => [self::post('page', 'publish')],
        ];
    }

    private static function post(string $type, string $status): WpPostStub
    {
        require_once __DIR__ . '/../Support/WpPostStub.php';

        $post = new WpPostStub();
        $post->post_type = $type;
        $post->post_status = $status;

        return $post;
    }
}
