<?php

/**
 * Returns:
 * 0: shop is available
 * 1: shop is not available
 */

$searchResult = @file_get_contents("http://game.raceroom.com/search?query=&json");

if ($searchResult !== false) {
    exit('0');
} else {
    exit('1');
}
