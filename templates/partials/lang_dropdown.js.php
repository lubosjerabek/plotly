<?php

/* Language dropdown — shared JS. Include inside a <script> block. */ ?>
  function toggleLangDropdown(e) {
    e.stopPropagation();
    const dd = document.getElementById('langDropdown');
    const open = dd.classList.toggle('is-open');
    dd.querySelector('.lang-dropdown__btn').setAttribute('aria-expanded', open);
  }
  document.addEventListener('click', () => {
    const dd = document.getElementById('langDropdown');
    if (dd && dd.classList.contains('is-open')) {
      dd.classList.remove('is-open');
      dd.querySelector('.lang-dropdown__btn').setAttribute('aria-expanded', 'false');
    }
  });
