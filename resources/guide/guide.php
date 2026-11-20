<?php

/**
 * The Help guide of the admin. One entry for each admin screen, and a few general topics.
 *
 * - 'classes': the screen(s) this entry explains (a test makes sure every screen of the admin has an entry, so the guide cannot fall behind).
 * - 'access': who sees the entry: null = every staff person, a screen key (articles, import, ...) = a person who may use that screen,
 *             'publish' = a moderator or the super user, 'superuser' = the super user only.
 * Write in simple English. When a screen changes, change its entry here.
 */

use App\Filament\Pages\AiImport;
use App\Filament\Pages\Backups;
use App\Filament\Pages\ChallengeSettings;
use App\Filament\Pages\LinkCheckerPage;
use App\Filament\Pages\MoveFromOldSite;
use App\Filament\Pages\SiteSettings;
use App\Filament\Pages\SlideViewerSettings;
use App\Filament\Resources\AiPrompts\AiPromptResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Challenge\FactStatementResource;
use App\Filament\Resources\Challenge\QuizPassageResource;
use App\Filament\Resources\Challenge\QuotePassageResource;
use App\Filament\Resources\Challenge\QuranChapterResource;
use App\Filament\Resources\Challenge\ScripturePassageResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\DidYouKnow\DidYouKnowResource;
use App\Filament\Resources\Explainers\ExplainerResource;
use App\Filament\Resources\History\ActivityResource;
use App\Filament\Resources\Newsletter\NewsletterIssueResource;
use App\Filament\Resources\Newsletter\NewsletterSubscriberResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Filament\Resources\SearchSynonyms\SearchSynonymResource;
use App\Filament\Resources\Social\SocialLinkResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\Users\UserResource;

