<?php

return [

    'code_length' => (int) env('VERIFICATION_CODE_LENGTH', 5),

    'expires_in_minutes' => (int) env('VERIFICATION_EXPIRES_IN_MINUTES', 10),

    'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),

];
