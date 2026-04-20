(function () {
  console.log('Gravity API Trap: Script loaded successfully');

  function getParam(name) {
    const url = new URL(window.location.href);
    return (url.searchParams.get(name) || '').trim();
  }

  function looksPaid(m) {
    m = (m || '').toLowerCase();
    return (
      ['cpc','ppc','sem','paid','paid_search','paidsearch','paid-social','paidsocial','paid social','display','banner','programmatic']
        .includes(m) ||
      m.includes('paid') || m.includes('cpc') || m.includes('ppc')
    );
  }

  function deriveMarketSource() {
    const utm_source   = getParam('utm_source').toLowerCase();
    const utm_medium   = getParam('utm_medium').toLowerCase();
    const utm_campaign = getParam('utm_campaign').toLowerCase();

    const gclid      = getParam('gclid');
    const fbclid     = getParam('fbclid');
    const gad        = getParam('gad');
    const gad_source = getParam('gad_source').toLowerCase();
    const display    = getParam('display').toLowerCase();

    const isGoogle    = utm_source.includes('google') || utm_source === 'g';
    const isBing      = utm_source.includes('bing') || utm_source.includes('microsoft');
    const isFacebook  = utm_source.includes('facebook') || utm_source === 'fb';
    const isInstagram = utm_source.includes('instagram');

    const isEmail   = ['email','newsletter','e-mail'].includes(utm_medium);
    const isOrganic = ['organic','seo'].includes(utm_medium);
    const isDisplay =
      ['display','banner','programmatic'].includes(utm_medium) ||
      utm_campaign.includes('display') ||
      display === '1' || display === 'true';

    const isLocalIntent = utm_campaign.includes('local') || utm_medium.includes('local');

    const hasGoogleClick = !!(gclid || gad || gad_source);
    const hasMetaClick   = !!fbclid;

    if (hasGoogleClick) {
      if (isDisplay) return 'WEB - Google Display';
      if (isLocalIntent) return 'WEB - Google Local';
      return 'WEB - Google Paid';
    }

    if (hasMetaClick) {
      if (isOrganic) return 'WEB - Organic Social';
      if (looksPaid(utm_medium)) return 'WEB - Facebook Paid';
      return 'WEB - Facebook';
    }

    if (isEmail) return 'Email';

    if (looksPaid(utm_medium) && (utm_medium.includes('cpc') || utm_medium.includes('ppc') || utm_medium.includes('sem') || utm_medium.includes('paid_search') || utm_medium.includes('paidsearch'))) {
      if (isGoogle) return 'WEB - Google Paid';
      if (isBing) return 'WEB - Bing Search';
      return 'WEB - Paid Search';
    }

    if (looksPaid(utm_medium) && (utm_medium.includes('social') || isFacebook || isInstagram)) {
      if (isFacebook || isInstagram) return 'WEB - Facebook Paid';
      return 'WEB - Paid Social';
    }

    const socialSources = ['facebook','instagram','linkedin','tiktok','twitter','x','pinterest','youtube'];
    if (socialSources.includes(utm_source)) return 'WEB - Organic Social';

    if (isOrganic) {
      const utm_id = getParam('utm_id').toLowerCase();
      const isLocalListing =
        utm_source === 'local_listing' ||
        utm_source.includes('local_listing') ||
        utm_id === 'gbp';

      if (isLocalListing) return 'WEB - Organic Search';
      if (isGoogle) return 'WEB - Google Search';
      if (isBing) return 'WEB - Bing Search';
      return 'WEB - Organic Search';
    }

    if (isDisplay) {
      if (isGoogle) return 'WEB - Google Display';
      return 'WEB - 3rd Party Display';
    }

    if (getParam('utm_source') || getParam('utm_medium') || getParam('utm_campaign')) return 'Internet';

    return 'WEB - Company Website';
  }

  function setInputValue(input, value) {
    if (!input || value === undefined || value === null) return;
    // Only set non-empty values to avoid interfering with required field validation
    if (value !== '') {
      console.log('Gravity API Trap: Setting input', input.id || input.name, 'to value:', value);
      input.value = value;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  // Scan a form for a dropdown whose options include employment-related values,
  // so no form ID needs to be hardcoded.
  function findEmploymentDropdown(formEl) {
    const selects = formEl.querySelectorAll('select');
    for (const sel of selects) {
      for (const opt of sel.options) {
        const text = (opt.text || '').toLowerCase();
        const val  = (opt.value || '').toLowerCase();
        if (text.includes('employ') || text.includes('job') ||
            val.includes('employ')  || val.includes('job')) {
          return sel;
        }
      }
    }
    return null;
  }

  function findNoTriggerInput(formEl) {
    const candidates = formEl.querySelectorAll('input[type="text"], input[type="hidden"]');
    for (const inp of candidates) {
      const field = inp.closest('.gfield');
      const label = field ? (field.querySelector('label.gfield_label')?.textContent || '').toLowerCase() : '';
      const name  = (inp.name || '').toLowerCase();
      const id    = (inp.id || '').toLowerCase();

      if (label.includes('notrigger') || label.includes('no trigger') || label.includes('suppress') || label.includes('trigger') ||
          name.includes('notrigger')  || name.includes('trigger')  || id.includes('notrigger')) {
        return inp;
      }
    }
    return null;
  }

  function applyNoTriggerRule(formEl) {
    const dropdown = findEmploymentDropdown(formEl);
    if (!dropdown) return;

    const noTrigger = findNoTriggerInput(formEl);
    if (!noTrigger) return;

    const selectedValue = (dropdown.value || '').toLowerCase().trim();
    const selectedText  = (dropdown.options[dropdown.selectedIndex]?.text || '').toLowerCase().trim();

    const isEmployment =
      selectedValue === 'employment' || selectedValue.includes('employ') || selectedValue.includes('job') ||
      selectedText  === 'employment' || selectedText.includes('employ')  || selectedText.includes('job');

    if (isEmployment) {
      console.log('Gravity API Trap: Employment selected — setting NoTrigger to True');
      noTrigger.value = 'True';
      noTrigger.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
      noTrigger.value = '';
      noTrigger.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  // Bind all employment-suppression listeners for a form.
  // Runs independently of UTM data so the rule always applies.
  function bindNoTriggerListeners(form) {
    const dropdown = findEmploymentDropdown(form);
    if (!dropdown) return;

    const noTrigger = findNoTriggerInput(form);
    if (!noTrigger) return;

    // Initialize field to empty on setup
    noTrigger.value = '';
    console.log('Gravity API Trap: NoTrigger initialized for', dropdown.id || dropdown.name);

    // Apply rule now, then again after short delays for late-rendering fields
    applyNoTriggerRule(form);
    setTimeout(function () { applyNoTriggerRule(form); }, 500);
    setTimeout(function () { applyNoTriggerRule(form); }, 2000);

    // Only attach listeners once per dropdown
    if (dropdown.dataset.mwNoTriggerBound) return;

    // Native change listener
    dropdown.addEventListener('change', function () {
      applyNoTriggerRule(form);
    });

    // jQuery listener — Gravity Forms triggers jQuery change events internally
    if (typeof jQuery !== 'undefined') {
      jQuery(dropdown).on('change', function () {
        applyNoTriggerRule(form);
      });
    }

    // Watch for programmatic value changes on the dropdown (e.g. conditional logic)
    const observer = new MutationObserver(function () {
      applyNoTriggerRule(form);
    });
    observer.observe(dropdown, { attributes: true });

    dropdown.dataset.mwNoTriggerBound = '1';
    console.log('Gravity API Trap: NoTrigger listeners bound for', dropdown.id || dropdown.name);
  }

  function setUTMValues(form) {
    const utmSource   = getParam('utm_source');
    const utmMedium   = getParam('utm_medium');
    const utmCampaign = getParam('utm_campaign');
    const utmId       = getParam('utm_id');
    const gclid       = getParam('gclid');
    const fbclid      = getParam('fbclid');
    const gad         = getParam('gad');
    const gadSource   = getParam('gad_source');
    const display     = getParam('display');

    const hasTrackingData = utmSource || utmMedium || utmCampaign || utmId || gclid || fbclid || gad || gadSource || display;
    if (!hasTrackingData) return;

    try {
      const derived = deriveMarketSource();
      console.log('Gravity API Trap: Derived MarketSource:', derived || '(none)');

      const labels = form.querySelectorAll('label.gfield_label');
      labels.forEach(function (label) {
        try {
          const inputId = label.getAttribute('for');
          if (!inputId) return;

          const input = form.querySelector('#' + CSS.escape(inputId));
          if (!input) return;

          const key = label.textContent.trim().toLowerCase();

          if      (key === 'utmsource')                           setInputValue(input, utmSource);
          else if (key === 'utmmedium' || key === 'utmedium')    setInputValue(input, utmMedium);
          else if (key === 'utmcampaign')                         setInputValue(input, utmCampaign);
          else if (key === 'utmid')                               setInputValue(input, utmId);
          else if (key === 'gclid')                               setInputValue(input, gclid);
          else if (key === 'fbclid')                              setInputValue(input, fbclid);
          else if (key === 'gad')                                 setInputValue(input, gad);
          else if (key === 'gad_source')                          setInputValue(input, gadSource);
          else if (key === 'display')                             setInputValue(input, display);
          else if (key === 'marketsource' || key === 'market source') setInputValue(input, derived);
        } catch (e) {
          console.warn('Error setting field value:', e);
        }
      });

      // Also try CSS class as a secondary targeting method
      if (derived) {
        const msField = form.querySelector('.mw-marketsource input');
        if (msField) setInputValue(msField, derived);
      }
    } catch (e) {
      console.warn('Error processing form UTM values:', e);
    }
  }

  function processAllForms() {
    console.log('Gravity API Trap: processAllForms called');
    const forms = document.querySelectorAll('.gform_wrapper form');
    console.log('Gravity API Trap: Found forms:', forms.length);

    forms.forEach(function (form, index) {
      console.log('Gravity API Trap: Processing form', index + 1);
      bindNoTriggerListeners(form); // always runs, regardless of UTM data
      setUTMValues(form);           // only runs when tracking params are present
    });
  }

  // Gravity Forms AJAX events
  document.addEventListener('gform_post_render', function () {
    console.log('Gravity API Trap: gform_post_render fired');
    setTimeout(processAllForms, 100);
  });

  document.addEventListener('gform_post_conditional_logic', function () {
    console.log('Gravity API Trap: gform_post_conditional_logic fired');
    setTimeout(processAllForms, 100);
  });

  // Fallback: poll until forms appear, then stop
  let checkInterval = setInterval(function () {
    if (document.querySelectorAll('.gform_wrapper form').length > 0) {
      console.log('Gravity API Trap: Found forms via interval check');
      processAllForms();
      clearInterval(checkInterval);
    }
  }, 1000);

  setTimeout(function () {
    clearInterval(checkInterval);
  }, 10000);

})();