return [
    // ================= General topics =================
    [
        'key' => 'start', 'title' => 'Start here', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'The admin is where you write, check and publish the content of InfoFluxes. The menu on the left has three parts: Content (what visitors read), Challenge yourself (the tests), and Settings (how the site works). You only see the screens your role may use.',
        'steps' => [
            'Open the Dashboard first. "Waiting for you" shows what needs a decision. "Needs attention" shows problems.',
            'To add an article, the fastest way is the AI import. See "From idea to published article" below.',
            'When you are not sure, look in this Help. Press the Help button at the top of any screen.',
        ],
        'tips' => ['Nothing you change is lost easily: deleted items go to the Trash, and the super user can see and undo changes in History.'],
    ],
    [
        'key' => 'daily-work', 'title' => 'From idea to published article', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'The usual way to make an article with the help of an AI chat. A person always checks it before it goes live.',
        'steps' => [
            'Go to AI import. Choose "A researched article".',
            'To fill a planned index entry: type its index ID (for example AL-2.1.001) in "Index entry to fill", or press "Write with AI help" on the entry in the Articles list.',
            'Write the title or question, check the category, the number of words and how detailed it should be. Press "Copy the prompt".',
            'Open a new chat in an AI (ChatGPT, Gemini or Claude) with web search on. Paste the prompt. Do not add anything.',
            'Copy the AI answer from its code box (use the Copy button of the box). Paste it into Step 2 and press Check.',
            'Read the result. Fix any red problem and press Check again. Amber notes and the "Please check" list are things to look at.',
            'Press "Save as drafts". The article gets the status "Written, waiting for review".',
            'Open it in Articles. In "Review before publishing", check every verse, hadith, source and quotation yourself, then tick each box.',
            'Write your name in "Reviewer name", set the status to Published and save. Only a moderator or the super user can publish.',
        ],
        'tips' => [
            'If the answer is rejected, the message says why. Paste it again after the AI fixes it, or ask the AI: "Answer in the exact shape of the prompt".',
            'The word notes the AI suggested are saved as drafts. Read them in Explainers and publish the good ones.',
        ],
    ],
    [
        'key' => 'statuses', 'title' => 'Statuses explained', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'Every article has a status. It tells where it is on the way to the reader.',
        'steps' => [
            'Draft: not finished. A blank index entry is a Draft. Visitors do not see it.',
            'Written, waiting for review: the text came from the AI import and a person has not checked it yet. Visitors do not see it.',
            'In review: an article sent in from the site, waiting for a moderator.',
            'Published: visitors can read it. An AI-written article needs a reviewer name and all review boxes ticked first.',
            'Archived: hidden from visitors, kept for the record.',
        ],
        'tips' => ['A date in the future in "Publish date" keeps a published article hidden until that day.'],
    ],
    [
        'key' => 'ids', 'title' => 'Codes and IDs', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'Everything has a short permanent code. It never changes, so you can use it to search or to talk about an item.',
        'steps' => [
            'A- article, S- visual story, D- Did You Know?, E- explainer, C- category, K- contributor, P- page.',
            'An index ID such as AL-2.1.001 is the place of an article in your subject index. It stays with the article when the AI text is saved into it.',
            'Type a code in the site search box (for example A-42) to go straight to it. The address /id/A-42 also works.',
        ],
        'tips' => [],
    ],
    [
        'key' => 'roles', 'title' => 'Who can do what', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'There are four roles. The super user can also tick extra screens for a person.',
        'steps' => [
            'Super user: everything, including People, Site settings, Backups, History and Redirects.',
            'Moderator: Articles, Visual Stories, Categories, Comments and Explainers (and more if ticked). A moderator can publish, archive and feature items.',
            'Author: Articles and Visual Stories, only their own. An author can save drafts and send them for review, but cannot publish.',
            'Member: no admin. A member signs in on the site (or with Google or Facebook). After signing up a member must confirm their email address with a link we send. Then they can comment, like, save and send in an article. Until then they can only read.',
        ],
        'tips' => ['Only the super user can open AI prompts, because they are the rules every AI article follows.'],
    ],
    [
        'key' => 'verses', 'title' => 'Verses and hadith in an article', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'A Quran verse shows in a blue block and a hadith in a green block, with the Arabic, the English and a reference line.',
        'steps' => [
            'The AI import makes these blocks by itself from the [QURAN] and [HADITH] parts of the AI answer.',
            'Check each block against Quran.com or Sunnah.com: the surah and verse, the words, the translation, the hadith number and grade.',
            'Never change a quotation. If a word is wrong, find the right text instead of editing it by feel.',
        ],
        'tips' => ['If you save an article in the editor, the blocks keep their colours: the site puts the style back by itself.'],
    ],
    [
        'key' => 'word-notes', 'title' => 'Word notes: @[word]', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'A word note explains a hard word, person or place. In an article, write @[Taghut] where the word should be tappable. A reader taps it and sees the note.',
        'steps' => [
            'Make the note once in Explainers, and publish it.',
            'In the article text, write @[the word]. To show different words than the note\'s word, write @[the word|the words to show].',
            'Only the first time a word appears on a page is tappable. A word with no published note just shows its words.',
        ],
        'tips' => ['The AI import marks the words for you and makes the notes as drafts.'],
    ],
    [
        'key' => 'safety', 'title' => 'If something goes wrong', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'Mistakes can be put right.',
        'steps' => [
            'Deleted something? Open its list, set the filter to Trash and restore it.',
            'Changed words by mistake? The super user opens History, finds the line and presses "Put the old words back".',
            'Renamed an address and a link broke? Old addresses keep working by themselves. The super user can see them in Redirects.',
            'Something big went wrong? The super user can download a backup in Backups. Ask for help before restoring.',
        ],
        'tips' => ['A backup is made every night if the scheduled task is set up.'],
    ],
    [
        'key' => 'plain-language', 'title' => 'Writing for ordinary readers', 'group' => 'General', 'classes' => [], 'access' => null,
        'what' => 'InfoFluxes is for ordinary people, not for scholars. Texts must be easy to read but never less than accurate.',
        'steps' => [
            'Short sentences (about 15 words), everyday words, every hard word explained the first time.',
            'Say who holds a belief: "Muslims believe", "historians say". Do not turn a belief into a fact.',
            'Salutations after names: ﷺ after Prophet Muhammad, (AS) after other prophets, (RA) after companions, (SWT) after Allah.',
            'Every claim needs a source you opened. The AI import warns about long sentences and missing sources.',
        ],
        'tips' => [],
    ],

    // ================= Content =================
    [
        'key' => 'articles', 'title' => 'Articles', 'group' => 'Content', 'classes' => [ArticleResource::class], 'access' => 'articles',
        'what' => 'All articles, including the blank index entries. A moderator sees all; an author sees only their own.',
        'steps' => [
            'Use the filters: Status (try "Written, waiting for review"), In category (it includes everything below the category), Featured, Has broken sources.',
            '"New article" writes one by hand. For AI text use AI import instead.',
            '"Write with AI help" on a blank index entry opens the AI import for that entry.',
            '"Check source links" tests the links of one article now.',
            '"More pictures" in the article form: the pictures are shown inside the text, under the main headings (the ## parts), in the order they are listed, one under each heading. To choose the place, type a number in "Put it in section number" (1 = under the first main heading). A picture that has no place, for example when there are more pictures than headings, stays in a small Pictures strip at the bottom.',
            'An article must be placed in a last-level category (one with no sub-categories). Use the Overview category of a heading for an overview article.',
        ],
        'tips' => ['The "Review before publishing" part appears on articles written with AI help.', '"Clone" makes a copy as a draft.'],
    ],
    [
        'key' => 'stories', 'title' => 'Visual Stories', 'group' => 'Content', 'classes' => [StoryResource::class], 'access' => 'stories',
        'what' => 'Stories told in pictures: chapters made of slides, each with a picture and words.',
        'steps' => ['Press "New", add a cover picture, then chapters, then slides.', 'Slides can have audio for the Listen button (it plays only when approved).', 'A story has its own code, S-.'],
        'tips' => ['Pictures are made small and fast by the site (WebP, under 300 KB).'],
    ],
    [
        'key' => 'categories', 'title' => 'Categories', 'group' => 'Content', 'classes' => [CategoryResource::class], 'access' => 'categories',
        'what' => 'The tree of the site. Top-level categories are the main menu. Articles go only in the last level of the tree.',
        'steps' => [
            '"Sits inside" puts a category below another one. A category that already holds articles cannot get sub-categories until its articles are moved.',
            'Hide a category with "Show on the site" off.',
            'A section that has many items (for example Stories in pictures) can show its sub-categories as tabs: switch on "Show its sub-categories as tabs" in that category. Its page then shows All and one tab for each sub-category, with a count, and a tab lists only its own items, 12 at a time. To add a tab, add a sub-category and file items in it. A sub-category with no items has no tab.',
            'Every category that has sub-categories also gets an "All articles" tab next to "Browse". It lists every published article and story in the category and all the sub-categories below it, with a search box, a sort choice and one button for each sub-category. On the category cards the number (for example "42 articles") counts the same things and opens that tab. Nothing to set up; drafts are never counted.',
            'To keep the tree short (3 levels), a deep section can be shrunk with one command: php artisan categories:flatten about-islam (it only counts), then make a backup and run it again with --apply. Articles in the deeper categories move up to their level 3 category and keep the old names in the Subtopic box of the article. The old categories go to the Trash and their old web address leads to the category that took over.',
            'Changing the web address makes a redirect by itself.',
        ],
        'tips' => ['Moving articles out of a heading: the Overview category is made for them.'],
    ],
    [
        'key' => 'comments', 'title' => 'Comments', 'group' => 'Content', 'classes' => [CommentResource::class], 'access' => 'comments',
        'what' => 'The discussion under articles, and the "Audience" under stories. A member\'s comment waits here until you approve it.',
        'steps' => ['The list opens on "Waiting for approval". Read, then approve or hide.', 'Replies by staff are shown at once.'],
        'tips' => ['The column "Article or story" says where a comment was written. A story comment shows the word Story under the name.'],
    ],
    [
        'key' => 'dyk', 'title' => 'Did You Know?', 'group' => 'Content', 'classes' => [DidYouKnowResource::class], 'access' => 'facts',
        'what' => 'Short facts, like an FAQ. Each one needs a source to be shown.',
        'steps' => ['Write a short title and the fact in plain words.', 'Add the source. Without a source the fact is not shown.', 'Publish it. Visitors see all published facts on one page.'],
        'tips' => [],
    ],
    [
        'key' => 'ai-import', 'title' => 'AI import', 'group' => 'Content', 'classes' => [AiImport::class], 'access' => 'import',
        'what' => 'Turns the answer of an AI chat into a draft article, with its sections, sources, verse blocks and word notes.',
        'steps' => [
            'Step 1 builds the prompt from your choices. Step 2 is where you paste the AI answer and press Check. Step 3 shows what was found.',
            'Check saves nothing. It tests the format, the category, the sources (does each link open?), every Quran reference, how easy the text is to read, and the verse and hadith blocks.',
            'Save as drafts makes the article with the status "Written, waiting for review". Nothing is published from here.',
            'The answer also carries META TITLE (at most 60 letters) and META DESCRIPTION (120 to 155 letters): what Google shows. They are saved in the Meta title and Meta description boxes of the article (the Page title box is left as it is). "How it will look in Google" shows a preview. Without these two lines the page uses its own title and short text, so nothing breaks.',
            'If you already have an article text (your own writing, or from your own prompt), open "I already have an article text" in Step 1 and paste it there. The prompt then becomes a "put my text into shape" prompt: the AI only cuts your text into ## headings, keeps every fact, verse and quotation, adds no new claim or source, lists only the sources your text names, and puts every doubt into PLEASE CHECK. Copy it into the AI, paste the answer in Step 2, and press Check. Clear the text to go back to the normal prompt.',
            '"Also ask for word notes" makes the AI suggest hard words. They are saved as draft notes in Explainers.',
        ],
        'tips' => ['If the AI changes the shape of the answer, ask it again with: "Answer in the exact shape of the prompt".', 'Copy the answer from the code box with its Copy button, so the ## marks are kept.'],
    ],
    [
        'key' => 'ai-prompts', 'title' => 'AI prompts', 'group' => 'Content', 'classes' => [AiPromptResource::class], 'access' => 'superuser',
        'what' => 'The rules the AI must follow: the Master rules for every article and one specialist prompt for each kind of subject.',
        'steps' => ['Modify the words of a prompt. "Reset to the original" brings back the first version.', 'Each specialist has "Match words": when a category path holds one of them, that prompt is suggested.'],
        'tips' => ['Only the super user can change them. Change them carefully: every new AI article follows them.'],
    ],
    [
        'key' => 'link-checker', 'title' => 'Link checker', 'group' => 'Content', 'classes' => [LinkCheckerPage::class], 'access' => 'publish',
        'what' => 'Checks that the source links of all articles still open. A few are checked every hour by themselves.',
        'steps' => [
            '"Check all links now" checks every link, a few at a time. Keep the page open.',
            'Broken: find a new link and fix the article. Blocked: the website refuses automatic checks. Open the link yourself, and if it is fine, press "I opened it: it is fine".',
            '"Ignore" hides a link you do not want to hear about.',
        ],
        'tips' => ['A broken link never hides an article.'],
    ],
    [
        'key' => 'search-synonyms', 'title' => 'Search synonyms', 'group' => 'Content', 'classes' => [SearchSynonymResource::class], 'access' => 'publish',
        'what' => 'Words the search treats as the same, so a visitor who types "Koran" also finds "Quran".',
        'steps' => ['"New group of words" needs at least two words.', '"Try a search" shows what the search would also look for.', 'Groups marked off are suggestions (such as Musa and Moses). Switch one on only if the two words mean the same thing to you.'],
        'tips' => ['A word added from a group must have at least 4 letters.'],
    ],
    [
        'key' => 'explainers', 'title' => 'Explainers', 'group' => 'Content', 'classes' => [ExplainerResource::class], 'access' => 'explainers',
        'what' => 'Short notes about words, people, places and events, shown when a reader taps the word in an article.',
        'steps' => [
            'Write the word, what it is, the note (one or two plain sentences) and a source.',
            'Notes made by the AI import are Draft. Read each one, then publish. The bulk button "Checked: publish" publishes the selected ones with your name as reviewer.',
            'Use a note in an article by writing @[the word].',
        ],
        'tips' => ['The page "Words explained" lists every published note A to Z.'],
    ],
    [
        'key' => 'pages', 'title' => 'Pages', 'group' => 'Content', 'classes' => [PageResource::class], 'access' => 'pages',
        'what' => 'Pages such as About, Privacy and Contact. Pages in the footer show in the footer.',
        'steps' => ['Write the page and choose whether it shows in the footer.', 'Changing its web address makes a redirect by itself.'],
        'tips' => [],
    ],
    [
        'key' => 'sliders', 'title' => 'Sliders', 'group' => 'Content', 'classes' => [SlideViewerSettings::class], 'access' => 'superuser',
        'what' => 'The settings of the picture slider of Visual Stories and of the home page banner.',
        'steps' => ['Change a setting and save. "Reset to the usual" brings back the usual values.', 'In the home page banner, "Add a picture" adds a slide. Choose "A video clip" to use a short video instead: it plays by itself, without sound and without controls, in a loop, and fills the whole banner. The words and the button stay on top.', 'The order of the slides is the order on the page: drag to change it. "Seconds before the next picture" also moves between clips, so set it to about the length of your clips (0 = it never moves by itself).'],
        'tips' => ['Pictures are cut to the shape set here and saved small.', 'A clip is kept as you upload it (not made smaller): MP4 (H.264) or WebM, up to 12 MB, best under 8 MB and 5 to 20 seconds. Smaller clips load faster on a phone.', 'A visitor who asked their device for less motion, or who uses a data saver, sees the still picture instead of the clip. Add a still picture to each clip.', 'The pause button on the banner also stops the clips.'],
    ],
    [
        'key' => 'contributors', 'title' => 'Contributors', 'group' => 'Content', 'classes' => [PersonResource::class], 'access' => 'people',
        'what' => 'The people who are credited on articles. A contributor is not a login account.',
        'steps' => ['Write the name, role, a short bio and a photo.', '"Give them a public page" adds a page that lists their articles.', 'Choose a contributor in the Publishing part of an article to credit them.'],
        'tips' => [],
    ],
    [
        'key' => 'newsletter-issues', 'title' => 'Send newsletter', 'group' => 'Content', 'classes' => [NewsletterIssueResource::class], 'access' => 'newsletter',
        'what' => 'Write a newsletter and send it to the people who subscribed, a batch at a time.',
        'steps' => ['Write it, then use the send action. It sends in small batches so the mail server is not overloaded.', 'Everyone gets an unsubscribe link.'],
        'tips' => ['Sending needs the email settings of the site to be correct.'],
    ],
    [
        'key' => 'newsletter-subscribers', 'title' => 'Newsletter (subscribers)', 'group' => 'Content', 'classes' => [NewsletterSubscriberResource::class], 'access' => 'superuser',
        'what' => 'The list of people who asked for the newsletter.',
        'steps' => ['Look people up, remove one, or export the list.'],
        'tips' => ['Keep this list private.'],
    ],

    // ================= Challenge yourself =================
    [
        'key' => 'challenge', 'title' => 'Challenge yourself (the tests)', 'group' => 'Challenge yourself',
        'classes' => [ChallengeSettings::class, FactStatementResource::class, QuizPassageResource::class, QuotePassageResource::class, QuranChapterResource::class, ScripturePassageResource::class],
        'access' => 'tests',
        'what' => 'The five tests visitors can play: a Quran-style test, Which scripture?, Fact or myth?, Who said it? and an order test with the 114 chapters.',
        'steps' => ['Test settings: switch each test on or off and set how many questions it asks.', 'The other screens hold the passages and facts that the tests use. Add or correct them there.'],
        'tips' => ['A test shows on the site only when it is switched on and has enough material.'],
    ],

    // ================= Settings =================
    [
        'key' => 'site-settings', 'title' => 'Site settings', 'group' => 'Settings', 'classes' => [SiteSettings::class], 'access' => 'superuser',
        'what' => 'The name, taglines, home page parts, Listen, comments and sign-up switches of the site.',
        'steps' => ['Change what you need and save. Most settings take effect at once.', 'Sign-ups open: switch off to stop new members (Google and Facebook sign-up follows this too).'],
        'tips' => [],
    ],
    [
        'key' => 'social', 'title' => 'Social media', 'group' => 'Settings', 'classes' => [SocialLinkResource::class], 'access' => 'superuser',
        'what' => 'The site\'s own accounts, shown as round buttons in the footer.',
        'steps' => ['Add the web address of each account. Drag to change the order. Switch one off to hide it.'],
        'tips' => ['This is not the same as signing in with Google or Facebook. That needs keys in the .env file.'],
    ],
    [
        'key' => 'users', 'title' => 'People', 'group' => 'Settings', 'classes' => [UserResource::class], 'access' => 'superuser',
        'what' => 'The accounts of everyone who can sign in: super users, moderators, authors and members.',
        'steps' => ['Choose a role and a status. Switched off people cannot sign in.', 'For a moderator or author you can tick the admin screens they may use. Leave all unticked for the usual screens of the role.'],
        'tips' => ['An author who needs the AI import needs "AI import" ticked.', 'A person you add here is counted as confirmed. A member who signs up on the site must confirm their own email address.'],
    ],
    [
        'key' => 'backups', 'title' => 'Backups', 'group' => 'Settings', 'classes' => [Backups::class], 'access' => 'superuser',
        'what' => 'Safe copies of the database and of the uploaded pictures.',
        'steps' => ['Press "Back up the database now" or the files button, then Download.', 'Copies older than the keep time (14 days) are removed by themselves.', 'The page shows how to set up the nightly backup on this computer or on the web host.'],
        'tips' => ['Download a copy now and then and keep it somewhere else.'],
    ],
    [
        'key' => 'history', 'title' => 'History', 'group' => 'Settings', 'classes' => [ActivityResource::class], 'access' => 'superuser',
        'what' => 'Who changed what and when. A change to a text can be put back.',
        'steps' => ['Filter by kind, person, date or search a name.', '"Details" shows before and after. "Put the old words back" restores the chosen fields.', '"How long to keep the history" sets how many months are kept.'],
        'tips' => ['Status, category and user role cannot be put back this way.'],
    ],
    [
        'key' => 'redirects', 'title' => 'Redirects', 'group' => 'Settings', 'classes' => [RedirectResource::class], 'access' => 'superuser',
        'what' => 'An old web address that leads to a new one. They are made by themselves when an address changes.',
        'steps' => ['"New redirect" adds one by hand (to a page of the site, or to another website).', 'Switch one off or delete it when it is not needed.'],
        'tips' => ['A redirect is used only when nothing lives at the old address.'],
    ],
    [
        'key' => 'languages', 'title' => 'Languages', 'group' => 'Settings', 'classes' => [\App\Filament\Resources\Languages\LanguageResource::class], 'access' => 'superuser',
        'what' => 'The list in the language button at the top of the site, each language with its flag. English is the main language and is always on.',
        'steps' => ['"Add a language" lets you pick from a list (name, flag and writing direction are filled in for you).', 'Keep it on "Coming soon" while the texts are being translated: visitors see it greyed out with the word "Soon".', 'Switch it to "On" when it is ready. Then visitors can choose it.', 'Once a language is Coming soon or On, a box for it appears beside every title and text in the admin.'],
        'tips' => ['The words of the site (buttons, menus) go in a folder lang/<code>/site.php. Without it English words are shown.', 'The main language cannot be switched off or deleted.'],
    ],
    [
        'key' => 'seo', 'title' => 'SEO and tracking', 'group' => 'Settings', 'classes' => [\App\Filament\Pages\SeoSettings::class], 'access' => 'superuser',
        'what' => 'How the site appears in Google and when a link is shared, plus Google Tag Manager / Analytics and your own code for the head and the body.',
        'steps' => ['While the site is being built, switch "Hide the whole site from search engines" ON. On launch day switch it OFF.', 'Set the home page title, the title pattern, a default description and a share picture.', 'Paste the verification codes from Google Search Console, Bing and Facebook.', 'Paste the Google Tag Manager ID (GTM-...). The head and body parts are added for you. Put Google Analytics inside Tag Manager.', 'Custom code boxes print your code exactly as typed: before </head>, after <body>, and before </body>.'],
        'tips' => ['Tracking never runs on localhost and, by default, not for logged-in staff.', 'robots.txt and sitemap.xml are made by themselves. Open /robots.txt on the live site to see them.', 'Articles and stories already carry structured data (JSON-LD) for Google.'],
    ],
    [
        'key' => 'faq', 'title' => 'FAQ', 'group' => 'Content', 'classes' => [\App\Filament\Resources\Faqs\FaqResource::class], 'access' => 'pages',
        'what' => 'The questions and answers of the FAQ page (/faq). Each question opens like an accordion. Readers can search the questions and open one with a link such as /faq#q-3.',
        'steps' => ['"New question" adds one. Write the question, the answer, and (if you want) a heading to list it under.', 'Drag the rows to change the order. Questions with the same heading are listed together.', 'Switch "Shown" off to hide a question without deleting it.'],
        'tips' => ['Google can show the questions and answers directly in its results, so write each answer in two or three clear sentences.', 'The old FAQ page text was moved here, question by question, and the old page was hidden (not deleted).'],
    ],
    [
        'key' => 'story-maker', 'title' => 'Story maker', 'group' => 'Content', 'classes' => [\App\Filament\Pages\StoryMaker::class], 'access' => 'storymaker',
        'what' => 'Makes stories in pictures with an AI chat: it builds the prompt, checks the answer you paste, and saves it as a draft story. It is separate from the AI import of the articles.',
        'steps' => ['Write the subject, choose the category, the number of slides (as many as the story needs), how long each slide is (Short, Story, or Story + extra) and the look of the pictures. Copy the prompt into an AI chat.', 'Paste the answer of the AI and press Check. It shows the chapters, the slides, the picture line of each slide, and what to look at. Problems must be fixed; notes are only advice. Press "Read the whole story" to read everything in order, like a reader (nothing is saved by reading). Not happy with it? Do not save: change the prompt and ask the AI again.', 'Press "Save as a draft". The story is a draft with no pictures yet. Step 4 (Pictures) opens: copy the picture lines to your image maker, then choose all the pictures at once. They go in order by file name: the first one can be the cover, the others go to the slides that have no picture yet. You see where each one goes, can move or take out a picture, and then press "Put the pictures on the story". Read the story and publish it yourself.', '"Save this run" keeps your choices, the prompt and the pasted answer. Open any run again from "Saved runs": you can check it again and save it again (the same draft is updated while it is still a draft).'],
        'tips' => ['A story is told in extremely simple words, usually in 3 to 5 chapters. The last chapter is always "Closing and final remarks".', 'A story does not need sources. If you want references, they go in the closing chapter.', 'A Quran verse keeps its Arabic and its English exactly. Compare every Arabic text with Quran.com before you publish.', 'Name the pictures 01, 02, 03 … (or let your image maker number them): numbers are read as numbers, so 2 comes before 10. A picture you have already added can be replaced by ticking "Also replace the pictures that are already on the slides". Every picture is made light and cut to the story shape.', 'The "Pictures" button on a saved run opens Step 4 again, for adding or changing pictures later. If the pictures are on the wrong slides, use "Pictures now on the slides": drag a picture by its ⋮⋮ handle to another slide (the pictures in between move one place), or use the arrows to swap it with the slide before or after. No new upload.', 'How long each slide is: Short is about 25 to 45 words (quick captions), Story about 60 to 110 words (a told story), Story + extra about 90 to 150 words with one short "good to know" line when it helps. The prompt and the check both follow your choice, and a saved run remembers it. A longer slide scrolls inside its page in the book.', 'The prophets are called by the Muslim name, with (AS) and the Bible name in brackets at the first mention: Isa (AS) (Jesus). The check warns when a Bible name stands alone or when the Bible name is missing at the first mention. It is still a draft: check every name yourself.', 'Nothing is in Visual Stories until you press "Save as a draft", so you can always cancel and ask the AI again. The super user can make "Save as a draft" wait until the whole story was opened in the reading panel (the switch is in the rules box).', 'When a run updates a draft that already has pictures and the new text has other chapters or slides, a note says so before you save: the pictures stay in the same places (by order), so check them.', 'The usual length is now Story. "Let the AI decide the length and the number of slides" (open flow) is ticked by default, so the story can flow freely: the prompt then leaves both to the AI (usually 6 to 14 slides, 50 to 130 words a slide), and the check only notes a very thin or a very long slide.', 'If you already have a story text (from your own prompt or your own writing), open "I already have a story text" in Step 1 and paste it there. The prompt then becomes a "put my text into shape" prompt: the AI only cuts your text into chapters and slides in the shape of the Story maker. It must keep every fact, verse and reference, add nothing new, and move source lists and notes into PLEASE CHECK. Copy that prompt into the AI, paste its answer in Step 2, and press Check. Clear the text to go back to the normal prompt.', 'A story that is published is not changed here: open it in Visual Stories and edit its content there.', 'The story must flow like a told story, not a list: the prompt asks for a narrator voice and one flowing paragraph for each slide, and the check warns about a slide that reads like a list, begins many sentences the same way, or has very short sentences. For a draft that already reads like a list, press "Rewrite in story voice" in Saved runs: the prompt carries the current text and asks the AI to tell it again with the same facts, verses, chapters and slides, so the pictures stay in place. Paste the answer and save it as an update of the same draft.', 'Only the super user can change the rules of the Story maker and the words of each length.'],
    ],
    [
        'key' => 'story-translations', 'title' => 'Story translations', 'group' => 'Content', 'classes' => [\App\Filament\Pages\StoryTranslations::class, \App\Filament\Pages\StoryTranslationEdit::class], 'access' => 'storymaker',
        'what' => 'Step 2 of the Story maker: it makes the prompt that asks an AI to translate a saved English story into Urdu, Arabic, French, Spanish and Hindi, checks the answer language by language, and saves each language into the story.',
        'steps' => ['Choose a story that is already saved in English (with its slides). Tick the languages to ask for and copy the prompt into an AI chat.', 'Paste the answer and press Check. Each language is checked on its own: the same chapters and slides as the English, the Arabic quotations unchanged letter for letter, the verse numbers, the salutation symbol, and that the words are really in that language.', 'Press "Save the languages". A language with a problem is left out; the others are saved. A saved language stays hidden until you press "Show on the site".', '"Save this run" keeps the choices, the prompt and the pasted answer. Open any run again from "Saved translation runs" and save it again if you need to.'],
        'tips' => ['English is never changed by a translation.', 'When the English text of a story changes (in the Story maker or in Visual Stories), every saved language is hidden and marked "Out of date", because it no longer matches. Make it again with the prompt, or read it with "Edit the text" and save it: then it is up to date and can be shown again.', 'A Quran verse keeps its Arabic exactly. The line under it is the translation named for that language (check the name on Quran.com), or the English line when the AI cannot copy it exactly.', 'The super user can change the translation rules, the Quran translation of each language and its word list (for ﷺ, (AS), (RA), (SWT) and the main words).', 'To fix a word by hand, press "Edit the text" on a language. English is shown beside each box and is never changed. A changed Arabic quotation is not saved. Saving does not change whether the language is shown or hidden.', 'When a language is switched on, the story gets its own address (for example /ur/stories/name) and a "Read in" row appears on the story page. A language that is hidden is shown only to staff, marked "(hidden)". Listen and PDF stay English only.'],
    ],
    [
        'key' => 'site-controls', 'title' => 'Site controls', 'group' => 'Settings', 'classes' => [\App\Filament\Pages\SiteControlsPage::class], 'access' => 'superuser',
        'what' => 'One page of On/Off switches for the parts of the public site that make it look busy: likes, counts, share buttons, social buttons, related articles, and the useful extras like Listen, Save for later, the Recent tab and the language button.',
        'steps' => ['Turn a switch on to show a part, off to hide it, then press Save.', '"Quiet look" switches off all the noisy parts at once. "Normal look" brings them back. The useful extras are never touched by either.', '"All settings (advanced)" opens Site settings.'],
        'tips' => ['Hiding a part never deletes anything: likes, saved items and comments are kept and come back when you switch it on.', 'Some switches are the same ones as in other screens (Listen, newsletter, comments, PDF book), so they always agree.'],
    ],
    [
        'key' => 'analytics', 'title' => 'Analytics', 'group' => 'Reports', 'classes' => [\App\Filament\Pages\AnalyticsDashboard::class], 'access' => 'superuser',
        'what' => 'What is going on at the site, read from your Google Analytics: visitors, pages read, where people come from, countries, devices, and who is on the site right now.',
        'steps' => ['First time: follow the five steps on the page (switch on the Data API, make a service account and a JSON key, add its email as Viewer in Google Analytics, copy the Property ID, upload the key here).', 'Choose Last 7, 28 or 90 days. Every number is compared with the same number of days before (green arrow up, red arrow down).', 'Press Refresh to ask Google again. Numbers are kept for 10 minutes.'],
        'tips' => ['This page only reads. It can never change anything in Google Analytics.', 'The key file is stored on the server, never shown and never in the public folder. You can delete it here at any time.', 'Visitors who choose "Only what is needed" in the cookie notice are not counted.'],
    ],
    [
        'key' => 'move-old-site', 'title' => 'Move from the old site', 'group' => 'Settings', 'classes' => [MoveFromOldSite::class], 'access' => 'superuser',
        'what' => 'Brings content from the old InfoFluxes into this one. It is only there on the computer that still has the old database.',
        'steps' => ['Always press Check first. It shows what would be moved and saves nothing.', 'Then press Move. Take a backup before.'],
        'tips' => [],
    ],
];
