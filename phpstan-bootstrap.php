<?php
/**
 * Analysis-only constant stub for PHPStan.
 *
 * ACCESSIBILITY_LAB_URL is defined in accessibility-lab.php via
 * plugin_dir_url(), a runtime call PHPStan cannot resolve statically, so the
 * constant never enters its symbol table. Paired with `dynamicConstantNames`
 * in phpstan.neon.dist so PHPStan treats the value as an opaque string rather
 * than this placeholder literal.
 *
 * The other plugin constants resolve fine from source and are deliberately
 * not stubbed here — a stub value could drift from the real one.
 *
 * @package AccessibilityLab
 */

define( 'ACCESSIBILITY_LAB_URL', '' );
