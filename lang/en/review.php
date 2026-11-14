<?php

/** The review checklist of an article written with AI help. A reviewer ticks every line before the article can be published. */
return [
    'section' => 'Review before publishing',
    'section_help' => 'This article was written with AI help. Read it and tick each line only when you really did it. The article cannot be published until every line is ticked and your name is in "Reviewer name".',
    'standard_label' => 'What every reviewer must do',
    'points_label' => 'Points the AI and the system asked you to check',
    'items' => [
        'verses' => 'I checked every Quran verse (surah and verse number, and the words) in a full text.',
        'hadith' => 'I checked every hadith: the collection, the number, the words and the grade.',
        'links' => 'I opened every source link myself, and the page says what the article says.',
        'quotes' => 'Every quotation is word for word, with its place named.',
        'facts' => 'Dates, names and places match the sources.',
        'fair' => 'Each belief is described the way its own followers describe it, and disputed points are marked.',
    ],
    'blocked_title' => 'Not published',
    'blocked_body' => 'This article was written with AI help and the review is not finished: :left line(s) are not ticked. Finish the checklist in "Review before publishing", then publish.',
    'nothing_to_check' => 'There are no extra points for this article.',
];
