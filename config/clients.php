<?php

return [
    /*
    | البحث في ملاحظات العملاء:
    | على MariaDB/MySQL يُستخدم فهرس FULLTEXT (مطابقة بداية الكلمة)،
    | وعلى Oracle/SQLite يُستخدم LIKE. اضبط notes_fulltext=false للرجوع إلى LIKE دائماً.
    */
    'notes_fulltext' => (bool) env('CLIENTS_NOTES_FULLTEXT', true),
    'notes_min_length' => (int) env('CLIENTS_NOTES_MIN_LENGTH', 3),
];
