<?php
/**
 * One-off, per environment (staging first, then production), with `wp eval-file`:
 *
 *   wp eval-file scripts/once/2026-10-12-content-fixes.php
 *
 * Placeholder content that went live:
 *  - the contact card said "Thijs Snauwaert, [functie]", thijs@jos.agency and +32 123 123 123 in four YOOtheme templates
 *    and four library layouts, and in the posts built from them: it now carries the real contact the other templates
 *    already use (Goele Van Dam, Projectmanager, goele@jos.agency, +32 2 411 20 54);
 *  - the "© Voornaam Naam" photo credit (library W3, and the Onze Energie case) is removed;
 *  - a team member whose stored layout comment was broken by an editor save (it printed its JSON on the page) gets
 *    the intact template copy of another team member; one with no layout comment at all is left alone.
 * Idempotent; a backup of the YOOtheme option is written first. Run with `wp eval-file` only: kses is off there, so
 * the builder layouts survive the saves. Database content, not code: the staging -> production copy carries it.
 */
if (has_filter('content_save_pre', 'wp_filter_post_kses')) {
    exit("kses is active: run this with `wp eval-file`.\n");
}

const REPLACEMENTS = [
    'Thijs Snauwaert,<br>[functie]' => 'Goele Van Dam,<br>Projectmanager',
    'Thijs Snauwaert,<br />[functie]' => 'Goele Van Dam,<br />Projectmanager',
    'mailto:thijs@jos.agency">thijs@jos.agency' => 'mailto:goele@jos.agency">goele@jos.agency',
    'tel:+32123123123">+32 123 123 123' => 'tel:+3224112054">+32 2 411 20 54',
    // The same links inside a post's stored layout comment, where the quotes are escaped.
    'mailto:thijs@jos.agency\\">thijs@jos.agency' => 'mailto:goele@jos.agency\\">goele@jos.agency',
    'tel:+32123123123\\">+32 123 123 123' => 'tel:+3224112054\\">+32 2 411 20 54',
    '<p>© Voornaam Naam</p>' => '',
];

function jos_replace(string $text): string
{
    return str_replace(array_keys(REPLACEMENTS), array_values(REPLACEMENTS), $text);
}

function jos_walk(mixed $node, int &$changed): mixed
{
    if (is_string($node)) {
        $new = jos_replace($node);
        $changed += $new !== $node ? 1 : 0;

        return $new;
    }
    if (is_array($node)) {
        foreach ($node as $key => $value) {
            $node[$key] = jos_walk($value, $changed);
        }
    }

    return $node;
}

global $wpdb;
$touched = 0;

echo "== YOOtheme templates and library\n";
$stored = get_option('yootheme');
$isJson = is_string($stored);
$option = $isJson ? json_decode($stored, true) : $stored;
if (is_array($option)) {
    $dir = '/data/sites/web/josagency/tmp/backups';
    if (! is_writable($dir)) {
        exit("$dir is not writable: the backup holds the YOOtheme key and must not land in a temp folder.\n");
    }
    $backup = $dir . '/yootheme-option-' . date('Ymd-His') . '.json';
    file_put_contents($backup, $isJson ? $stored : wp_json_encode($option));

    $changed = 0;
    foreach (['templates', 'library'] as $part) {
        $option[$part] = jos_walk($option[$part] ?? [], $changed);
    }
    if ($changed > 0) {
        update_option('yootheme', $isJson ? wp_json_encode($option) : $option);
        $touched += $changed;
    }
    echo "$changed strings changed (backup $backup)\n";
}

echo "== posts built from them\n";
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status <> 'trash' AND post_type NOT IN ('revision', 'nav_menu_item')
    AND (post_content LIKE '%[functie]%' OR post_content LIKE '%Voornaam Naam%'
        OR post_content LIKE '%+32 123 123 123%' OR post_content LIKE '%thijs@jos.agency%')");
foreach ($ids as $id) {
    $content = (string) get_post_field('post_content', $id);
    $new = jos_replace($content);
    if ($new !== $content) {
        $wpdb->update($wpdb->posts, ['post_content' => $new], ['ID' => $id]);
        clean_post_cache((int) $id);
        $touched++;
        echo '#' . $id . ' ' . get_post_type($id) . '/' . get_post_field('post_name', $id) . "\n";
    }
}

echo "== team layout comments\n";
$layoutState = static function (string $content): string {
    if (preg_match('/<!--\s*(\{.*\})\s*-->/s', $content, $m) !== 1) {
        return 'none';
    }

    return json_decode($m[1]) !== null ? 'intact' : 'broken';
};
$team = get_posts(['post_type' => 'team', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID']);
$intact = '';
foreach ($team as $member) {
    $intact = $intact === '' && $layoutState($member->post_content) === 'intact' ? $member->post_content : $intact;
}
foreach ($team as $member) {
    // Only a comment an editor save broke is repaired; a member with no layout comment at all is left as it is.
    if ($intact !== '' && $layoutState($member->post_content) === 'broken') {
        $wpdb->update($wpdb->posts, ['post_content' => $intact], ['ID' => $member->ID]);
        clean_post_cache($member->ID);
        $touched++;
        echo '#' . $member->ID . ' ' . $member->post_name . ": layout comment repaired\n";
    }
}

echo $touched ? "done ($touched changes)\n" : "already done, nothing to change\n";
