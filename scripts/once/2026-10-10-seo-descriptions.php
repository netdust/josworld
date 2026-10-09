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
 * Database content, not code: the staging -> production copy carries it.
 */
const PAGES = [
    'waarom-jos'          => 'Waarom Jos? Anders dan de rest, en dat is nodig. Jos is niet gewoon een consultant. Ontdek hoe wij communicatie aanpakken.',
    'diensten'            => 'Je hebt een verhaal dat ertoe doet. Jos zorgt dat mensen het begrijpen, vertrouwen én gebruiken. Ontdek onze diensten.',
    'samenwerken'         => 'Waar wil je heen? Bij Jos beginnen we niet bij een offerte, maar bij je doel. Ontdek hoe we samenwerken.',
    'ons-werk'            => 'Ons werk: campagnes, merken en content voor overheden, social profit en duurzame ondernemers. Bekijk wat we deden.',
    'team'                => 'Maak kennis met het team van Jos: strategen, projectmanagers en makers die je dichter bij de mensen brengen.',
    'voor-overheden'      => 'Samen krijgen we iedereen mee: burgers, ondernemers en lokale besturen. Jos brengt je overheid of publieke dienst dicht bij de mensen.',
    'voor-social-profit'  => 'Waar jij het voor doet, doet er weer toe. Samen krijgen we meer mensen in beweging: Jos voor social profits en duurzame ondernemers.',
    'blog-nieuws'         => 'Nieuws en verhalen van Jos: over ons werk, ons team en de keuzes die we maken.',
    'gratis-aanbod'       => 'Gratis tools, canvassen en checklists van Jos voor communicatie die werkt.',
    'jobs-stages'         => 'Ben jij onze nieuwe collega of stagiair? Bekijk de vacatures en stageplekken bij Jos.',
    'contact'             => 'Eens afspreken met Jos? Plan een sparmoment met Goele of bel ons. We zijn benieuwd naar je project!',
    'privacy-cookie-policy' => 'Privacy- en cookiebeleid van Jos: we meten het bezoek met Matomo op onze eigen server, zonder cookies.',
];

const VACATURES = [
    'copywriter' => 'Jos zoekt een copywriter die scherpe, heldere teksten schrijft voor overheden, social profits en duurzame ondernemers.',
];

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
    wp_update_post(['ID' => $post->ID, 'post_excerpt' => $excerpt]);
    echo "#{$post->ID} {$post->post_type}/{$post->post_name}: $excerpt\n";

    return 1;
}

$filled = 0;
foreach (PAGES as $slug => $text) {
    $page = get_page_by_path($slug);
    $filled += $page ? jos_fill($page, $text) : 0;
}
foreach (VACATURES as $slug => $text) {
    $post = get_posts(['post_type' => 'vacature', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1]);
    $filled += $post ? jos_fill($post[0], $text) : 0;
}
foreach (get_posts(['post_type' => 'case', 'post_status' => 'publish', 'numberposts' => -1]) as $case) {
    $client = trim((string) get_post_meta($case->ID, '_jw_client', true));
    $title = rtrim($case->post_title, '.!?');
    $filled += jos_fill($case, $client === '' ? "$title. Een case van Jos." : "$title. Zo pakte Jos het aan voor $client.");
}
foreach (get_posts(['post_type' => 'team', 'post_status' => 'publish', 'numberposts' => -1]) as $member) {
    $filled += jos_fill($member, jos_trim((string) get_post_meta($member->ID, '_jw_bio', true)));
}

echo $filled ? "done ($filled written)\n" : "already done, nothing to write\n";
