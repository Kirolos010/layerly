<?php
/**
 * Helper to build and synchronize lang/en.json, lang/ar.json, lang/en.php, lang/ar.php
 */

$enOld = require __DIR__ . '/../lang/en.php';
$arOld = require __DIR__ . '/../lang/ar.php';

$enNew = [
    // SEO
    'meta_title' => 'AI Design Studio | Free Bilingual Vector Design Generator',
    'meta_desc' => 'Create, edit, and export bilingual designs with AI. Natural language prompt to editable canvas objects with Cairo, Tajawal, Poppins and Montserrat fonts.',
    'og_title' => 'AI Design Studio - AI Powered Bilingual Canvas Editor',
    'og_desc' => 'Describe your design idea and watch AI create an editable vector canvas with independent layers, Google Fonts, and full Arabic RTL support.',
    
    // Themes & Liquid Glass
    'theme_toggle' => 'Toggle Light/Dark Theme',
    'theme_light' => 'Light Mode',
    'theme_dark' => 'Dark Mode',
    'liquid_glass_studio' => 'Liquid Glass Studio',
    'collapse_panel' => 'Collapse Panel',
    'expand_panel' => 'Expand Panel',
    'floating_dock' => 'Elements Dock',
    'tools_title' => 'Design Elements',
    'ai_command_bar' => 'Edit with AI Command Bar',
    'canvas_drag_hint' => 'Pan & Move',
    'quick_presets' => 'Canvas Presets',
    'floating_toolbar' => 'Floating Toolbar',
    'properties_inspector' => 'Inspector Panel',

    // Toasts
    'toast_saved' => 'Design saved successfully to cloud!',
    'toast_autosaved' => 'Autosaved locally',
    'toast_exported' => 'Export downloaded successfully',
    'toast_copied' => 'Copied to clipboard!',
    'toast_imported' => 'Project imported successfully!',
    'toast_undone' => 'Action undone',
    'toast_redone' => 'Action redone',
    'toast_cleared' => 'History cleared',
    'toast_error' => 'An error occurred. Please try again.',
    'toast_restored' => 'Previous session restored!',

    // Shortcuts Modal
    'shortcuts_title' => 'Keyboard Shortcuts',
    'shortcuts_desc' => 'Supercharge your workflow with studio hotkeys',
    'key_undo' => 'Undo change',
    'key_redo' => 'Redo change',
    'key_copy' => 'Copy element',
    'key_paste' => 'Paste element',
    'key_duplicate' => 'Duplicate selected',
    'key_delete' => 'Delete selected',
    'key_nudge' => 'Nudge 1px / 10px (with Shift)',
    'key_zoom' => 'Zoom In / Out',
    'key_save' => 'Save Project',
    'close_shortcuts' => 'Close Shortcuts',

    // About & Help
    'about' => 'About & Help',
    'about_title' => 'About AI Design Studio',
    'about_tagline' => 'The Future of Generative Bilingual Vector Design',
    'about_feature_ai_title' => 'AI Structured Engine',
    'about_feature_ai_desc' => 'Gemini-powered generative architecture translates natural language prompts directly into editable vector elements.',
    'about_feature_rtl_title' => 'Native Arabic Typography & RTL',
    'about_feature_rtl_desc' => 'True letter-joining and RTL orientation with curated Google Fonts: Cairo, Tajawal, Almarai, Amiri, and Reem Kufi.',
    'about_feature_glass_title' => 'Liquid Glass Aesthetics',
    'about_feature_glass_desc' => 'Translucent floating panels, specular highlights, GPU-accelerated mesh orbs, and fluid micro-interactions.',
    'about_feature_export_title' => 'High-Res Production Export',
    'about_feature_export_desc' => 'Export crisp PNGs with transparency, high-quality JPGs, SVG vectors, or full project JSON backups.',
    'back_to_studio' => 'Back to Studio',

    // 404
    'page_not_found' => 'Page Not Found (404)',
    'page_not_found_desc' => 'The page you are looking for does not exist or has been moved.',
    'return_to_studio' => 'Return to Studio Canvas'
];

