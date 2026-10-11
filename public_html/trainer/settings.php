<?php
require_once 'trainer_init.php';
require_once '../includes/profile.php';
profile_handle();
$dashTitle = d('إعداداتي', 'Settings');
require '../includes/partials/dash_header.php';
profile_render(true);
$footExtra = '<script>document.getElementById("bioAi")?.addEventListener("click",function(){AI_ASK("../api/ai_tools.php",{action:"bio",text:document.getElementById("bio").value},function(r){if(r.ok)document.getElementById("bio").value=r.text;else alert(r.message||"error")},this)});</script>';
require '../includes/partials/dash_footer.php';
