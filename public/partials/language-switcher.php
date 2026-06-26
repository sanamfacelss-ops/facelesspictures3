<!-- ══ LANGUAGE SWITCHER — Google Translate ══ -->
<style>
/* Hide Google's default toolbar banner */
.goog-te-banner-frame{display:none!important}
body{top:0!important}
.skiptranslate{display:none!important}

/* Our custom button */
#fp-lang-btn{
  position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
  background:#111;color:#fff;
  border:none;border-radius:999px;
  padding:.5rem .9rem;
  display:flex;align-items:center;gap:.4rem;
  font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.22);
  transition:background .2s,transform .2s;
  user-select:none;
}
#fp-lang-btn:hover{background:#333;transform:translateY(-2px)}
#fp-lang-btn svg{width:15px;height:15px;flex-shrink:0}

/* Language dropdown */
#fp-lang-menu{
  position:fixed;bottom:4.5rem;right:1.5rem;z-index:9999;
  background:#fff;border:1px solid #e5e7eb;border-radius:14px;
  box-shadow:0 8px 40px rgba(0,0,0,.14);
  padding:.5rem;
  width:260px;
  max-height:420px;
  overflow-y:auto;
  display:none;
  flex-direction:column;
  gap:1px;
}
#fp-lang-menu.open{display:flex}
#fp-lang-menu-search{
  border:1.5px solid #e5e7eb;border-radius:8px;
  padding:.45rem .7rem;font-size:.8rem;width:100%;
  outline:none;font-family:'DM Sans',sans-serif;
  margin-bottom:.35rem;
  transition:border-color .15s;
}
#fp-lang-menu-search:focus{border-color:#111}
.fp-lang-item{
  padding:.42rem .7rem;border-radius:8px;
  font-size:.8rem;font-family:'DM Sans',sans-serif;
  cursor:pointer;color:#374151;
  display:flex;align-items:center;gap:.5rem;
  transition:background .1s;
  white-space:nowrap;
}
.fp-lang-item:hover{background:#f3f4f6;color:#111}
.fp-lang-item.active{background:#111;color:#fff;font-weight:600}
.fp-lang-sep{font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af;padding:.5rem .7rem .2rem;pointer-events:none}
</style>

<!-- Hidden Google Translate element -->
<div id="google_translate_element" style="display:none"></div>

<!-- Our custom button -->
<button id="fp-lang-btn" onclick="fpLangToggle()" aria-label="Change language">
  <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
    <circle cx="12" cy="12" r="10"/>
    <path stroke-linecap="round" d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/>
  </svg>
  <span id="fp-lang-current">Language</span>
</button>

<!-- Dropdown -->
<div id="fp-lang-menu" role="listbox" aria-label="Select language">
  <input id="fp-lang-menu-search" type="text" placeholder="Search language…" oninput="fpLangSearch(this.value)" autocomplete="off">
  <div id="fp-lang-list"></div>
</div>

<script>
// Language list: Indian languages first, then global
var FP_LANGS = [
  // ── Indian Languages ──
  {code:'hi', label:'हिन्दी',         eng:'Hindi'},
  {code:'mr', label:'मराठी',          eng:'Marathi'},
  {code:'bn', label:'বাংলা',           eng:'Bengali'},
  {code:'te', label:'తెలుగు',          eng:'Telugu'},
  {code:'ta', label:'தமிழ்',           eng:'Tamil'},
  {code:'gu', label:'ગુજરાતી',         eng:'Gujarati'},
  {code:'kn', label:'ಕನ್ನಡ',           eng:'Kannada'},
  {code:'ml', label:'മലയാളം',          eng:'Malayalam'},
  {code:'pa', label:'ਪੰਜਾਬੀ',          eng:'Punjabi'},
  {code:'or', label:'ଓଡ଼ିଆ',            eng:'Odia'},
  {code:'as', label:'অসমীয়া',          eng:'Assamese'},
  {code:'ur', label:'اردو',            eng:'Urdu'},
  {code:'ne', label:'नेपाली',          eng:'Nepali'},
  {code:'si', label:'සිංහල',           eng:'Sinhala'},
  // ── International ──
  {code:'en', label:'English',         eng:'English'},
  {code:'ar', label:'العربية',         eng:'Arabic'},
  {code:'zh-CN', label:'中文 (简体)',   eng:'Chinese Simplified'},
  {code:'zh-TW', label:'中文 (繁體)',   eng:'Chinese Traditional'},
  {code:'fr', label:'Français',        eng:'French'},
  {code:'de', label:'Deutsch',         eng:'German'},
  {code:'es', label:'Español',         eng:'Spanish'},
  {code:'pt', label:'Português',       eng:'Portuguese'},
  {code:'ru', label:'Русский',         eng:'Russian'},
  {code:'ja', label:'日本語',           eng:'Japanese'},
  {code:'ko', label:'한국어',           eng:'Korean'},
  {code:'it', label:'Italiano',        eng:'Italian'},
  {code:'tr', label:'Türkçe',          eng:'Turkish'},
  {code:'id', label:'Bahasa Indonesia',eng:'Indonesian'},
  {code:'ms', label:'Bahasa Melayu',   eng:'Malay'},
  {code:'th', label:'ภาษาไทย',          eng:'Thai'},
  {code:'vi', label:'Tiếng Việt',      eng:'Vietnamese'},
  {code:'fa', label:'فارسی',           eng:'Persian'},
  {code:'pl', label:'Polski',          eng:'Polish'},
  {code:'nl', label:'Nederlands',      eng:'Dutch'},
  {code:'uk', label:'Українська',      eng:'Ukrainian'},
  {code:'sw', label:'Kiswahili',       eng:'Swahili'},
  {code:'af', label:'Afrikaans',       eng:'Afrikaans'},
];

var fpMenuOpen = false;
var fpCurrentLang = 'en';

function fpLangToggle(){
  fpMenuOpen = !fpMenuOpen;
  var menu = document.getElementById('fp-lang-menu');
  if(fpMenuOpen){
    menu.classList.add('open');
    document.getElementById('fp-lang-menu-search').value = '';
    fpLangSearch('');
    setTimeout(function(){ document.getElementById('fp-lang-menu-search').focus(); }, 80);
  } else {
    menu.classList.remove('open');
  }
}

function fpLangSearch(q){
  q = q.toLowerCase().trim();
  var list = document.getElementById('fp-lang-list');
  list.innerHTML = '';

  var indian = FP_LANGS.filter(function(l){ return ['hi','mr','bn','te','ta','gu','kn','ml','pa','or','as','ur','ne','si'].indexOf(l.code) > -1; });
  var intl   = FP_LANGS.filter(function(l){ return ['hi','mr','bn','te','ta','gu','kn','ml','pa','or','as','ur','ne','si'].indexOf(l.code) === -1; });

  function matchLang(l){ return !q || l.label.toLowerCase().includes(q) || l.eng.toLowerCase().includes(q) || l.code.toLowerCase().includes(q); }

  var showIndian = indian.filter(matchLang);
  var showIntl   = intl.filter(matchLang);

  if(showIndian.length){
    if(!q){ var sep=document.createElement('div'); sep.className='fp-lang-sep'; sep.textContent='Indian Languages'; list.appendChild(sep); }
    showIndian.forEach(function(l){ list.appendChild(fpLangItem(l)); });
  }
  if(showIntl.length){
    if(!q){ var sep2=document.createElement('div'); sep2.className='fp-lang-sep'; sep2.textContent='International'; list.appendChild(sep2); }
    showIntl.forEach(function(l){ list.appendChild(fpLangItem(l)); });
  }
}

function fpLangItem(l){
  var el = document.createElement('div');
  el.className = 'fp-lang-item' + (l.code === fpCurrentLang ? ' active' : '');
  el.setAttribute('role','option');
  el.innerHTML = '<span style="min-width:90px">' + l.label + '</span><span style="color:#9ca3af;font-size:.72rem">' + l.eng + '</span>';
  el.onclick = function(){ fpSetLang(l.code, l.eng); };
  return el;
}

function fpSetLang(code, label){
  fpCurrentLang = code;
  document.getElementById('fp-lang-current').textContent = label;
  fpMenuOpen = false;
  document.getElementById('fp-lang-menu').classList.remove('open');

  // Trigger Google Translate
  var sel = document.querySelector('.goog-te-combo');
  if(sel){
    sel.value = code;
    sel.dispatchEvent(new Event('change'));
  } else {
    // GT not loaded yet — store and apply after init
    window.__fpPendingLang = code;
  }
}

// Close on outside click
document.addEventListener('click', function(e){
  if(fpMenuOpen && !document.getElementById('fp-lang-btn').contains(e.target) && !document.getElementById('fp-lang-menu').contains(e.target)){
    fpMenuOpen = false;
    document.getElementById('fp-lang-menu').classList.remove('open');
  }
});

// Google Translate init
function googleTranslateElementInit(){
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    autoDisplay: false,
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE
  }, 'google_translate_element');

  // Apply pending language if user selected before GT loaded
  if(window.__fpPendingLang){
    setTimeout(function(){
      var sel = document.querySelector('.goog-te-combo');
      if(sel){ sel.value = window.__fpPendingLang; sel.dispatchEvent(new Event('change')); }
    }, 500);
  }
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async defer></script>
