<?php

//============================================================+
// File name   : tce_page_footer.php
// Begin       : 2001-09-02
// Last Update : 2023-11-30
//
// Description : Outputs default XHTML page footer.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Outputs default XHTML page footer.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2001-09-02
 */

echo K_NEWLINE;
echo '</main>' . K_NEWLINE; //close main.body

echo '<footer class="footer" role="contentinfo">' . K_NEWLINE;
include '../../shared/code/tce_page_userbar.php'; // display user bar
echo '</footer>' . K_NEWLINE;
include '../config/theme/picoman.php'; // public theme by Maman Sulaeman
echo '<script src="' . K_PATH_SHARED_JSCRIPTS . 'mobile-exam.js" defer="defer"></script>' . K_NEWLINE;
echo '<script src="' . K_PATH_SHARED_JSCRIPTS . 'login-page.js?v=20260718-1" defer="defer"></script>' . K_NEWLINE;
echo '<script src="' . K_PATH_SHARED_JSCRIPTS . 'public-app-shell.js?v=20260718-5" defer="defer"></script>' . K_NEWLINE;

echo '<!-- ' . base64_decode(K_KEY_SECURITY) . ' -->' . K_NEWLINE;
echo '</body>' . K_NEWLINE;
echo '</html>';
