<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSV exports that are safe to open in a spreadsheet. A cell starting with = + - @, a tab or a line
 * break is a formula to Excel, LibreOffice and Google Sheets; data typed by people (client names,
 * expense descriptions, entries) could run one when an administrator opens the file. Such cells get a
 * leading apostrophe so they show as text. Real numbers ("-5.00") are left alone.
 */
class TimeGrowCsv {

    /** One cell, neutralized when it would be read as a formula. Pure. */
    public static function cell($value) {
        if (is_string($value) && $value !== '' && !is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    /** fputcsv() with every cell neutralized (same arguments; the escape character is given explicitly, as PHP 8.4 asks). */
    public static function put($handle, array $fields, $separator = ',', $enclosure = '"', $escape = '\\', $eol = "\n") {
        return fputcsv($handle, array_map([__CLASS__, 'cell'], $fields), $separator, $enclosure, $escape, $eol);
    }
}
