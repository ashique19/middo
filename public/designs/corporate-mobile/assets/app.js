(() => {
  const SCREENS = {
    login: {
      title: 'Login',
      lede: 'Secure corporate sign-in with Middo branding and a clear path into the office lunch workspace.',
      points: [
        'Email + password fields with Middo focus states',
        'Primary CTA lands on Corporate Home',
        'Secondary path to forgot password / registration',
      ],
      tabs: false,
    },
    home: {
      title: 'Home Dashboard',
      lede: 'Corporate command center: KPIs, next meal, upcoming schedules, wallet balance, and quick tools.',
      points: [
        'Browse Menu hero CTA for same-day / scheduled ordering',
        'Active orders, next meal, and monthly spend at a glance',
        'Quick tools: Add Money, Support, History, Return Middo Box',
      ],
      tabs: true,
    },
    menu: {
      title: 'Browse Menu',
      lede: 'B2B menu discovery with filters and meal cards that open checkout.',
      points: [
        'Category chips for thalis, light, veg, protein',
        'Meal cards show photo, description, and BDT price',
        'Tap Order to open multi-date checkout',
      ],
      tabs: true,
    },
    checkout: {
      title: 'Checkout',
      lede: 'Schedule quantities across Dhaka delivery dates and pay from Middo Balance.',
      points: [
        'Same-day cutoff countdown for today’s lunch run',
        'Date × quantity grid for bulk office orders',
        'Sticky confirm CTA with delivery window summary',
      ],
      tabs: false,
    },
    schedule: {
      title: 'Scheduled Lunches',
      lede: 'Upcoming calendar of corporate orders with track and edit actions.',
      points: [
        'Status + payment badges per order',
        'Track opens live logistics timeline',
        'Place New Order returns to menu',
      ],
      tabs: true,
    },
    track: {
      title: 'Track Order',
      lede: 'Live Middo Box logistics from kitchen acceptance to desk-side delivery.',
      points: [
        'Current status and order total header',
        'Event timeline with performer context',
        'Report issue deep-links into support thread',
      ],
      tabs: false,
    },
    history: {
      title: 'Order History',
      lede: 'Past office lunches for reordering and feedback in the current billing cycle.',
      points: [
        'Delivered state with muted treatment',
        'One-tap reorder into menu',
        'Feedback / complaint entry point',
      ],
      tabs: false,
    },
    wallet: {
      title: 'Wallet & Profile',
      lede: 'Middo Balance top-up and company account details for prepaid lunch ops.',
      points: [
        'Balance hero with Add Money CTA',
        'Quick amounts + custom top-up field',
        'Company, area, and contact summary',
      ],
      tabs: true,
    },
    support: {
      title: 'Complaint / Support',
      lede: 'Order-scoped support thread for missing items, quality, or delivery issues.',
      points: [
        'Order context chip (date, meal, id)',
        'Chat bubbles for corporate ↔ Middo Support',
        'Composer for follow-up messages',
      ],
      tabs: false,
    },
  };

  const TAB_SCREENS = new Set(['home', 'menu', 'schedule', 'wallet']);

  let platform = 'ios';
  let screen = 'login';

  const iosDevice = document.getElementById('device-ios');
  const androidDevice = document.getElementById('device-android');
  const iosRoot = document.getElementById('ios-screens');
  const androidRoot = document.getElementById('android-screens');
  const iosTabbar = document.getElementById('ios-tabbar');
  const androidTabbar = document.getElementById('android-tabbar');
  const btnIos = document.getElementById('btn-ios');
  const btnAndroid = document.getElementById('btn-android');
  const screenNav = document.getElementById('screen-nav');
  const specTitle = document.getElementById('spec-title');
  const specLede = document.getElementById('spec-lede');
  const specList = document.getElementById('spec-list');
  const platformNote = document.getElementById('platform-note');

  function cloneScreens(target) {
    target.innerHTML = '';
    Object.keys(SCREENS).forEach((key) => {
      const tpl = document.getElementById(`tpl-${key}`);
      if (!tpl) return;
      target.appendChild(tpl.content.cloneNode(true));
    });
  }

  function setPlatform(next) {
    platform = next;
    btnIos.setAttribute('aria-pressed', String(platform === 'ios'));
    btnAndroid.setAttribute('aria-pressed', String(platform === 'android'));
    iosDevice.classList.toggle('hidden-platform', platform !== 'ios');
    androidDevice.classList.toggle('hidden-platform', platform !== 'android');
    platformNote.textContent =
      platform === 'ios'
        ? 'iOS: Dynamic Island, floating glass tab bar, large titles, home indicator.'
        : 'Android: Material status bar, edge-to-edge bottom nav with active pill, gesture bar.';
  }

  function updateSpec() {
    const meta = SCREENS[screen];
    if (!meta) return;
    specTitle.textContent = meta.title;
    specLede.textContent = meta.lede;
    specList.innerHTML = meta.points.map((p) => `<li>${p}</li>`).join('');
  }

  function syncTabs(tabbar) {
    if (!tabbar) return;
    const show = SCREENS[screen]?.tabs;
    tabbar.hidden = !show;
    tabbar.querySelectorAll('.tab').forEach((tab) => {
      tab.classList.toggle('active', tab.dataset.go === screen);
    });
  }

  function showScreen(root, name) {
    root.querySelectorAll('.screen').forEach((el) => {
      el.classList.toggle('active', el.dataset.screen === name);
    });
  }

  function setScreen(name) {
    if (!SCREENS[name]) return;
    screen = name;
    showScreen(iosRoot, screen);
    showScreen(androidRoot, screen);
    syncTabs(iosTabbar);
    syncTabs(androidTabbar);
    screenNav.querySelectorAll('button').forEach((btn) => {
      btn.setAttribute('aria-current', String(btn.dataset.screen === screen));
    });
    updateSpec();
  }

  function bindNav(root) {
    root.addEventListener('click', (e) => {
      const go = e.target.closest('[data-go]');
      if (!go) return;
      e.preventDefault();
      setScreen(go.dataset.go);
    });
  }

  cloneScreens(iosRoot);
  cloneScreens(androidRoot);
  bindNav(iosRoot);
  bindNav(androidRoot);
  bindNav(iosTabbar);
  bindNav(androidTabbar);

  btnIos.addEventListener('click', () => setPlatform('ios'));
  btnAndroid.addEventListener('click', () => setPlatform('android'));

  screenNav.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-screen]');
    if (!btn) return;
    setScreen(btn.dataset.screen);
  });

  // Amount option toggles (visual only)
  document.addEventListener('click', (e) => {
    const opt = e.target.closest('.amount-opt');
    if (!opt) return;
    const grid = opt.parentElement;
    grid.querySelectorAll('.amount-opt').forEach((el) => el.classList.remove('active'));
    opt.classList.add('active');
  });

  document.addEventListener('click', (e) => {
    const chip = e.target.closest('.filter-row .chip');
    if (!chip) return;
    const row = chip.parentElement;
    row.querySelectorAll('.chip').forEach((el) => el.classList.remove('active'));
    chip.classList.add('active');
  });

  document.addEventListener('click', (e) => {
    const cell = e.target.closest('.date-cell');
    if (!cell) return;
    cell.classList.toggle('active');
  });

  setPlatform('ios');
  setScreen('login');

  // Expose for debugging
  window.MiddoCorporateUI = { setScreen, setPlatform, TAB_SCREENS };
})();
