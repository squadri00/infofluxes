<?php

/**
 * The languages of the Story maker (step 2: translating a saved story). code => [name, name in its own language, right to left?, the Quran translation to use, the wording list].
 * These are first drafts. The super user can change them on the "Story translations" screen. Check the exact name of a Quran translation on Quran.com.
 * Arabic has no translation line: the Arabic verse stands as it is.
 */
return [
    'ur' => ['Urdu', 'اردو', true, 'Fateh Muhammad Jalandhry',
        "Allah (SWT) → اللہ سبحانہ و تعالیٰ\nProphet Muhammad ﷺ → حضرت محمد ﷺ (the symbol ﷺ stays as it is)\nOther prophets (AS) → علیہ السلام\nCompanions (RA) → رضی اللہ عنہ (for a woman: رضی اللہ عنہا)\nQuran → قرآن    Hadith → حدیث    Sharia → شریعت"],
    'ar' => ['Arabic', 'العربية', true, '',
        "Allah (SWT) → الله سبحانه وتعالى\nProphet Muhammad ﷺ → النبي محمد ﷺ (the symbol ﷺ stays as it is)\nOther prophets (AS) → عليه السلام\nCompanions (RA) → رضي الله عنه (for a woman: رضي الله عنها)\nQuran → القرآن    Hadith → الحديث    Sharia → الشريعة"],
    'fr' => ['French', 'Français', false, 'Muhammad Hamidullah',
        "Allah (SWT) → Allah (SWT)\nProphet Muhammad ﷺ → le Prophète Muhammad ﷺ (the symbol ﷺ stays as it is)\nOther prophets (AS) → (AS)\nCompanions (RA) → (RA)\nQuran → le Coran    Hadith → le hadith    Sharia → la charia"],
    'es' => ['Spanish', 'Español', false, 'Julio Cortes',
        "Allah (SWT) → Allah (SWT)\nProphet Muhammad ﷺ → el Profeta Muhammad ﷺ (the symbol ﷺ stays as it is)\nOther prophets (AS) → (AS)\nCompanions (RA) → (RA)\nQuran → el Corán    Hadith → el hadiz    Sharia → la sharía"],
    'hi' => ['Hindi', 'हिन्दी', false, 'Suhel Farooq Khan and Saifur Rahman Nadwi',
        "Allah (SWT) → अल्लाह (SWT)\nProphet Muhammad ﷺ → पैग़म्बर मुहम्मद ﷺ (the symbol ﷺ stays as it is)\nOther prophets (AS) → (AS)\nCompanions (RA) → (RA)\nQuran → क़ुरआन    Hadith → हदीस    Sharia → शरीअत"],
];
