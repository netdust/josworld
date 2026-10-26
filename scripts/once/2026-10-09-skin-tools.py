#!/usr/bin/env python3
"""
docs/tools/*.html -> app/content/themes/josworld/tools/<slug>.html.php, in the Jos style (opened directly the file exits).

The two tools were built with a THEMING LAYER (one :root block of tokens): this swaps those tokens for the
Jos brand (theme.josworld.less section 1), self-hosts Inter + Fraunces instead of calling Google Fonts, gives
the display type Fraunces' real weight, and adds the Jos bar and footer (hidden in print). The tools' own
markup and scripts are untouched. Re-run it after the source files change:

    python3 scripts/once/2026-10-09-skin-tools.py
"""
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCES = {
    'campagnestart.html': 'campagnestart',
    'faire-aanbestedingsscan - V3.html': 'faire-aanbestedingsscan',
}
OUT = ROOT / 'app/content/themes/josworld/tools'
THEME_URL = '/content/themes/josworld'
GUARD = "<?php defined('ABSPATH') || exit; ?>\n"  # PHP eats the newline after ?>: the page is unchanged

TOKENS = {  # Figma "Jos …" variables, as in less/theme.josworld.less
    '--paper': '#FCFCF7',      # Jos Beige
    '--ink': '#35070C',        # Jos Brown
    '--ink-soft': '#866A6D',   # Jos Brown 60%
    '--line': '#EBE6E7',       # Jos Brown 10%
    '--accent': '#E60B66',     # Jos Raspberry
    '--accent-soft': '#FCE5EE',  # Raspberry tint (as in the Backlashplanner workbook)
}

FONT_FACES = f"""/* Self-hosted: the site's own Inter and Fraunces, no call to Google. */
@font-face{{font-family:'Inter';font-weight:400;font-style:normal;font-display:swap;src:url({THEME_URL}/fonts/inter-400.woff2) format('woff2')}}
@font-face{{font-family:'Inter';font-weight:600;font-style:normal;font-display:swap;src:url({THEME_URL}/fonts/inter-600.woff2) format('woff2')}}
@font-face{{font-family:'Fraunces';font-weight:300;font-style:normal;font-display:swap;src:url({THEME_URL}/fonts/fraunces-300.woff2) format('woff2')}}
@font-face{{font-family:'Fraunces';font-weight:400;font-style:normal;font-display:swap;src:url({THEME_URL}/fonts/fraunces-400.woff2) format('woff2')}}
"""

BAR_CSS = """
/* Jos bar + footer */
.jw-bar{display:flex;align-items:center;justify-content:space-between;max-width:var(--maxw);margin:0 auto;padding:20px 20px 0}
.jw-bar img{display:block;height:37px;width:auto}
.jw-bar a{color:var(--ink-soft);font-size:14px;text-decoration:none}
.jw-bar a:hover{color:var(--ink)}
h1{line-height:1.12}
.jw-foot{max-width:var(--maxw);margin:0 auto;padding:0 20px 40px;font-size:13px;color:var(--ink-soft)}
.jw-foot a{color:inherit}
@media print{.jw-bar,.jw-foot{display:none}}
"""

BAR_HTML = f"""<header class="jw-bar">
  <a href="/gratis-aanbod/" aria-label="Jos: naar alle gratis tools"><img src="{THEME_URL}/images/logo.png" alt="Jos" width="56" height="37"></a>
  <a href="/gratis-aanbod/">&larr; Alle gratis tools</a>
</header>
"""

FOOT_HTML = '<footer class="jw-foot">Een gratis tool van Jos &middot; <a href="/">jos.agency</a></footer>\n'


def skin(html: str) -> str:
    # Google Fonts out.
    html = re.sub(r'<link[^>]*fonts\.(?:googleapis|gstatic)\.com[^>]*>\s*', '', html)

    # Tokens in, fonts in.
    for name, value in TOKENS.items():
        html, n = re.subn(rf'({re.escape(name)}:)#[0-9A-Fa-f]{{6}}', rf'\g<1>{value}', html, count=1)
        assert n == 1, f'token {name} not found'
    html, n = re.subn(r"--display:[^;]+;", "--display:'Fraunces',Georgia,'Times New Roman',serif;", html, count=1)
    assert n == 1, 'display font not found'
    html = html.replace(":root{", FONT_FACES + ":root{", 1)

    # Fraunces is loaded at 300/400: a rule that sets the display face gets the regular weight, not a faked bold.
    def regular(match: re.Match) -> str:
        rule = match.group(0)
        return re.sub(r'font-weight:\s*\d+', 'font-weight:400', rule) if 'var(--display)' in rule else rule

    html = re.sub(r'\{[^{}]*\}', regular, html)

    # Bar, footer, and keep the (token-gated) tools out of search: the gate is the point of the form.
    html = html.replace('</style>', BAR_CSS + '</style>', 1)
    html = html.replace('<meta name="viewport"', '<meta name="robots" content="noindex">\n<meta name="viewport"', 1)
    html = re.sub(r'(<body[^>]*>\s*)', r'\1' + BAR_HTML, html, count=1)
    html = html.replace('<script', FOOT_HTML + '<script', 1)
    return html


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    for source, slug in SOURCES.items():
        skinned = skin((ROOT / 'docs/tools' / source).read_text(encoding='utf-8'))
        (OUT / f'{slug}.html.php').write_text(GUARD + skinned, encoding='utf-8')
        print(f'{source} -> app/content/themes/josworld/tools/{slug}.html.php ({len(skinned)} bytes)')


if __name__ == '__main__':
    main()
