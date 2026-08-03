/**
 * Theme ESLint flat config (gate: FR-6).
 *
 * Correctness rules ONLY: @eslint/js `recommended`. No formatting/stylistic
 * rules and no framework preset (@wordpress/eslint-plugin deliberately NOT
 * adopted) — formatting stays with the editor. See specs/wp-gate-harness.
 * Config files (*.config.js) are deliberately outside the lint scope.
 */
import { defineConfig } from 'eslint/config';
import js from '@eslint/js';
import globals from 'globals';

export default defineConfig([
  {
    ignores: ['dist/**'],
  },
  {
    files: ['src/**/*.js'],
    extends: [js.configs.recommended],
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
  },
]);
