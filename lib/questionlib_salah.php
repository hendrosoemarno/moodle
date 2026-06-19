<?php
// This is a partial questionlib.php file with modified question_pluginfile()

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Handles serving of files related to questions.
 */
function question_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    // Allow only logged-in users
    if (!isloggedin()) {
        return false;
    }

    // Only handle files from the 'questiontext' file area
    if ($filearea !== 'questiontext') {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/question/$filearea/$relativepath";

    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    // Serve the file
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

// Add other necessary Moodle questionlib functions below as needed.
