import { ddevWp } from './mail';

/**
 * Whether the local site's translation provider answers. The DeepL key lives in .env and can be
 * disabled or out of quota; a test that needs a real translation skips with that reason instead
 * of failing the gate on a key nobody can fix from the code.
 */
let answer: boolean | null = null;

export function translatorAnswers(): boolean {
  answer ??= ddevWp(`try {
      $t = \\ntdst\\baseline\\services\\polylang\\PolylangService::translator();
      echo $t !== null && $t->translate(['Hallo'], 'nl', 'fr') !== [] ? 'ok' : 'no';
    } catch (\\Throwable $e) { echo 'no'; }`).trim() === 'ok';

  return answer;
}

export const noTranslator = 'the translation provider rejects the local key (disabled or out of quota)';
