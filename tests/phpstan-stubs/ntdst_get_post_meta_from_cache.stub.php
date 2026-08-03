<?php

/**
 * PHPStan-only stub for the `function_exists()`-guarded global function
 * declared in
 * app/content/themes/josworld/services/yootheme/ntdst-cached-meta-accessor.php
 * (T05's cached-meta seam, lazily required from `FieldResolver::resolve()`).
 *
 * WHY THIS STUB EXISTS: the real declaration must stay guarded by
 * `function_exists()` and split into its own lazily-`require_once`d file —
 * Brain Monkey's interception layer (Patchwork) throws `DefinedTooEarly` if
 * the function's defining file is included before Patchwork bootstraps (see
 * that file's docblock for the empirically-verified detail). PHPStan,
 * however, does not perform conditional function-declaration reflection and
 * does not follow a `require_once` that only executes inside a method body,
 * so the real declaration is invisible to static analysis and every call
 * site — including the resolver's own — fails with `function.notFound`.
 * This stub is loaded via `phpstan.neon`'s `scanFiles` for signature
 * discovery ONLY; it declares no body and is never included at runtime.
 *
 * Lives under tests/ (not app/) because it is dev tooling for static
 * analysis, not code shipped to the site; tests/ is already an explicit
 * allowlist entry in this project's otherwise dotfile-blocking .gitignore.
 *
 * Keep this signature in exact sync with the real declaration.
 *
 * @return array<string, mixed>
 */
function ntdst_get_post_meta_from_cache(int $postId): array
{
}
