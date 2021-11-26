<?php
    while (true) {
        $start = time();
        passthru('php artisan schedule:run');
        $end = time();
        sleep($start - $end);
    }
