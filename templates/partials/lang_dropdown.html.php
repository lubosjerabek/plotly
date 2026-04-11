<?php

/**
 * Language dropdown widget.
 * Requires: $lang (current language code), csrf_field(), t(), htmlspecialchars()
 * Requires lang_dropdown.css.php in <style> and lang_dropdown.js.php in <script>.
 */

?>
<div class="lang-dropdown" id="langDropdown">
  <button type="button" class="lang-dropdown__btn" onclick="toggleLangDropdown(event)" aria-haspopup="true" aria-expanded="false">
    <?= htmlspecialchars(t('lang_' . $lang)) ?>
    <svg class="lang-dropdown__chevron" viewBox="0 0 16 16" aria-hidden="true">
      <path d="M4.427 7.427l3.396 3.396a.25.25 0 0 0 .354 0l3.396-3.396A.25.25 0 0 0 11.396 7H4.604a.25.25 0 0 0-.177.427z"/>
    </svg>
  </button>
  <div class="lang-dropdown__menu" role="menu">
    <?php foreach (['en' => 'English', 'cs' => 'Čeština'] as $code => $label): ?>
    <form method="post" action="/set-lang" class="lang-dropdown__item-form">
        <?= csrf_field() ?>
      <input type="hidden" name="lang" value="<?= $code ?>">
      <button type="submit" class="lang-dropdown__item<?= $lang === $code ? ' is-active' : '' ?>" role="menuitem">
        <span class="lang-dropdown__code"><?= strtoupper($code) ?></span><?= htmlspecialchars($label) ?>
      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>
