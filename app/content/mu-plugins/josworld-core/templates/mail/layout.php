<?php

/**
 * The JOS mail layout: lemon background, the logo, the message on a white
 * card. Tables and inline styles; the <style> block only reaches into the
 * message itself, whose markup comes from the sending plugin.
 *
 * @var array{content: string, subject: string, logo: string} $args
 */

defined('ABSPATH') || exit;

$josworld_text = 'color: #35070c; font-family: Helvetica, Arial, sans-serif;';
$josworld_home = home_url('/');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($args['subject']); ?></title>
    <style>
        .jos-message a { color: #35070c; }
        /* FluentForms' {all_data} table is width="600", wider than the card. */
        .jos-message table.ff_all_data { width: 100% !important; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #fff794;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
    bgcolor="#fff794" style="background-color: #fff794;">
    <tr>
        <td align="center" style="padding: 40px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                style="max-width: 600px;">
                <tr>
                    <td style="padding: 0 0 24px;">
                        <a href="<?php echo esc_url($josworld_home); ?>">
                            <img src="<?php echo esc_url($args['logo']); ?>" width="92" alt="JOS"
                                style="display: block; width: 92px; height: auto; border: 0;">
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="jos-message" bgcolor="#ffffff"
                        style="background-color: #ffffff; padding: 40px; font-size: 16px; line-height: 1.55;
                        word-break: break-word; overflow-wrap: anywhere;
                        <?php echo esc_attr($josworld_text); ?>">
                        <?php
                        // The message itself: escaped plain text, or a plugin's own HTML.
                        echo $args['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </td>
                </tr>
                <tr>
                    <td align="center"
                        style="padding: 24px 0 0; font-size: 13px; <?php echo esc_attr($josworld_text); ?>">
                        JOS · <a href="<?php echo esc_url($josworld_home); ?>" style="color: #35070c;">jos.agency</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
