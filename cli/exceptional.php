<?php

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!error_reporting()) {
        // Allow silencing
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
