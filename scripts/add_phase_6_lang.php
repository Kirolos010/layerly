<?php
$enJsonPath = __DIR__ . '/../lang/en.json';
$arJsonPath = __DIR__ . '/../lang/ar.json';
$enPhpPath  = __DIR__ . '/../lang/en.php';
$arPhpPath  = __DIR__ . '/../lang/ar.php';

$en = json_decode(file_get_contents($enJsonPath), true) ?: [];
$ar = json_decode(file_get_contents($arJsonPath), true) ?: [];

$phase6En = [
    'ai_poster'                    => 'AI Poster',
    'ai_poster_new'                => 'AI Poster (New)',
    'ai_poster_desc'               => 'Create or reconstruct high-end posters with editable typography & clean backgrounds.',
    'mode_import_tab'              => 'Import & Make Editable',
    'mode_import_title'            => 'Import Finished Poster & Make Editable',
    'mode_import_desc'             => 'Upload any poster image to transcribe every text block, remove baked-in text, and rebuild editable layers.',
    'mode_text_poster_tab'         => 'Text to Poster',
    'mode_text_poster_title'       => 'Generate Full Poster from Scratch',
    'mode_text_poster_desc'        => 'Generate art-directed posters with high-impact artwork and perfectly styled typography layers.',
    'poster_upload_label'          => 'Upload Poster Image',
    'poster_dropzone_hint'         => 'Drag & drop poster image here, or click to browse (Max 5MB: JPG, PNG, WebP)',
    'clean_plate_option'           => 'Generate clean plate (seamlessly remove original text)',
    'keep_original_option'         => 'Keep original image as background and put editable text on top',
    'btn_import_poster'            => '✨ Import & Rebuild Editable Layers',
    'btn_generate_poster'          => '🚀 Generate AI Poster',
    'poster_prompt_label'          => 'Poster Concept & Idea',
    'poster_prompt_placeholder'    => 'e.g. Promotional poster for an AI & Machine Learning course with cyber developer artwork and Dubai neon skyline...',
    'style_preset_label'           => 'Poster Visual Style',
    'style_cyber_tech'             => 'Cyber & Tech',
    'style_luxury_gold'            => 'Luxury & Gold',
    'style_modern_minimal'         => 'Modern Minimal',
    'style_event_promo'            => 'Event & Conference',
    'style_calligraphy'            => 'Modern Arabic Calligraphy',
    'poster_quota_badge'           => ':count poster generations remaining today',
    'poster_quota_exceeded'        => 'Daily poster limit reached (5 generations/day on free tier). Resets at midnight.',
    'step_import_1'                => 'Analyzing text & layout with Gemini Vision...',
    'step_import_2'                => 'Generating clean plate background...',
    'step_import_3'                => 'Building editable vector layers & badges...',
    'step_gen_1'                   => 'Creative art direction & copywriting pass...',
    'step_gen_2'                   => 'Generating text-free background artwork...',
    'step_gen_3'                   => 'Positioning typography & badges in safe zones...',
    'step_retry_btn'               => 'Retry Step',
    'clean_plate_fallback_notice'  => 'Clean plate generated via inpainting fallback (image model unavailable).',
    'compare_view'                 => 'Compare with Original',
    'compare_view_hint'            => 'Toggle or drag slider to compare reconstruction against original poster',
    'btn_realign'                  => 'Re-align Layers',
    'realign_tooltip'              => 'Auto-fit and re-snap text and badge layers to their detected bounding boxes',
    'realign_success'              => 'Layers re-aligned and typography fitted accurately.',
    'poster_meta_info'             => 'Poster Generation Meta',
    'poster_image_failed'          => 'Failed to generate background art. Please retry or upload a background image.',
    'original_poster_label'        => 'Original Poster',
    'rebuilt_poster_label'         => 'Editable Rebuild',
    'compare_slider_label'         => 'Compare Slider',
    'toggle_compare'               => 'A/B Toggle'
];