$arNew = [
    // SEO
    'meta_title' => 'استوديو التصميم الذكي | منصة التصميم بالذكاء الاصطناعي ثنائية اللغة',
    'meta_desc' => 'أنشئ وحرر وصدّر تصاميم احترافية بالذكاء الاصطناعي باللغتين العربية والإنجليزية مع كانفاس تفاعلي وخطوط عربية راقية.',
    'og_title' => 'استوديو التصميم الذكي - محرر كانفاس ذكي ثنائي اللغة',
    'og_desc' => 'صف فكرة تصميمك وسيقوم الذكاء الاصطناعي بإنشاء كانفاس متكامل بطبقات مستقلة ودعم كامل للغة العربية.',
    
    // Themes & Liquid Glass
    'theme_toggle' => 'تبديل المظهر الفاتح/الداكن',
    'theme_light' => 'الوضع الفاتح',
    'theme_dark' => 'الوضع الداكن',
    'liquid_glass_studio' => 'استوديو الزجاج السائل',
    'collapse_panel' => 'طي اللوحة',
    'expand_panel' => 'توسيع اللوحة',
    'floating_dock' => 'شريط الأدوات العائم',
    'tools_title' => 'عناصر التصميم',
    'ai_command_bar' => 'شريط أوامر التعديل الذكي',
    'canvas_drag_hint' => 'تحريك الكانفاس',
    'quick_presets' => 'أبعاد الكانفاس',
    'floating_toolbar' => 'شريط الأدوات العائم',
    'properties_inspector' => 'لوحة الخصائص',

    // Toasts
    'toast_saved' => 'تم حفظ التصميم بنجاح في السحابة!',
    'toast_autosaved' => 'تم الحفظ التلقائي محلياً',
    'toast_exported' => 'تم تنزيل التصميم بنجاح',
    'toast_copied' => 'تم النسخ إلى الحافظة!',
    'toast_imported' => 'تم استيراد المشروع بنجاح!',
    'toast_undone' => 'تم التراجع عن الإجراء',
    'toast_redone' => 'تمت إعادة الإجراء',
    'toast_cleared' => 'تم مسح السجل بنجاح',
    'toast_error' => 'حدث خطأ ما. يرجى المحاولة مجدداً.',
    'toast_restored' => 'تمت استعادة الجلسة السابقة!',

    // Shortcuts Modal
    'shortcuts_title' => 'اختصارات لوحة المفاتيح',
    'shortcuts_desc' => 'ضاعف سرعتك واحترافيتك مع اختصارات الاستوديو',
    'key_undo' => 'تراجع عن التعديل',
    'key_redo' => 'إعادة التعديل',
    'key_copy' => 'نسخ العنصر المحدد',
    'key_paste' => 'لصق العنصر',
    'key_duplicate' => 'تكرار العنصر المحدد',
    'key_delete' => 'حذف العنصر المحدد',
    'key_nudge' => 'تحريك دقيق 1px / 10px (مع Shift)',
    'key_zoom' => 'تكبير / تصغير الكانفاس',
    'key_save' => 'حفظ المشروع',
    'close_shortcuts' => 'إغلاق الاختصارات',

    // About & Help
    'about' => 'حول المنصة والمساعدة',
    'about_title' => 'عن استوديو التصميم الذكي',
    'about_tagline' => 'مستقبل التصميم الجرافيكي الذكي ثنائي اللغة',
    'about_feature_ai_title' => 'محرك الذكاء الاصطناعي الهيكلي',
    'about_feature_ai_desc' => 'بنية توليدية مدعومة بنماذج Gemini تترجم الأفكار النصية إلى عناصر رسومية متجهة قابلة للتحرير الفوري.',
    'about_feature_rtl_title' => 'طباعة عربية أصيلة ودعم كامل لليمين إلى اليسار',
    'about_feature_rtl_desc' => 'تشبيك حروف سليم وتوجيه كامل من اليمين لليسار مع نخبة الخطوط العربية: كايرو، تجوال، المراعي، أميري، وريم كوفي.',
    'about_feature_glass_title' => 'تصميم الزجاج السائل الانسيابي',
    'about_feature_glass_desc' => 'واجهة عائمة شفافة ذات انعكاسات ناعمة وتدرجات لونية هادئة تعمل بسلاسة فائقة بمعدل 60 إطاراً في الثانية.',
    'about_feature_export_title' => 'تصدير فائق الدقة للطباعة والويب',
    'about_feature_export_desc' => 'تصدير صور PNG شفافة، JPG بجودة عالية للطباعة، ملفات متجهة SVG، أو نسخ احتياطية للمشروع.',
    'back_to_studio' => 'العودة إلى الاستوديو',

    // 404
    'page_not_found' => 'الصفحة غير موجودة (404)',
    'page_not_found_desc' => 'عذراً، الصفحة التي تحاول الوصول إليها غير موجودة أو تم نقلها.',
    'return_to_studio' => 'العودة إلى كانفاس الاستوديو'
];

$enTotal = array_merge($enOld, $enNew);
$arTotal = array_merge($arOld, $arNew);

file_put_contents(__DIR__ . '/../lang/en.json', json_encode($enTotal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__ . '/../lang/ar.json', json_encode($arTotal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

file_put_contents(__DIR__ . '/../lang/en.php', "<?php\nreturn " . var_export($enTotal, true) . ";\n");
file_put_contents(__DIR__ . '/../lang/ar.php', "<?php\nreturn " . var_export($arTotal, true) . ";\n");

echo "Generated lang/en.json (" . count($enTotal) . " keys) and lang/ar.json (" . count($arTotal) . " keys)\n";

