<?php
/**
 * انسخ هذا الملف باسم config.local.php واملأ القيم. لا ترفع config.local.php إلى GitHub أبدًا.
 * Copy this file to config.local.php and fill in the values. Never commit config.local.php.
 */
return [
    // ---- قاعدة البيانات / Database ----
    'db_host' => 'localhost',
    'db_name' => 'u846492886_a',
    'db_user' => 'u846492886_a',
    'db_pass' => 'ضع_كلمة_المرور_هنا',

    // ---- الموقع / Site ----
    'base_url'   => 'https://aramiosmartglobal.com',   // محليًا: http://localhost:8080
    'debug'      => false,                              // true محليًا فقط
    'app_secret' => 'ضع_نصًا_عشوائيًا_طويلًا_هنا',      // لتشفير مفاتيح الذكاء الاصطناعي المخزنة
    'mail_from'  => 'no-reply@aramiosmartglobal.com',

    // ---- الذكاء الاصطناعي / AI (اختياري — يمكن ضبطه من لوحة التحكم > الذكاء الاصطناعي) ----
    // provider: anthropic | openai | gemini | compatible (أي خدمة متوافقة مع OpenAI مثل DeepSeek/Groq/OpenRouter/Ollama)
    'ai_provider' => '',
    'ai_api_key'  => '',
    'ai_model'    => '',
    'ai_base_url' => '',   // فقط لـ compatible
];