$phase6Ar = [
    'ai_poster'                    => 'بوستر الذكاء الاصطناعي',
    'ai_poster_new'                => 'بوستر الذكاء الاصطناعي (جديد)',
    'ai_poster_desc'               => 'توليد أو إعادة بناء بوسترات احترافية بطبقات نصوص وخلفيات نقية قابلة للتعديل.',
    'mode_import_tab'              => 'استيراد وتحويل لتصميم قابل للتعديل',
    'mode_import_title'            => 'استيراد بوستر وتحويله لتصميم قابل للتعديل بالكامل',
    'mode_import_desc'             => 'ارفع صورة أي بوستر لاستخراج جميع النصوص، إزالة النصوص المطبوعة، وإعادة بناء طبقات قابلة للتعديل.',
    'mode_text_poster_tab'         => 'توليد بوستر من فكرة',
    'mode_text_poster_title'       => 'توليد بوستر إعلاني متكامل من فكرة نصية',
    'mode_text_poster_desc'        => 'توليد بوسترات بتوجيه فني دقيق، عمل فني مذهل للخلفية، وطبقات خطوط عربية وأزرار منسقة بامتياز.',
    'poster_upload_label'          => 'رفع صورة البوستر',
    'poster_dropzone_hint'         => 'اسحب صورة البوستر هنا، أو انقر للاختيار من جهازك (حتى 5 ميجابايت: JPG, PNG, WebP)',
    'clean_plate_option'           => 'توليد خلفية نقية (إزالة النصوص الأصلية بسلاسة)',
    'keep_original_option'         => 'الاحتفاظ بالصورة الأصلية كخلفية ووضع النصوص القابلة للتعديل فوقها',
    'btn_import_poster'            => '✨ استيراد وبناء الطبقات القابلة للتعديل',
    'btn_generate_poster'          => '🚀 توليد بوستر الذكاء الاصطناعي',
    'poster_prompt_label'          => 'فكرة وموضوع البوستر',
    'poster_prompt_placeholder'    => 'مثال: إعلان ترويجي لكورس برمجة وتطوير الذكاء الاصطناعي مع مبرمج وسيتي لاين دبي السايبر...',
    'style_preset_label'           => 'النمط البصري للبوستر',
    'style_cyber_tech'             => 'تقني وسايبر (Cyber Tech)',
    'style_luxury_gold'            => 'فاخر وذهبي (Luxury Gold)',
    'style_modern_minimal'         => 'عصري وبسيط (Minimal)',
    'style_event_promo'            => 'فعاليات ومؤتمرات (Event Promo)',
    'style_calligraphy'            => 'خط عربي عصري (Arabic Calligraphy)',
    'poster_quota_badge'           => 'متبقي :count عمليات بوستر اليوم',
    'poster_quota_exceeded'        => 'تم استنفاد الحد اليومي لتوليد البوسترات (5 يومياً للنسخة المجانية). يتجدد منتصف الليل.',
    'step_import_1'                => 'جاري تحليل وقراءة النصوص بواسطة Gemini Vision...',
    'step_import_2'                => 'جاري توليد الخلفية النقية وإزالة النصوص المطبوعة...',
    'step_import_3'                => 'جاري بناء الطبقات القابلة للتعديل والأزرار...',
    'step_gen_1'                   => 'جاري التوجيه الفني وصياغة النصوص الذكية...',
    'step_gen_2'                   => 'جاري توليد العمل الفني للخلفية بدون نصوص...',
    'step_gen_3'                   => 'جاري توزيع النصوص والأزرار في المناطق الآمنة...',
    'step_retry_btn'               => 'إعادة محاولة الخطوة',
    'clean_plate_fallback_notice'  => 'تم توليد الخلفية النقية بتقنية التمويه الموضعي البديلة (نموذج الصور غير متاح).',
    'compare_view'                 => 'مقارنة بالأصل',
    'compare_view_hint'            => 'بدّل أو حرّك لمقارنة التصميم المعاد بناؤه بالبوستر الأصلي',
    'btn_realign'                  => 'إعادة محاذاة الطبقات',
    'realign_tooltip'              => 'إعادة مطابقة النصوص والأزرار بدقة مع المربعات المكتشفة في التصميم',
    'realign_success'              => 'تمت محاذاة الطبقات وضبط أحجام النصوص بنجاح.',
    'poster_image_failed'          => 'تعذر توليد العمل الفني للخلفية. يرجى إعادة المحاولة أو رفع صورة خلفية.',
    'poster_meta_info'             => 'بيانات خط أنابيب البوستر',
    'original_poster_label'        => 'البوستر الأصلي',
    'rebuilt_poster_label'         => 'التصميم المعاد بناؤه',
    'compare_slider_label'         => 'شريط المقارنة',
    'toggle_compare'               => 'تبديل A/B'
];

$enMerged = array_merge($en, $phase6En);
$arMerged = array_merge($ar, $phase6Ar);

file_put_contents($enJsonPath, json_encode($enMerged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($arJsonPath, json_encode($arMerged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

file_put_contents($enPhpPath, "<?php\nreturn " . var_export($enMerged, true) . ";\n");
file_put_contents($arPhpPath, "<?php\nreturn " . var_export($arMerged, true) . ";\n");

echo "Phase 6 bilingual strings successfully added (" . count($enMerged) . " EN / " . count($arMerged) . " AR keys).\n";

