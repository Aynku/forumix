<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
/**
 * Captcha-Partial – baut Verhaltens-Token + optionales Mathe-CAPTCHA in die Form ein.
 *
 * Vor dem Include setzen:
 *   $captchaAction = 'login' | 'register' | 'forgot' | ...
 *   (optional) $captchaForce = true;  // CAPTCHA immer zeigen
 */
$captchaAction = $captchaAction ?? 'generic';
$captchaForce  = $captchaForce ?? false;
$_showCaptcha  = $captchaForce || captcha_required($captchaAction);
$_behavior     = behavior_token();
$_captcha      = $_showCaptcha ? captcha_generate() : null;
?>

<!-- Verhaltens-Token (versteckt, immer aktiv) -->
<input type="hidden" name="_bt" value="<?= e($_behavior) ?>">

<?php if ($_showCaptcha): ?>
  <div class="form-row captcha-row">
    <label>
      🤖 Sicherheitsfrage:
      <strong style="font-size:18px;font-family:var(--font-mono)"><?= e($_captcha['question']) ?> = ?</strong>
    </label>
    <input type="number" name="_captcha_answer" required autocomplete="off"
           inputmode="numeric" pattern="[0-9-]*" min="-99" max="99"
           style="max-width:120px;text-align:center;font-size:18px">
    <input type="hidden" name="_captcha_token" value="<?= e($_captcha['token']) ?>">
    <div class="form-hint">
      Diese Frage erscheint, weil zu viele fehlgeschlagene Versuche von deiner IP kamen.
      Sie verschwindet nach erfolgreicher Aktion.
    </div>
  </div>
<?php endif; ?>
