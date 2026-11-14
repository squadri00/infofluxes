<?php

/** The prompt library: slug => [kind, title, when to use it, order]. The words of each prompt are in the .txt file with the same name. */
return [
    'master' => ['master', 'Master rules (used for every article)', 'Always added in front of the specialist prompt.', 1],
    'islam-beliefs' => ['specialist', 'Islamic beliefs and the Quran', 'Allah (SWT), prophets, the Quran, hadith, angels, the afterlife, articles of faith.', 2],
    'islam-history' => ['specialist', 'Islamic history and civilisation', 'Events, people, places, travel, learning and science in Islamic history.', 3],
    'bible-comparative' => ['specialist', 'The Bible, Judaism and Christianity', 'Biblical subjects, Jewish and Christian beliefs, and comparison with the Quran.', 4],
    'other-religions' => ['specialist', 'Other religions and world views', 'Hinduism, Buddhism, Sikhism and other traditions.', 5],
    'science-and-faith' => ['specialist', 'Science, nature and faith', 'Where a religious text and science meet; the history of science.', 6],
    'history-general' => ['specialist', 'General history, people and places', 'History and geography that is not mainly one religion\'s teaching.', 7],
];
