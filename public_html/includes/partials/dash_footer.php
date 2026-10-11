  </div>
</div>
<?php $copilot = $copilot ?? true; if ($copilot): ?>
<div class="dash-chat no-print"><button class="fab" data-ai-toggle aria-label="AI"><i class="fa-solid fa-wand-magic-sparkles"></i></button></div>
<div class="chat-box dc" id="aiChat" style="bottom:86px">
  <div class="chat-head"><div class="av"><i class="fa-solid fa-robot"></i></div><div><b><?= e(d('مساعد أراميو الذكي', 'Aramio Copilot')) ?></b><div class="small opacity-75"><?= e($roleLabel) ?></div></div><button class="x" aria-label="close">&times;</button></div>
  <div class="chat-body"></div><div class="chat-note"><?= e(t('ai_disclaimer')) ?></div>
  <form class="chat-foot" autocomplete="off"><input type="text" maxlength="600" placeholder="<?= e(t('ai_placeholder')) ?>"><button type="submit"><i class="fa-solid fa-paper-plane"></i></button></form>
</div>
<script>window.AI_CFG={endpoint:'../api/ai_copilot.php',welcome:<?= json_encode(ai_available() ? d('أنا مساعدك الذكي. اسألني عن بياناتك أو كيف تنجز مهمة في اللوحة.', 'I am your AI copilot. Ask about your data or how to get things done.') : t('ai_unavailable'), JSON_UNESCAPED_UNICODE) ?>,error:<?= json_encode(t('ai_error'), JSON_UNESCAPED_UNICODE) ?>};</script>
<?php endif; ?>
<script src="<?= $B ?>assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php if (!empty($needChart)): ?><script src="<?= $B ?>assets/vendor/chartjs/chart.umd.min.js"></script><?php endif; ?>
<script src="<?= $B ?>assets/js/app.js?v=2"></script>
<?php if ($copilot): ?><script src="<?= $B ?>assets/js/chat.js?v=2"></script><?php endif; ?>
<script>
(function(){var s=document.getElementById('dSide'),o=document.getElementById('dOverlay');
document.getElementById('dOpen')?.addEventListener('click',function(){s.classList.add('open');o.classList.add('open')});
o.addEventListener('click',function(){s.classList.remove('open');o.classList.remove('open')});
document.getElementById('markRead')?.addEventListener('click',function(e){e.preventDefault();window.postJSON('<?= $B ?>api/notifications.php',{action:'read_all'}).then(function(){location.reload()})});
window.AI_ASK=function(url,payload,cb,btn){var t=btn&&btn.innerHTML;if(btn){btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>'}
 window.postJSON(url,payload).then(function(r){cb(r)}).catch(function(){cb({ok:false,message:<?= json_encode(t('ai_error'), JSON_UNESCAPED_UNICODE) ?>})}).finally(function(){if(btn){btn.disabled=false;btn.innerHTML=t}})};
})();
</script>
<?= $footExtra ?? '' ?>
</body></html>
