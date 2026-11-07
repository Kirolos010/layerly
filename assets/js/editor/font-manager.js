/**
 * AI Design Studio - Font Manager & Loader
 * Ensures web fonts are loaded prior to rendering to avoid glyph/box miscalculations.
 */

export const ARABIC_FONTS = ['Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi'];
export const ENGLISH_FONTS = ['Poppins', 'Montserrat', 'Playfair Display', 'Roboto'];

export const GOOGLE_FONTS = {
    arabic: [
        { name: 'Cairo', label: 'Cairo (عصري بارز)', sample: 'تصميم عربي احترافي' },
        { name: 'Tajawal', label: 'Tajawal (ناعم وعصري)', sample: 'أهلاً بكم في الاستوديو' },
        { name: 'Almarai', label: 'Almarai (واضح وشامل)', sample: 'خط المراعي الأنيق' },
        { name: 'Amiri', label: 'Amiri (كلاسيكي نسخي)', sample: 'جمال الخط الكلاسيكي' },
        { name: 'Reem Kufi', label: 'Reem Kufi (كوفي هندسي)', sample: 'خط ريم كوفي الفني' }
    ],
    english: [
        { name: 'Poppins', label: 'Poppins (Geometric Clean)', sample: 'Clean Geometric Headings' },
        { name: 'Montserrat', label: 'Montserrat (Modern Bold)', sample: 'Modern Bold Typography' },
        { name: 'Playfair Display', label: 'Playfair Display (Luxury Serif)', sample: 'Elegant Editorial Style' },
        { name: 'Roboto', label: 'Roboto (Neutral Sans)', sample: 'Crisp & Highly Readable' }
    ]
};

const loadedFontsCache = new Set();

/**
 * Check if a string contains Arabic script
 */
export function isArabic(text) {
    if (!text || typeof text !== 'string') return false;
    return /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/.test(text);
}

/**
 * Pre-load specific fonts using document.fonts API
 * @param {string[]} fontFamilies
 * @returns {Promise<void>}
 */
export async function loadFonts(fontFamilies = []) {
    if (!('fonts' in document)) return;

    const unique = [...new Set(fontFamilies.filter(f => f && !loadedFontsCache.has(f)))];
    if (unique.length === 0) {
        await document.fonts.ready;
        return;
    }

    const promises = unique.map(async (fontName) => {
        try {
            // Load 400 normal and 700 bold
            await Promise.allSettled([
                document.fonts.load(`400 16px "${fontName}"`),
                document.fonts.load(`700 16px "${fontName}"`)
            ]);
            loadedFontsCache.add(fontName);
        } catch (e) {
            console.warn(`Could not preload font "${fontName}":`, e);
        }
    });

    await Promise.allSettled(promises);
    await document.fonts.ready;
}

