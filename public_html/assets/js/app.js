/* Aramio front-end helpers */
(function(){
  var root=document.documentElement;
  // theme
  function setTheme(t){root.setAttribute('data-theme',t);try{localStorage.setItem('theme',t)}catch(e){}
    document.querySelectorAll('[data-theme-icon]').forEach(function(i){i.className=t==='dark'?'fa-solid fa-sun':'fa-solid fa-moon'})}
  document.querySelectorAll('[data-theme-toggle]').forEach(function(b){b.addEventListener('click',function(){setTheme(root.getAttribute('data-theme')==='dark'?'light':'dark')})});
  setTheme(root.getAttribute('data-theme')||'light');

  // reveal on scroll
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{threshold:.12});
    document.querySelectorAll('.reveal').forEach(function(el){io.observe(el)});
  } else document.querySelectorAll('.reveal').forEach(function(el){el.classList.add('in')});

  // counters
  document.querySelectorAll('[data-count]').forEach(function(el){
    var target=parseInt(el.getAttribute('data-count'),10)||0,done=false;
    function run(){if(done)return;done=true;var s=null;(function step(ts){s=s||ts;var p=Math.min((ts-s)/1400,1);el.textContent=Math.floor(p*target).toLocaleString();if(p<1)requestAnimationFrame(step);else el.textContent=target.toLocaleString()})(performance.now())}
    if('IntersectionObserver' in window){new IntersectionObserver(function(es,o){if(es[0].isIntersecting){run();o.disconnect()}}).observe(el)}else run();
  });

  // countdown
  document.querySelectorAll('[data-countdown]').forEach(function(box){
    var end=new Date(box.getAttribute('data-countdown').replace(' ','T')).getTime();
    var ended=box.getAttribute('data-ended')||'';
    function tick(){var d=end-Date.now();if(isNaN(end)||d<=0){box.innerHTML='<b>'+ended+'</b>';return}
      var v=[Math.floor(d/864e5),Math.floor(d%864e5/36e5),Math.floor(d%36e5/6e4),Math.floor(d%6e4/1e3)];
      box.querySelectorAll('[data-u]').forEach(function(n,i){n.textContent=String(v[i]).padStart(2,'0')});setTimeout(tick,1000)}
    tick();
  });

  // PWA
  if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register((window.APP_BASE||'/')+'sw.js').catch(function(){})})}
  var deferred;window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferred=e;document.querySelectorAll('[data-install]').forEach(function(b){b.classList.remove('d-none')})});
  document.querySelectorAll('[data-install]').forEach(function(b){b.addEventListener('click',function(){if(deferred){deferred.prompt();deferred=null}})});

  // CSRF helper for fetch
  window.csrfToken=function(){var m=document.querySelector('meta[name="csrf"]');return m?m.content:''};
  window.postJSON=function(url,data){
    return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':window.csrfToken()},body:JSON.stringify(data||{})}).then(function(r){return r.json()});
  };
})();
