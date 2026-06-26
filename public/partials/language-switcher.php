<!-- ══ LANGUAGE SWITCHER — Google Translate ══ -->
<style>
/* Suppress Google's ugly top bar */
.goog-te-banner-frame{display:none!important}
.goog-te-menu-value:hover{text-decoration:none!important}
body{top:0!important;position:static!important}
.skiptranslate{display:none!important}
iframe.skiptranslate{display:none!important}

/* Our button */
#fp-lang-btn{
  position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
  background:#111;color:#fff;
  border:none;border-radius:999px;
  padding:.5rem 1rem .5rem .75rem;
  display:flex;align-items:center;gap:.45rem;
  font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.25);
  transition:background .2s,transform .2s;user-select:none;
}
#fp-lang-btn:hover{background:#333;transform:translateY(-2px)}
#fp-lang-btn svg{width:15px;height:15px;flex-shrink:0}

/* Dropdown */
#fp-lang-menu{
  position:fixed;bottom:5rem;right:1.5rem;z-index:9999;
  background:#fff;border:1px solid #e5e7eb;border-radius:14px;
  box-shadow:0 8px 40px rgba(0,0,0,.15);
  padding:.5rem;width:268px;max-height:440px;overflow-y:auto;
  display:none;flex-direction:column;
}
#fp-lang-menu.open{display:flex}
#fp-lang-menu-search{
  border:1.5px solid #e5e7eb;border-radius:8px;
  padding:.45rem .7rem;font-size:.8rem;width:100%;
  outline:none;font-family:inherit;margin-bottom:.3rem;
  transition:border-color .15s;box-sizing:border-box;
}
#fp-lang-menu-search:focus{border-color:#111}
.fp-lang-sep{font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af;padding:.5rem .7rem .2rem;pointer-events:none}
.fp-lang-item{
  padding:.42rem .7rem;border-radius:8px;
  font-size:.8rem;font-family:inherit;cursor:pointer;color:#374151;
  display:flex;align-items:center;gap:.5rem;transition:background .1s;
}
.fp-lang-item:hover{background:#f3f4f6;color:#111}
.fp-lang-item.active{background:#111;color:#fff;font-weight:600}
</style>

<!-- Hidden GT container -->
<div id="google_translate_element" style="display:none;visibility:hidden;position:absolute;left:-9999px"></div>

<!-- Button -->
<button id="fp-lang-btn" onclick="fpToggleMenu()" aria-label="Change language">
  <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
    <circle cx="12" cy="12" r="10"/>
    <path stroke-linecap="round" d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/>
  </svg>
  <span id="fp-lang-label">Language</span>
</button>

<!-- Dropdown -->
<div id="fp-lang-menu" role="listbox">
  <input id="fp-lang-menu-search" type="text" placeholder="Search language…" oninput="fpSearch(this.value)" autocomplete="off">
  <div id="fp-lang-list"></div>
</div>

<script>
var FP_LANGS=[
  /* ── Indian ── */
  {c:'hi',n:'हिन्दी',e:'Hindi'},
  {c:'mr',n:'मराठी',e:'Marathi'},
  {c:'bn',n:'বাংলা',e:'Bengali'},
  {c:'te',n:'తెలుగు',e:'Telugu'},
  {c:'ta',n:'தமிழ்',e:'Tamil'},
  {c:'gu',n:'ગુજરાતી',e:'Gujarati'},
  {c:'kn',n:'ಕನ್ನಡ',e:'Kannada'},
  {c:'ml',n:'മലയാളം',e:'Malayalam'},
  {c:'pa',n:'ਪੰਜਾਬੀ',e:'Punjabi'},
  {c:'or',n:'ଓଡ଼ିଆ',e:'Odia'},
  {c:'as',n:'অসমীয়া',e:'Assamese'},
  {c:'ur',n:'اردو',e:'Urdu'},
  {c:'ne',n:'नेपाली',e:'Nepali'},
  {c:'si',n:'සිංහල',e:'Sinhala'},
  /* ── International ── */
  {c:'en',n:'English',e:'English'},
  {c:'ar',n:'العربية',e:'Arabic'},
  {c:'zh-CN',n:'中文 (简体)',e:'Chinese Simplified'},
  {c:'zh-TW',n:'中文 (繁體)',e:'Chinese Traditional'},
  {c:'fr',n:'Français',e:'French'},
  {c:'de',n:'Deutsch',e:'German'},
  {c:'es',n:'Español',e:'Spanish'},
  {c:'pt',n:'Português',e:'Portuguese'},
  {c:'ru',n:'Русский',e:'Russian'},
  {c:'ja',n:'日本語',e:'Japanese'},
  {c:'ko',n:'한국어',e:'Korean'},
  {c:'it',n:'Italiano',e:'Italian'},
  {c:'tr',n:'Türkçe',e:'Turkish'},
  {c:'id',n:'Bahasa Indonesia',e:'Indonesian'},
  {c:'ms',n:'Bahasa Melayu',e:'Malay'},
  {c:'th',n:'ภาษาไทย',e:'Thai'},
  {c:'vi',n:'Tiếng Việt',e:'Vietnamese'},
  {c:'fa',n:'فارسی',e:'Persian'},
  {c:'pl',n:'Polski',e:'Polish'},
  {c:'nl',n:'Nederlands',e:'Dutch'},
  {c:'uk',n:'Українська',e:'Ukrainian'},
  {c:'sw',n:'Kiswahili',e:'Swahili'},
];

var INDIAN=['hi','mr','bn','te','ta','gu','kn','ml','pa','or','as','ur','ne','si'];
var fpOpen=false;
var fpCurrent='en';

// Read current language from Google Translate cookie
(function(){
  var m=document.cookie.match(/googtrans=\/en\/([^;]+)/);
  if(m&&m[1]){
    fpCurrent=decodeURIComponent(m[1]);
    var l=FP_LANGS.find(function(x){return x.c===fpCurrent;});
    if(l) document.addEventListener('DOMContentLoaded',function(){
      var el=document.getElementById('fp-lang-label');
      if(el) el.textContent=l.e;
    });
  }
})();

function fpToggleMenu(){
  fpOpen=!fpOpen;
  var m=document.getElementById('fp-lang-menu');
  if(fpOpen){
    m.classList.add('open');
    var s=document.getElementById('fp-lang-menu-search');
    s.value='';fpSearch('');
    setTimeout(function(){s.focus();},60);
  } else {
    m.classList.remove('open');
  }
}

function fpSearch(q){
  q=q.toLowerCase().trim();
  var list=document.getElementById('fp-lang-list');
  list.innerHTML='';
  var ind=FP_LANGS.filter(function(l){return INDIAN.indexOf(l.c)>-1;});
  var intl=FP_LANGS.filter(function(l){return INDIAN.indexOf(l.c)===-1;});
  function match(l){return !q||l.n.toLowerCase().includes(q)||l.e.toLowerCase().includes(q)||l.c.toLowerCase().includes(q);}
  var si=ind.filter(match), sx=intl.filter(match);
  if(si.length){
    if(!q){var s=document.createElement('div');s.className='fp-lang-sep';s.textContent='Indian Languages';list.appendChild(s);}
    si.forEach(function(l){list.appendChild(fpItem(l));});
  }
  if(sx.length){
    if(!q){var s2=document.createElement('div');s2.className='fp-lang-sep';s2.textContent='International';list.appendChild(s2);}
    sx.forEach(function(l){list.appendChild(fpItem(l));});
  }
}

function fpItem(l){
  var d=document.createElement('div');
  d.className='fp-lang-item'+(l.c===fpCurrent?' active':'');
  d.innerHTML='<span style="min-width:96px">'+l.n+'</span><span style="color:#9ca3af;font-size:.7rem">'+l.e+'</span>';
  d.onclick=function(){fpSetLang(l.c,l.e);};
  return d;
}

function fpSetLang(code, label){
  fpCurrent=code;
  document.getElementById('fp-lang-label').textContent=label;
  document.getElementById('fp-lang-menu').classList.remove('open');
  fpOpen=false;

  // Set Google Translate cookie then reload — most reliable method
  var domain=location.hostname;
  // Clear old cookie
  document.cookie='googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain='+domain;
  document.cookie='googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
  if(code==='en'){
    // Restore to English — just clear cookie and reload
    location.reload();
    return;
  }
  // Set new language cookie
  document.cookie='googtrans=/en/'+code+'; path=/; domain='+domain;
  document.cookie='googtrans=/en/'+code+'; path=/';
  location.reload();
}

// Close on outside click
document.addEventListener('click',function(e){
  if(!fpOpen) return;
  var btn=document.getElementById('fp-lang-btn');
  var menu=document.getElementById('fp-lang-menu');
  if(btn&&!btn.contains(e.target)&&menu&&!menu.contains(e.target)){
    fpOpen=false;menu.classList.remove('open');
  }
});

// Google Translate init (needed to activate translation on page load)
function googleTranslateElementInit(){
  new google.translate.TranslateElement({
    pageLanguage:'en',
    autoDisplay:false
  },'google_translate_element');
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async defer></script>
