<?php

return [

    /*
    | Questions and the suspect cutoff wait on the TB programme.
    | Keep questions empty and cutoff null until that sheet arrives.
    |
    | A supplied question is key, label, and optional points (default 1).
    | Score is the sum of points for answers marked yes.
    | is_suspect is true when cutoff is an integer and the score is at least that cutoff.
    */

    'questions' => [],

    'cutoff' => null,

];
