-- ترقية قاعدة بيانات Aramio إلى النسخة 2 (آمنة: لا تعدّل ولا تحذف أي جدول أو بيانات موجودة)
-- يمكن تشغيلها أكثر من مرة دون ضرر. Safe & idempotent: only CREATE TABLE IF NOT EXISTS.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  k CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_k (k, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity VARCHAR(40) NOT NULL,
  entity_id INT NOT NULL,
  field VARCHAR(60) NOT NULL,
  lang CHAR(2) NOT NULL,
  value MEDIUMTEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tr (entity, entity_id, field, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  feature VARCHAR(40) NOT NULL,
  provider VARCHAR(30) DEFAULT NULL,
  prompt_chars INT DEFAULT 0,
  reply_chars INT DEFAULT 0,
  ok TINYINT(1) DEFAULT 1,
  error VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feature (feature, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_cache (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  h CHAR(64) NOT NULL UNIQUE,
  reply MEDIUMTEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  KEY idx_tok (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  pass_score TINYINT DEFAULT 60,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question TEXT NOT NULL,
  options TEXT NOT NULL,          -- JSON array
  correct_index TINYINT NOT NULL,
  explanation TEXT NULL,
  KEY idx_quiz (quiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  trainee_id INT NOT NULL,
  score TINYINT NOT NULL,
  passed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_q (quiz_id, trainee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  body VARCHAR(500) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  lang CHAR(2) DEFAULT 'ar',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_ar VARCHAR(255) NOT NULL,
  question_en VARCHAR(255) NOT NULL,
  answer_ar TEXT NOT NULL,
  answer_en TEXT NOT NULL,
  sort_order INT DEFAULT 0,
  status TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  detail VARCHAR(255) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value_ar, setting_value_en) VALUES
 ('ai_enabled', '1', '1'),
 ('ai_provider', '', ''),
 ('ai_model', '', ''),
 ('ai_base_url', '', ''),
 ('ai_api_key_enc', '', ''),
 ('whatsapp_number', '', ''),
 ('seo_description', 'أكاديمية أراميو: دورات تدريبية معتمدة واستشارات وشهادات موثّقة مدعومة بالذكاء الاصطناعي', 'Aramio Academy: accredited courses, consultations and verified certificates powered by AI');

INSERT INTO faqs (question_ar, question_en, answer_ar, answer_en, sort_order)
SELECT * FROM (SELECT 'كيف أتحقق من صحة شهادتي؟' a, 'How can I verify my certificate?' b, 'أدخل رقم الشهادة في صفحة التحقق وستظهر لك بياناتها فورًا، أو امسح رمز QR الموجود على الشهادة.' c, 'Enter the certificate ID on the verification page, or scan the QR code printed on the certificate.' d, 1 e) x
WHERE NOT EXISTS (SELECT 1 FROM faqs);
INSERT INTO faqs (question_ar, question_en, answer_ar, answer_en, sort_order)
SELECT 'ما طرق الدفع المتاحة؟','What payment methods are available?','التحويل البنكي، باي بال، أو عبر وكيل معتمد. ارفع إيصال الدفع وسيتم تفعيل دورتك بعد المراجعة.','Bank transfer, PayPal or an authorised agent. Upload your receipt and your course is activated after review.',2
WHERE (SELECT COUNT(*) FROM faqs) = 1;
INSERT INTO faqs (question_ar, question_en, answer_ar, answer_en, sort_order)
SELECT 'هل الدورات متاحة بلغات متعددة؟','Are courses available in multiple languages?','نعم، الموقع يدعم العربية والإنجليزية والفرنسية والتركية والإسبانية والملايو، ويمكن ترجمة محتوى الدورات بالذكاء الاصطناعي.','Yes. The site supports Arabic, English, French, Turkish, Spanish and Malay, and course content can be translated with AI.',3
WHERE (SELECT COUNT(*) FROM faqs) = 2;
