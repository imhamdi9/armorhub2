<?php

return [
    // After this many failed attempts a delivery is dead-lettered (it stays visible and can be replayed).
    'max_attempts' => (int) env('EVENTS_MAX_ATTEMPTS', 6),
];
