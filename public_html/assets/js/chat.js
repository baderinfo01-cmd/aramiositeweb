/* AI chat widget */
(function(){
  var box=document.getElementById('aiChat'); if(!box) return;
  var body=box.querySelector('.chat-body'),form=box.querySelector('form'),input=form.querySelector('input'),opened=false,history=[];
  var cfg=window.AI_CFG||{};
  function add(role,text){var d=document.createElement('div');d.className='msg '+(role==='user'?'user':'bot');d.textContent=text;
    d.innerHTML=d.innerHTML.replace(/(https?:\/\/[^\s<]+|[a-z_\/]+\.php\?[^\s<)]+)/gi,function(u){return '<a href="'+u+'">'+u+'</a>'});
    body.appendChild(d);body.scrollTop=body.scrollHeight;return d}
  function toggle(){box.classList.toggle('open');if(box.classList.contains('open')){if(!opened){opened=true;add('bot',cfg.welcome)}setTimeout(function(){input.focus()},50)}}
  document.querySelectorAll('[data-ai-toggle]').forEach(function(b){b.addEventListener('click',toggle)});
  box.querySelector('.x').addEventListener('click',toggle);
  form.addEventListener('submit',function(e){e.preventDefault();var q=input.value.trim();if(!q)return;input.value='';add('user',q);history.push({role:'user',content:q});
    var t=add('bot','');t.innerHTML='<span class="typing"><i></i><i></i><i></i></span>';
    window.postJSON(cfg.endpoint,{messages:history.slice(-10)}).then(function(r){
      if(r&&r.ok){t.textContent=r.reply;t.innerHTML=t.innerHTML.replace(/(https?:\/\/[^\s<]+|[a-z_\/]+\.php\?[^\s<)]+)/gi,function(u){return '<a href="'+u+'">'+u+'</a>'});history.push({role:'assistant',content:r.reply})}
      else t.textContent=(r&&r.message)||cfg.error;body.scrollTop=body.scrollHeight}).catch(function(){t.textContent=cfg.error});
  });
})();
