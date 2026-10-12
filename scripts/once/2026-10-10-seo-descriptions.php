<?php
/**
 * One-off, per environment (any time; independent of the code deploy):
 *
 *   wp eval-file scripts/once/2026-10-10-seo-descriptions.php
 *
 * The meta description of a page or single is its excerpt, read before the content — and the content of a
 * builder page starts with its own heading, and a case or team member's with the template's placeholder text.
 * This writes the excerpts that are missing: the pages by slug, a case from its headline and client, a team
 * member from their bio, the vacancy by hand. A filled excerpt is an editor's choice and is never overwritten.
 * Run it with `wp eval-file` only: it re-saves through wp_update_post, and WP-CLI is what keeps kses from
 * rewriting the builder layouts. A save moves `post_modified` (so `lastmod` shifts once) and a changed excerpt
 * re-translates the EN/FR copies baseline still counts as unchecked.
 * Database content, not code: the staging -> production copy carries it.
 */
if (has_filter('content_save_pre', 'wp_filter_post_kses')) {
    exit("kses is active: run this with `wp eval-file`.\n");
}

const PLACEHOLDER = '/lorem ipsum|page title over two lines|intro paragraph/i';

// The listings (ons-werk, team, jobs-stages, gratis-aanbod) are post-type archives: their description is the type's.
const PAGES = [
    'waarom-jos'          => 'Waarom Jos? Anders dan de rest, en dat is nodig. Jos is niet gewoon een consultant. Ontdek hoe wij communicatie aanpakken.',
    'diensten'            => 'Je hebt een verhaal dat ertoe doet. Jos zorgt dat mensen het begrijpen, vertrouwen én gebruiken. Ontdek onze diensten.',
    'samenwerken'         => 'Waar wil je heen? Bij Jos beginnen we niet bij een offerte, maar bij je doel. Ontdek hoe we samenwerken.',
    'voor-overheden'      => 'Samen krijgen we iedereen mee: burgers, ondernemers en lokale besturen. Jos brengt je overheid of publieke dienst dicht bij de mensen.',
    'voor-social-profit'  => 'Waar jij het voor doet, doet er weer toe. Samen krijgen we meer mensen in beweging: Jos voor social profits en duurzame ondernemers.',
    'blog-nieuws'         => 'Nieuws en verhalen van Jos: over ons werk, ons team en de keuzes die we maken.',
    'contact'             => 'Eens afspreken met Jos? Plan een sparmoment met Goele of bel ons. We zijn benieuwd naar je project!',
    'privacy-cookie-policy' => 'Privacy- en cookiebeleid van Jos: we meten het bezoek met Matomo op onze eigen server, zonder cookies.',
];

const VACATURES = [
    'copywriter' => 'Jos zoekt een copywriter die scherpe, heldere teksten schrijft voor overheden, social profits en duurzame ondernemers.',
];

/** Unlike SeoService::trimDescription() this prefers to end on a sentence: a bio reads as a description that way. */
function jos_trim(string $text, int $max = 155): string
{
    $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $sentence = max(mb_strrpos($cut, '. '), mb_strrpos($cut, '! '), mb_strrpos($cut, '? '));
    if ($sentence !== false && $sentence > 60) {
        return mb_substr($cut, 0, $sentence + 1);
    }

    return rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')), ',;: ') . '…';
}

function jos_fill(WP_Post $post, string $excerpt): int
{
    if (trim($post->post_excerpt) !== '' || $excerpt === '') {
        return 0;
    }
    if (preg_match(PLACEHOLDER, $excerpt)) {
        echo "#{$post->ID} {$post->post_type}/{$post->post_name}: placeholder text, skipped\n";

        return 0;
    }
    $saved = wp_update_post(['ID' => $post->ID, 'post_excerpt' => $excerpt], true);
    if (is_wp_error($saved)) {
        echo "#{$post->ID} {$post->post_type}/{$post->post_name}: FAILED " . $saved->get_error_message() . "\n";

        return 0;
    }
    echo "#{$post->ID} {$post->post_type}/{$post->post_name}: $excerpt\n";

    return 1;
}

$filled = 0;
foreach (PAGES as $slug => $text) {
    $page = get_page_by_path($slug);
    $page ? $filled += jos_fill($page, $text) : print("page/$slug: not found\n");
}
foreach (VACATURES as $slug => $text) {
    $post = get_posts(['post_type' => 'vacature', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1]);
    $post ? $filled += jos_fill($post[0], $text) : print("vacature/$slug: not found\n");
}
foreach (get_posts(['post_type' => 'case', 'post_status' => 'publish', 'numberposts' => -1]) as $case) {
    $client = trim((string) get_post_meta($case->ID, JOSWORLD_CORE_META_PREFIX . 'client', true));
    $title = rtrim($case->post_title, '.!?');
    $filled += jos_fill($case, $client === '' ? "$title. Een case van Jos." : "$title. Zo pakte Jos het aan voor $client.");
}
foreach (get_posts(['post_type' => 'team', 'post_status' => 'publish', 'numberposts' => -1]) as $member) {
    $filled += jos_fill($member, jos_trim((string) get_post_meta($member->ID, JOSWORLD_CORE_META_PREFIX . 'bio', true)));
}

echo $filled ? "done ($filled written)\n" : "already done, nothing to write\n";
