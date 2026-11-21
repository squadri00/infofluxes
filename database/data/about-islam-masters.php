<?php

/**
 * The new masters of About Islam: Prophet Muhammad ﷺ, Hadith and Sunnah, Islamic Beliefs, Spirituality and Character, Common Questions.
 * Master (level 2) > topic (level 3, holds the articles) > planned subjects (empty draft index lines, grouped by a Subtopic).
 * Used by App\Support\MasterBuilder (php artisan masters:build). Titles only: no claim is made here, the editors write the articles.
 */
return [
    'parent' => 'about-islam',
    // The order of all masters under About Islam (existing ones are named by their slug, new ones by their name).
    'order' => ['allah-swt-index', 'the-quran', 'Prophet Muhammad ﷺ Index', 'Hadith and Sunnah Index', 'sharia-index', 'Islamic Beliefs Index', 'Spirituality and Character Index', 'Common Questions and Misconceptions'],

    // Subjects of the plan that are the same as an article that already exists: the existing article is listed in the new topic too, so nothing is written twice.
    'same_as' => [
        'What do sahih, hasan and daif mean?' => 'A-1801',
        'What is a chain of narrators (isnad)?' => 'A-1672',
        'Fortune-telling, astrology and horoscopes' => 'A-2352',
        'Why the Quran is the final and protected revelation' => 'A-1485',
        'Contentment (rida) with the decree of Allah (SWT)' => 'A-1503',
    ],

    'masters' => [

        // =====================================================================================================
        [
            'name' => 'Prophet Muhammad ﷺ Index',
            'icon' => 'dome',
            'description' => 'The life, character and legacy of Prophet Muhammad ﷺ, subject by subject, from the earliest sources.',
            'topics' => [
                [
                    'name' => 'His Birth, Family and Early Life',
                    'description' => 'His ancestry, birth, childhood and youth before the first revelation.',
                    'groups' => [
                        'Ancestry and birth' => [
                            'The family and tribe of Prophet Muhammad ﷺ',
                            'The Year of the Elephant and the birth of Prophet Muhammad ﷺ',
                            'What is known about the date of his ﷺ birth',
                            'Why Muslims differ about celebrating the Mawlid',
                        ],
                        'Childhood' => [
                            'His ﷺ father Abdullah and his mother Aminah',
                            'Life with Halimah (RA) in the desert',
                            'The loss of his ﷺ mother and the care of his grandfather Abdul Muttalib',
                            'Growing up in the house of his uncle Abu Talib',
                            'The journey to Syria and the monk Bahira',
                        ],
                        'Youth' => [
                            'His ﷺ work as a shepherd and a trader',
                            'Why he ﷺ was called Al-Amin, the Trustworthy',
                            'The Hilf al-Fudul pact for justice',
                            'Rebuilding the Kaaba and the placing of the Black Stone',
                            'His ﷺ marriage to Khadijah (RA)',
                        ],
                    ],
                ],
                [
                    'name' => 'The First Revelation and the Makkah Years',
                    'description' => 'The call to Islam in Makkah, the hardship of the first Muslims and the Night Journey.',
                    'groups' => [
                        'The beginning of revelation' => [
                            'The cave of Hira and the first revelation',
                            'Jibreel (AS) and the first meeting',
                            'How Khadijah (RA) comforted him ﷺ',
                            'Waraqa ibn Nawfal and the first confirmation',
                            'How revelation came to the Prophet ﷺ',
                            'The pause in revelation',
                        ],
                        'Calling people to Islam' => [
                            'The first Muslims',
                            'The secret call and the open call',
                            'What the Quraysh offered and why they refused',
                            'The suffering of the early Muslims: Bilal (RA), Sumayyah (RA) and others',
                            'The migration to Abyssinia',
                            'The boycott of Banu Hashim',
                            'The Year of Sorrow: the deaths of Khadijah (RA) and Abu Talib',
                            'The journey to Taif',
                        ],
                        'The Night Journey' => [
                            'Isra and Miraj: the Night Journey and the Ascension',
                            'How the five daily prayers were given',
                            'How scholars understand the Night Journey',
                        ],
                    ],
                ],
                [
                    'name' => 'The Hijrah and the Madinah Years',
                    'description' => 'The move to Madinah, the first Muslim community, the treaties and the return to Makkah.',
                    'groups' => [
                        'The Hijrah' => [
                            'The pledges of Aqabah',
                            'The plan against the Prophet ﷺ and the night of the Hijrah',
                            'The cave of Thawr: the Prophet ﷺ and Abu Bakr (RA)',
                            'Arrival in Madinah and the first mosque',
                            'The brotherhood between the Muhajirun and the Ansar',
                            'The Constitution of Madinah',
                            'The change of the Qibla',
                        ],
                        'Battles and treaties' => [
                            'Why the Prophet ﷺ fought: the reasons behind his battles',
                            'The Battle of Badr',
                            'The Battle of Uhud',
                            'The Battle of the Trench',
                            'The Treaty of Hudaybiyyah',
                            'The letters of the Prophet ﷺ to kings and rulers',
                            'The conquest of Makkah',
                            'The Battle of Hunayn and the siege of Taif',
                            'The expedition to Tabuk',
                            'The Year of Delegations',
                        ],
                        'Life of the community' => [
                            'The Mosque of the Prophet ﷺ and the People of the Bench',
                            'How the Prophet ﷺ settled disputes in Madinah',
                            'The Prophet ﷺ and the Jewish tribes of Madinah',
                            'The hypocrites in Madinah',
                        ],
                    ],
                ],
                [
                    'name' => 'His Character, Manners and Daily Life',
                    'description' => 'How the Prophet ﷺ behaved with people at home, at work and in worship.',
                    'groups' => [
                        'His character' => [
                            'His honesty and trustworthiness',
                            'His mercy and kindness',
                            'His humility',
                            'His patience when he was harmed',
                            'How he ﷺ forgave his enemies',
                            'His generosity',
                            'His courage',
                            'His sense of humour and gentle jokes',
                        ],
                        'His daily life' => [
                            'How he ﷺ ate, dressed and slept',
                            'How he ﷺ helped in his home',
                            'His worship by night and day',
                            'How he ﷺ treated children',
                            'How he ﷺ treated the elderly, the poor and the servants',
                            'How he ﷺ treated animals and nature',
                            'How he ﷺ spoke and listened',
                            'What is known about how he ﷺ looked',
                            'His ﷺ food, health and medicine',
                        ],
                        'His teaching' => [
                            'How he ﷺ taught his Companions',
                            'How he ﷺ answered difficult questions',
                            'How he ﷺ corrected mistakes with kindness',
                        ],
                    ],
                ],
                [
                    'name' => 'His Household and Family',
                    'description' => 'The Mothers of the Believers, his children and the family of the Prophet ﷺ.',
                    'groups' => [
                        'His wives, the Mothers of the Believers' => [
                            'The Mothers of the Believers (RA): an overview',
                            'Khadijah bint Khuwaylid (RA)',
                            'Sawdah bint Zam\'ah (RA)',
                            'Aisha bint Abu Bakr (RA)',
                            'Hafsah bint Umar (RA)',
                            'Zaynab bint Khuzaymah (RA)',
                            'Umm Salamah (RA)',
                            'Zaynab bint Jahsh (RA)',
                            'Juwayriyyah bint al-Harith (RA)',
                            'Umm Habibah (RA)',
                            'Safiyyah bint Huyayy (RA)',
                            'Maymunah bint al-Harith (RA)',
                            'Maria al-Qibtiyyah (RA) and the birth of Ibrahim',
                        ],
                        'His children and grandchildren' => [
                            'The children of the Prophet ﷺ: an overview',
                            'Fatimah al-Zahra (RA)',
                            'Zaynab, Ruqayyah and Umm Kulthum (RA)',
                            'The sons of the Prophet ﷺ: Qasim, Abdullah and Ibrahim',
                            'Hasan and Husayn (RA)',
                        ],
                        'The wider family' => [
                            'Who are the Ahl al-Bayt, the family of the Prophet ﷺ?',
                            'Hamzah (RA), the uncle of the Prophet ﷺ',
                            'Abbas (RA), the uncle of the Prophet ﷺ',
                            'Abu Talib: what is known about the uncle who protected him ﷺ',
                            'Abu Lahab and his wife: why the Quran names them',
                        ],
                    ],
                ],
                [
                    'name' => 'His Companions (RA)',
                    'description' => 'The men and women who lived with the Prophet ﷺ and carried his message.',
                    'groups' => [
                        'Understanding the Companions' => [
                            'Who are the Companions (Sahabah)?',
                            'The Muhajirun and the Ansar',
                            'The ten promised Paradise',
                            'The People of Badr and the People of the Pledge of Ridwan',
                            'The Companions in the Quran and the Sunnah',
                            'How Sunni and Shia Muslims view the Companions',
                            'The disagreements among the Companions and how Muslims speak about them',
                        ],
                        'The four rightly guided caliphs' => [
                            'Abu Bakr as-Siddiq (RA)',
                            'Umar ibn al-Khattab (RA)',
                            'Uthman ibn Affan (RA)',
                            'Ali ibn Abi Talib (RA)',
                        ],
                        'Well-known Companions' => [
                            'Bilal ibn Rabah (RA)',
                            'Salman al-Farisi (RA)',
                            'Abu Dharr al-Ghifari (RA)',
                            'Khalid ibn al-Walid (RA)',
                            'Mus\'ab ibn Umayr (RA)',
                            'Abdullah ibn Abbas (RA)',
                            'Abdullah ibn Mas\'ud (RA)',
                            'Abu Hurairah (RA)',
                            'Zayd ibn Thabit (RA)',
                            'Muadh ibn Jabal (RA)',
                            'Anas ibn Malik (RA)',
                            'Abu Ubaydah ibn al-Jarrah (RA)',
                        ],
                        'Women Companions' => [
                            'Famous women Companions (RA)',
                            'Asma bint Abu Bakr (RA)',
                            'Nusaybah bint Ka\'b (RA), who defended the Prophet ﷺ at Uhud',
                            'Umm Sulaym (RA)',
                        ],
                    ],
                ],
                [
                    'name' => 'His Miracles and Signs',
                    'description' => 'What Muslims mean by a miracle, the miracles reported about the Prophet ﷺ and how scholars judge them.',
                    'groups' => [
                        'Understanding miracles' => [
                            'What is a miracle in Islam?',
                            'The Quran as the greatest miracle of the Prophet ﷺ',
                            'How scholars check the reports of miracles',
                        ],
                        'Reported miracles and signs' => [
                            'The splitting of the moon',
                            'Water flowing from his ﷺ fingers',
                            'The weeping tree trunk',
                            'Food that grew by blessing',
                            'Healing by the permission of Allah (SWT)',
                            'The Prophet ﷺ foretold things that later came true',
                            'The dream visions of the Prophet ﷺ',
                        ],
                        'Signs from earlier scriptures' => [
                            'What do Muslims say about the Prophet ﷺ in the Torah and the Gospel?',
                            'The Christian monk Bahira and the signs of prophethood',
                            'Why did some people of earlier scriptures accept Islam?',
                        ],
                    ],
                ],
                [
                    'name' => 'His Final Years, Farewell and Legacy',
                    'description' => 'The Farewell Pilgrimage, the last days of the Prophet ﷺ and what he left behind.',
                    'groups' => [
                        'The last years' => [
                            'The Farewell Pilgrimage',
                            'The Farewell Sermon and its main messages',
                            'Ghadir Khumm: what happened and how Sunni and Shia Muslims understand it',
                            'The completion of the religion: "Today I have perfected your religion"',
                        ],
                        'The passing of the Prophet ﷺ' => [
                            'His illness and his last days',
                            'The death of the Prophet ﷺ and the words of Abu Bakr (RA)',
                            'His burial and the Prophet\'s Mosque',
                        ],
                        'His legacy' => [
                            'What the Prophet ﷺ left behind',
                            'The names and titles of the Prophet ﷺ',
                            'Why Muslims love and respect the Prophet ﷺ',
                            'How the message spread after his death',
                            'The Prophet ﷺ as a role model for all times',
                        ],
                    ],
                ],
            ],
        ],

        // =====================================================================================================
        [
            'name' => 'Hadith and Sunnah Index',
            'icon' => 'scroll',
            'description' => 'What the Sunnah is, how hadith were kept and checked, and how to understand them well.',
            'topics' => [
                [
                    'name' => 'What the Sunnah Is and Why It Matters',
                    'description' => 'The meaning of Sunnah and hadith, and why Muslims follow the example of the Prophet ﷺ.',
                    'groups' => [
                        'The basics' => [
                            'What is the Sunnah?',
                            'What is a hadith?',
                            'Sunnah, hadith and seerah: what is the difference?',
                            'The types of Sunnah: sayings, actions and approvals',
                            'Is the Sunnah a form of revelation?',
                        ],
                        'Why Muslims follow the Sunnah' => [
                            'Obeying the Messenger ﷺ: what the Quran says',
                            'How the Sunnah explains the Quran: examples',
                            'The Prophet ﷺ as the example to follow',
                        ],
                        'Different views' => [
                            'Quran-only views: what they say and how scholars reply',
                            'The Sunnah in the four Sunni schools and in the Shia view',
                        ],
                    ],
                ],
                [
                    'name' => 'The Great Hadith Collections',
                    'description' => 'How hadith were remembered and written down, and the famous books that hold them.',
                    'groups' => [
                        'How hadith were kept' => [
                            'How hadith were remembered and written in the time of the Companions',
                            'The writing of hadith by the Followers (Tabi\'un)',
                            'Why hadith were gathered in books: the age of the collections',
                        ],
                        'The famous collections' => [
                            'Sahih al-Bukhari: the book and its author',
                            'Sahih Muslim: the book and its author',
                            'The four Sunan: Abu Dawud, al-Tirmidhi, al-Nasa\'i and Ibn Majah',
                            'The Muwatta of Imam Malik',
                            'The Musnad of Imam Ahmad',
                            'Riyad as-Salihin and the Forty Hadith of an-Nawawi',
                            'Shia hadith collections: al-Kafi and others',
                            'Other well-known hadith books',
                        ],
                        'Using the books' => [
                            'How to read a hadith reference such as "Bukhari 1234"',
                            'How the method of Imam Bukhari differs from that of Imam Muslim',
                            'Where to find hadith in your own language',
                        ],
                    ],
                ],
                [
                    'name' => 'How a Hadith Is Graded',
                    'description' => 'What authentic, good and weak mean, and how scholars decide.',
                    'groups' => [
                        'Grades of hadith' => [
                            'What do sahih, hasan and daif mean?',
                            'The conditions of an authentic hadith',
                            'Mutawatir and ahad hadith',
                            'Hadith qudsi: what is it?',
                            'Marfu, mawquf and maqtu: sayings of the Prophet ﷺ, the Companions and the Followers',
                            'Mursal and other broken chains: a simple guide',
                        ],
                        'Using graded hadith' => [
                            'Can a weak hadith be used? What scholars say',
                            'Why scholars sometimes grade the same hadith differently',
                            'Can an ordinary Muslim check the grade of a hadith?',
                        ],
                    ],
                ],
                [
                    'name' => 'Narrators, Chains and the Science of Hadith',
                    'description' => 'How the chains of narrators were tested, and the scholars who did it.',
                    'groups' => [
                        'The science' => [
                            'What is a chain of narrators (isnad)?',
                            'The text of a hadith (matn) and how it is tested',
                            'The science of narrators (Ilm al-Rijal)',
                            'How scholars tested a narrator\'s memory and honesty',
                            'Why chains of narrators are special to Islam',
                        ],
                        'The people' => [
                            'Famous hadith scholars: Bukhari, Muslim, al-Tirmidhi, Ibn Hajar and an-Nawawi',
                            'Abu Hurairah (RA), the Companion who narrated the most, and the questions about him',
                            'Women narrators of hadith: Aisha (RA) and others',
                        ],
                        'Questions about hadith' => [
                            'Western academic doubts about hadith: what they say and how Muslim scholars reply',
                            'Is hadith checking only for experts? Tools for the common reader',
                        ],
                    ],
                ],
                [
                    'name' => 'Famous Hadith Explained',
                    'description' => 'The hadith every Muslim hears, explained in plain words.',
                    'groups' => [
                        'Foundations of faith and action' => [
                            'The Hadith of Jibreel: Islam, Iman and Ihsan',
                            '"Actions are judged by intentions"',
                            '"Religion is sincere advice"',
                            'The hadith of the three men trapped in the cave',
                            'The hadith about the sincerity of the martyr, the scholar and the wealthy man',
                        ],
                        'Character and daily life' => [
                            '"None of you truly believes until he loves for his brother what he loves for himself"',
                            '"The best of you are those who learn the Quran and teach it"',
                            '"A smile for your brother is charity"',
                            '"Let him speak good or keep silent"',
                            '"Make things easy and do not make them hard"',
                            '"The strong believer is better than the weak believer"',
                            '"Paradise lies at the feet of mothers": its source and meaning',
                        ],
                        'Advice of the Prophet ﷺ' => [
                            'The advice of the Prophet ﷺ to Muadh (RA)',
                            'The advice of the Prophet ﷺ to Ibn Abbas (RA): "Guard Allah (SWT) and He will guard you"',
                            '"Be in this world as a stranger or a traveller"',
                        ],
                    ],
                ],
                [
                    'name' => 'Weak and Fabricated Hadith',
                    'description' => 'Sayings people repeat that are not authentic, and how to avoid spreading them.',
                    'groups' => [
                        'Understanding the problem' => [
                            'What is a fabricated (mawdu) hadith and why were they made?',
                            'The warning against lying about the Prophet ﷺ',
                            'Israiliyyat: stories from earlier scriptures in Muslim books',
                            'How to avoid sharing weak hadith on social media',
                        ],
                        'Sayings people often ask about' => [
                            'Is "Seek knowledge even in China" a hadith?',
                            'Is "Love of one\'s country is part of faith" a hadith?',
                            'Is "The differences of my Ummah are a mercy" a hadith?',
                            'Is "Whoever knows himself knows his Lord" a hadith?',
                            'The hadith about the 73 sects: its grade and its meaning',
                            'Are there hadith about the virtue of particular nights and months that are not authentic?',
                        ],
                    ],
                ],
                [
                    'name' => 'Hadith and the Quran Together',
                    'description' => 'How hadith and the Quran work together, and how scholars deal with hard cases.',
                    'groups' => [
                        'How they work together' => [
                            'How hadith explain the Quran: prayer, zakat and Hajj as examples',
                            'The role of hadith in Islamic law',
                            'Abrogation (naskh) in the Quran and the hadith',
                        ],
                        'When a hadith looks difficult' => [
                            'When a hadith seems to disagree with the Quran',
                            'Hadith that seem to disagree with each other and how scholars reconcile them',
                            'Hadith about medicine and nature: how scholars read them',
                            'Difficult hadith: how scholars read them in their context',
                        ],
                        'Hadith in Shia Islam' => [
                            'The sayings of the Prophet ﷺ and the Imams in the Shia tradition',
                        ],
                    ],
                ],
            ],
        ],

        // =====================================================================================================
        [
            'name' => 'Islamic Beliefs Index',
            'icon' => 'compass',
            'description' => 'What Muslims believe and why: faith, angels, messengers, the Last Day, destiny and the main schools of belief.',
            'topics' => [
                [
                    'name' => 'The Six Pillars of Belief',
                    'description' => 'The core of Muslim faith and the difference between Islam, Iman and Ihsan.',
                    'groups' => [
                        'What faith means' => [
                            'What is Iman (faith) in Islam?',
                            'Islam, Iman and Ihsan: the three levels of religion',
                            'The six pillars of belief: an overview',
                            'The five pillars of Islam: a simple overview',
                            'What makes a person a Muslim: the Shahadah',
                            'Belief in the unseen',
                        ],
                        'Faith and action' => [
                            'Does faith go up and down?',
                            'Faith and deeds: how scholars link them',
                            'What is certainty (yaqin) and how does it grow?',
                            'The fruits of faith in daily life',
                        ],
                    ],
                ],
                [
                    'name' => 'Angels and the Unseen World',
                    'description' => 'What Muslims believe about angels, the unseen and the things people often ask about.',
                    'groups' => [
                        'Angels' => [
                            'Who are the angels?',
                            'Jibreel (AS), Mikail (AS), Israfil (AS) and the Angel of Death',
                            'The angels who record our deeds',
                            'Munkar and Nakir: the questioning in the grave',
                            'The angels who guard us and the angels of mercy',
                            'Do angels have free will?',
                            'Harut and Marut',
                        ],
                        'Jinn and Shaytan' => [
                            'What do Muslims believe about the jinn?',
                            'Iblis and Shaytan: who are they and how do they tempt people?',
                            'How to protect yourself from Shaytan',
                        ],
                        'Things people ask about the unseen' => [
                            'What is magic (sihr) in Islam?',
                            'The evil eye and envy',
                            'Possession and ruqyah: what is true and what is superstition?',
                            'Fortune-telling, astrology and horoscopes',
                            'Dreams in Islam: true dreams and bad dreams',
                            'Omens and superstition',
                            'Amulets, charms and lucky objects',
                        ],
                    ],
                ],
                [
                    'name' => 'The Books and the Messengers (AS)',
                    'description' => 'Why Allah (SWT) sent prophets and books, and what Muslims believe about them.',
                    'groups' => [
                        'The messengers' => [
                            'Why did Allah (SWT) send messengers?',
                            'The prophet (Nabi) and the messenger (Rasul): what is the difference?',
                            'The prophets named in the Quran',
                            'The five messengers of strong will',
                            'Why Muslims believe in all the prophets without making a difference',
                            'Were any prophets women? What scholars say',
                            'What Muslims believe about Isa (AS): his birth, his mission and his return',
                            'The end of prophethood: what Muslims believe and why',
                        ],
                        'The books' => [
                            'The Torah, the Psalms, the Gospel and the Scrolls: what Muslims believe about them',
                            'Why Muslims believe earlier scriptures were changed',
                            'Why the Quran is the final and protected revelation',
                        ],
                    ],
                ],
                [
                    'name' => 'The Last Day and Life After Death',
                    'description' => 'What Muslims believe about death, the signs of the Last Day, judgment, Paradise and Hell.',
                    'groups' => [
                        'Death and the grave' => [
                            'Why Muslims believe in life after death',
                            'What happens at the time of death?',
                            'The grave and the life in Barzakh',
                        ],
                        'The signs of the Last Day' => [
                            'The minor signs of the Last Day',
                            'The major signs of the Last Day: an overview',
                            'The Dajjal: who is he?',
                            'The return of Isa (AS)',
                            'Yajuj and Majuj (Gog and Magog)',
                            'The Mahdi: what Sunni and Shia Muslims believe',
                            'The sun rising from the west and the other great signs',
                        ],
                        'Judgment and the afterlife' => [
                            'The trumpet and the resurrection',
                            'The Day of Judgment: the record, the scale and the bridge',
                            'The intercession of the Prophet ﷺ',
                            'Paradise: what Muslims believe about it',
                            'Hell: what Muslims believe about it, and will it last forever?',
                            'What happens to people who never heard the message of Islam?',
                            'What happens to children who die young?',
                        ],
                    ],
                ],
                [
                    'name' => 'Qadar: Decree and Free Will',
                    'description' => 'How Muslims understand Allah\'s (SWT) knowledge, decree and human choice.',
                    'groups' => [
                        'Understanding Qadar' => [
                            'What is Qadar (the divine decree)?',
                            'The four levels of Qadar: knowledge, writing, will and creation',
                            'The Preserved Tablet (Lawh al-Mahfuz)',
                            'If Allah (SWT) knows everything, do we have free will?',
                            'Does dua change the decree?',
                            'Guidance and misguidance: who decides?',
                        ],
                        'Living with Qadar' => [
                            'Why Islam does not teach "do nothing and wait for fate"',
                            'Trust in Allah (SWT) and effort together',
                            'Why do bad things happen if Allah (SWT) decides everything?',
                            'How different schools of belief explain Qadar',
                        ],
                    ],
                ],
                [
                    'name' => 'Major Schools of Belief',
                    'description' => 'The main groups of Muslims and how they differ, explained fairly.',
                    'groups' => [
                        'The big groups' => [
                            'Who are the Sunni, the Shia and the Ibadi Muslims?',
                            'Ahl as-Sunnah wal-Jamaah: who are they?',
                            'The Athari, Ash\'ari and Maturidi schools of belief',
                            'The Mutazilah: who were they?',
                            'The Shia schools: Twelver, Ismaili and Zaydi',
                            'The Ibadi school',
                        ],
                        'Understanding the differences' => [
                            'What do all Muslims agree on?',
                            'Why do Muslims disagree on some matters of belief?',
                            'Differences in belief and differences in law: what is the difference?',
                            'The Kharijites and the extremist groups of history',
                            'Who is a Muslim? How scholars deal with calling someone a disbeliever',
                        ],
                    ],
                ],
                [
                    'name' => 'Faith, Doubt and Disbelief',
                    'description' => 'What weakens or breaks faith, and what a believer does with doubts.',
                    'groups' => [
                        'Disbelief and hypocrisy' => [
                            'What is disbelief (kufr)? The kinds of kufr',
                            'Shirk: major and hidden',
                            'Hypocrisy (nifaq): the signs and the danger',
                            'Acts that scholars say take a person out of Islam',
                            'Can a Muslim commit a major sin and still be a believer?',
                        ],
                        'Doubts and questions' => [
                            'What should a Muslim do with doubts about faith?',
                            'What is Fitrah, the natural belief in God?',
                            'Innovation (bid\'ah) in belief: what it means',
                            'Should I follow my scholars blindly? The meaning of taqlid',
                        ],
                    ],
                ],
            ],
        ],

        // =====================================================================================================
        [
            'name' => 'Spirituality and Character Index',
            'icon' => 'heart',
            'description' => 'The inner life of a Muslim: sincerity, patience, repentance, dua, good manners and a clean heart.',
            'topics' => [
                [
                    'name' => 'Sincerity, Intention and the Heart',
                    'description' => 'Doing everything for the sake of Allah (SWT) and cleaning the heart of its diseases.',
                    'groups' => [
                        'Sincerity' => [
                            'What is sincerity (ikhlas)?',
                            'Niyyah: the role of intention in every action',
                            'Riya: showing off, and how to avoid it',
                            'Ihsan: worshipping as if you see Allah (SWT)',
                            'Khushu: humility and focus in worship',
                        ],
                        'The diseases of the heart' => [
                            'The diseases of the heart: an overview',
                            'Pride and arrogance (kibr)',
                            'Envy (hasad) and how to cure it',
                            'Love of this world and detachment from it (zuhd)',
                            'The sound heart (qalb salim) in the Quran',
                        ],
                    ],
                ],
                [
                    'name' => 'Patience, Gratitude and Trust in Allah (SWT)',
                    'description' => 'How to face hardship and blessing, and how Islam looks at worry and sadness.',
                    'groups' => [
                        'Patience and gratitude' => [
                            'Sabr: the kinds of patience',
                            'Patience in loss and illness',
                            'Shukr: how to be thankful',
                            'Contentment (rida) with the decree of Allah (SWT)',
                            'Hope and fear (raja and khawf): the two wings of the believer',
                            'Tawakkul: how to trust Allah (SWT) the right way',
                        ],
                        'Hard times' => [
                            'Hardship as a test: how Muslims understand suffering',
                            'Anxiety and depression in the light of Islam',
                            'Grief: how a Muslim mourns',
                            'The advice of the Prophet ﷺ for worry and sadness',
                            'Loneliness and stress: finding peace in faith',
                        ],
                    ],
                ],
                [
                    'name' => 'Repentance and Forgiveness',
                    'description' => 'Turning back to Allah (SWT) after a mistake, and forgiving other people.',
                    'groups' => [
                        'Repentance' => [
                            'What is tawbah (repentance) and how is it done?',
                            'The conditions of sincere repentance',
                            'Does Allah (SWT) forgive every sin?',
                            'Istighfar: asking forgiveness',
                            'What if I keep falling into the same sin?',
                            'Major and minor sins: what is the difference?',
                            'Why losing hope in the mercy of Allah (SWT) is wrong',
                            'Starting again after years of neglecting religion',
                        ],
                        'Forgiveness' => [
                            'Should I confess my sins to someone? The Islamic view',
                            'Forgiving others: the example of the Prophet ﷺ',
                            'Asking forgiveness from people I have wronged',
                        ],
                    ],
                ],
                [
                    'name' => 'Dua and Remembrance of Allah (SWT)',
                    'description' => 'How to call upon Allah (SWT) and keep Him in mind through the day and night.',
                    'groups' => [
                        'Dua' => [
                            'What is dua and why does it matter?',
                            'The manners of dua: best times and places',
                            'Why does Allah (SWT) not answer my dua?',
                            'Famous supplications of the Prophet ﷺ',
                            'Dua for protection: Ayat al-Kursi and the last verses of Surah al-Baqarah',
                            'Everyday duas: food, sleep, travel and entering the home',
                            'Istikharah: the prayer for guidance, how and when',
                        ],
                        'Dhikr and special times' => [
                            'Morning and evening remembrance (adhkar)',
                            'Dhikr after prayer: tasbih, tahmid and takbir',
                            'Reciting the Quran and reflecting on it (tadabbur)',
                            'Sending blessings (salawat) on the Prophet ﷺ',
                            'Night prayer (tahajjud) and the last third of the night',
                            'Laylat al-Qadr and the best nights: how to spend them',
                            'The best days of the year: the first ten days of Dhul Hijjah and the day of Arafah',
                        ],
                    ],
                ],
                [
                    'name' => 'Good Character and Manners (Akhlaq)',
                    'description' => 'The manners of a Muslim with people, in speech and in everyday life.',
                    'groups' => [
                        'Good character' => [
                            'What is good character in Islam?',
                            'Honesty, truthfulness and keeping promises',
                            'Kindness and mercy towards people',
                            'Controlling anger',
                            'Modesty (haya)',
                            'Humility and simplicity',
                            'Justice, even against yourself',
                        ],
                        'Speech and manners' => [
                            'Greeting with salam and the manners of meeting',
                            'Backbiting, gossip and slander',
                            'The manners of speech, humour and arguing',
                            'The rights of neighbours',
                            'The manners of eating, visiting and sneezing',
                            'Cleanliness and the appearance of a Muslim',
                            'Respecting different opinions',
                            'Muslim manners on social media',
                        ],
                    ],
                ],
                [
                    'name' => 'Purifying the Soul (Tazkiyah)',
                    'description' => 'The journey of improving oneself, and the scholars who wrote about it.',
                    'groups' => [
                        'Working on yourself' => [
                            'What is tazkiyah, the purification of the soul?',
                            'The three levels of the self: commanding, blaming and at peace',
                            'Fighting the desires (hawa)',
                            'Taking account of yourself (muhasabah)',
                            'Remembering death',
                            'Keeping good company',
                        ],
                        'Paths and people' => [
                            'What is Sufism (tasawwuf)? Its history and the different views about it',
                            'Imam al-Ghazali and the revival of religious sciences',
                            'Ibn al-Qayyim and the diseases and cures of the heart',
                            'Hasan al-Basri and Rabia al-Adawiyyah',
                        ],
                        'Spiritual lessons of worship' => [
                            'The spiritual lessons of Ramadan',
                            'The spiritual lessons of Hajj',
                            'The spiritual lessons of prayer',
                        ],
                    ],
                ],
                [
                    'name' => 'Ethics in Family and Community Life',
                    'description' => 'How faith shapes behaviour at home, at work and in society.',
                    'groups' => [
                        'Family' => [
                            'Honouring parents (birr al-walidayn)',
                            'Keeping family ties',
                            'Being a good spouse: kindness and respect',
                            'Raising children with good manners',
                            'Caring for the elderly and the sick',
                        ],
                        'Community' => [
                            'Friendship and brotherhood in faith',
                            'Helping the poor and the orphan',
                            'Treating non-Muslim neighbours and colleagues',
                            'Honesty at work: work as worship',
                            'Looking after the environment: the human as a steward',
                            'Using time well',
                            'Spending wisely: avoiding waste and stinginess',
                            'Standing against oppression and for justice',
                        ],
                    ],
                ],
            ],
        ],

        // =====================================================================================================
        [
            'name' => 'Common Questions and Misconceptions',
            'icon' => 'question',
            'description' => 'Honest answers to the questions people ask most about Islam, and to the mistaken ideas they often hear.',
            'topics' => [
                [
                    'name' => 'Islam Basics for Beginners',
                    'description' => 'A simple start for anyone who is curious, or who has just become a Muslim.',
                    'groups' => [
                        'Understanding Islam' => [
                            'What is Islam? A simple introduction',
                            'What do Muslims believe? Ten simple points',
                            'What do the words Islam, Muslim and Allah mean?',
                            'Is Islam only for Arabs?',
                            'How many Muslims are there and where do they live?',
                            'Who was Prophet Muhammad ﷺ? A short story',
                            'What is the Quran, and how do I start reading it?',
                            'Islamic words explained: halal, haram, sunnah, fard and others',
                        ],
                        'Becoming a Muslim' => [
                            'How do I become a Muslim?',
                            'What should I do first after becoming a Muslim?',
                            'Do I need to change my name or my clothes?',
                            'How do I tell my family that I became a Muslim?',
                            'How can a beginner learn to pray?',
                            'Where do I find a mosque, a teacher and good friends?',
                            'Do I have to learn Arabic to be a good Muslim?',
                        ],
                    ],
                ],
                [
                    'name' => 'Questions about Allah (SWT)',
                    'description' => 'The questions people ask about God in Islam.',
                    'groups' => [
                        'Who is Allah (SWT)?' => [
                            'Is Allah (SWT) the same God as the God of Jews and Christians?',
                            'Why do Muslims say "Allah" and not "God"?',
                            'Where is Allah (SWT)?',
                            'Does Allah (SWT) have a body, hands and a face?',
                            'Who created Allah (SWT)?',
                            'Why can we not see Allah (SWT)?',
                            'Is Allah (SWT) male?',
                        ],
                        'Why questions' => [
                            'Why did Allah (SWT) create us?',
                            'Does Allah (SWT) need our worship?',
                            'Why does Allah (SWT) allow suffering and evil?',
                            'Is Allah (SWT) merciful or harsh?',
                            'Why does Allah (SWT) test people?',
                            'Why do some prayers seem unanswered?',
                        ],
                    ],
                ],
                [
                    'name' => 'Questions about the Prophet ﷺ',
                    'description' => 'The hard questions people ask about the life of Prophet Muhammad ﷺ, answered from the sources.',
                    'groups' => [
                        'His marriages' => [
                            'Why did the Prophet ﷺ marry more than one wife?',
                            'What do we know about the age of Aisha (RA) at her marriage?',
                            'Why did the Prophet ﷺ marry Zaynab bint Jahsh (RA)?',
                            'The marriages of the Prophet ﷺ: political, social and personal reasons',
                        ],
                        'War and peace' => [
                            'Was the Prophet ﷺ a warlord?',
                            'Did the Prophet ﷺ spread Islam by the sword?',
                            'What happened to Banu Qurayzah?',
                            'The Prophet ﷺ and the treatment of prisoners of war',
                        ],
                        'The message' => [
                            'Did the Prophet ﷺ write the Quran himself? Was he unable to read and write?',
                            'Was the Prophet ﷺ influenced by Jews and Christians?',
                            'The "Satanic verses": what is the story?',
                            'Claims that the Prophet ﷺ was ill or mad: how do Muslims reply?',
                            'Can the Prophet ﷺ be drawn? Why do Muslims object to pictures of him?',
                        ],
                    ],
                ],
                [
                    'name' => 'Questions about the Quran',
                    'description' => 'Questions about how the Quran was kept, how it is read and what some verses mean.',
                    'groups' => [
                        'Origin and keeping' => [
                            'Was the Quran changed or lost after the Prophet ﷺ?',
                            'Did Uthman (RA) burn other copies of the Quran?',
                            'What do the oldest Quran manuscripts show?',
                            'Was the Quran copied from the Bible?',
                            'Why are there different readings (qira\'at) of the Quran?',
                            'Why was the Quran revealed in Arabic? Are translations the Quran?',
                        ],
                        'Hard questions' => [
                            'Are there contradictions in the Quran?',
                            'The "sword verses": what do they mean?',
                            'Is the Quran against science?',
                            'Scientific claims about the Quran: how should we judge them?',
                            'What does the Quran say about other religions?',
                            'Why do some verses seem harsh? Reading verses in context',
                        ],
                    ],
                ],
                [
                    'name' => 'Jihad, War and Violence',
                    'description' => 'What Islam teaches about fighting, and what it does not.',
                    'groups' => [
                        'Meaning and rules' => [
                            'What does jihad really mean?',
                            'Is jihad the same as holy war?',
                            'What are the rules of war in Islam?',
                            'Does Islam allow killing innocent people?',
                            'Self-defence and resistance in Islam',
                            'Who can declare jihad? The role of the state and scholars',
                        ],
                        'Extremism and terrorism' => [
                            'What does Islam say about terrorism and suicide attacks?',
                            'What are ISIS and Al-Qaeda, and why do scholars reject them?',
                            'Why do some Muslims commit violence in the name of Islam?',
                            'Does Islam command Muslims to fight all non-Muslims?',
                            'Can a fatwa order violence?',
                            'Is Islam violent by nature?',
                        ],
                    ],
                ],
                [
                    'name' => 'Women in Islam',
                    'description' => 'The most common questions about women, answered from the Quran, the Sunnah and the scholars.',
                    'groups' => [
                        'Dress and public life' => [
                            'Does Islam oppress women?',
                            'Why do Muslim women wear the hijab? Is it forced?',
                            'The niqab and the burqa: what do scholars say?',
                            'Can women work, study and lead?',
                            'Can women pray in the mosque or lead prayers?',
                            'Why do men and women sit separately?',
                        ],
                        'Marriage and family' => [
                            'Why does Islam allow a man to have up to four wives?',
                            'Forced and arranged marriage: what does Islam say?',
                            'Divorce in Islam: who has the right?',
                            'Does Islam allow a husband to beat his wife? The meaning of Quran 4:34',
                            'Why is a woman\'s inheritance sometimes half of a man\'s?',
                            'Why is a woman\'s testimony counted differently in some cases?',
                        ],
                        'Harmful customs and rights' => [
                            'Honour killings: are they Islamic?',
                            'Female genital cutting: is it Islamic?',
                            'The rights of women in the seventh century and what changed',
                            'Do women go to Paradise, and what do they get there?',
                        ],
                    ],
                ],
                [
                    'name' => 'Freedom, Apostasy and Blasphemy',
                    'description' => 'Freedom of belief, leaving Islam and respect for the sacred.',
                    'groups' => [
                        'Freedom of belief' => [
                            'Is there freedom of religion in Islam? "No compulsion in religion"',
                            'What does Islam say about leaving Islam?',
                            'Is the punishment for apostasy death? The views of scholars',
                            'Are questions and doubts allowed in Islam?',
                            'Do ex-Muslims face threats, and why?',
                        ],
                        'Speech and the sacred' => [
                            'What is blasphemy, and why do Muslims react so strongly?',
                            'Freedom of speech and respect for religion',
                            'How should Muslims answer insults against the Prophet ﷺ?',
                            'Can a Muslim criticise Islam or Muslims?',
                        ],
                        'Hard social questions' => [
                            'What does Islam teach about homosexuality, and how should Muslims treat people?',
                            'What does Islam say about changing one\'s gender?',
                        ],
                    ],
                ],
                [
                    'name' => 'Islam and Other Religions',
                    'description' => 'How Islam looks at Jews, Christians and people of other faiths.',
                    'groups' => [
                        'People of the Book' => [
                            'Are Jews and Christians disbelievers?',
                            'Can a Muslim marry a Christian or a Jewish woman?',
                            'What does Islam say about Jews and Judaism?',
                            'Why do Muslims reject the Trinity and the divinity of Isa (AS)?',
                            'Do Muslims believe that Isa (AS) was crucified?',
                            'Is Islam a new religion?',
                        ],
                        'Living with others' => [
                            'Is Islam the only way to Paradise? What about good people of other faiths?',
                            'What does Islam teach about Hindus, Buddhists and others?',
                            'Can Muslims have friends of other religions?',
                            'Can Muslims join the celebrations of other religions?',
                            'The People of the Covenant (dhimmis) and the jizyah: what were they?',
                            'Interfaith dialogue in Islam',
                        ],
                    ],
                ],
                [
                    'name' => 'Islam in the Modern World',
                    'description' => 'Islam and today\'s questions: law, rights, science, money and life in the West.',
                    'groups' => [
                        'Law and society' => [
                            'Is Islam compatible with democracy and human rights?',
                            'What is Sharia law, and will it be forced on everyone?',
                            'Are Islamic punishments (hudud) cruel?',
                            'Does Islam allow slavery?',
                            'Can a Muslim live in a non-Muslim country?',
                            'Why are many Muslim countries poor or in conflict?',
                        ],
                        'Daily life' => [
                            'Does Islam forbid music, art and photography?',
                            'Why is interest (riba) forbidden, and how do Muslims buy a home?',
                            'Birthdays, Valentine\'s Day and New Year: what do scholars say?',
                            'Tattoos, dogs and other things people ask about',
                            'Is Islam against science and evolution?',
                            'Why is Islam one of the fastest-growing religions?',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
