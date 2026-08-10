<?php

//============================================================+
// File name   : LatexRender.php
// Begin       : 2007-05-18
// Last Update : 2023-11-30
// Author      : Nicola Asuni
//
// Description :
// ------------------------------------------------------------
// This is a PHP5 class for generating images from LaTeX Formulas.
// This class is based on the following:
// LaTeX Rendering Class v0.8 (Licensed under GPL 2)
// Copyright (C) 2003 Benjamin Zeiss <zeiss@math.uni-goettingen.de>
// Currently the project is maintained by Steve Mayer.
// Please check the following Website to obtain the original
// source code: http://www.mayer.dial.pipex.com/tex.htm
// ------------------------------------------------------------
//============================================================+

/**
 * @file
 * LaTeX Rendering Class.
 * @package com.tecnick.tcexam.shared
 */

// Includes configuration file.
require_once '../../shared/config/tce_latex.php';

/**
 * @class LatexRender
 * This is a PHP5 class for generating images from LaTeX Formulas.
 * This class is based on the following:
 * LaTeX Rendering Class v0.8 (Licensed under GPL 2)
 * Copyright (C) 2003 Benjamin Zeiss <zeiss@math.uni-goettingen.de>
 * Currently the project is maintained by Steve Mayer.
 * Please check the following Website to obtain the original
 * source code: http://www.mayer.dial.pipex.com/tex.htm
 * @package com.tecnick.tcexam.shared
 * @authors Benjamin Zeiss,2026 Nicola Asuni
 */
class LatexRender
{
    //  ---------- Variable Definitions ---------- * ---------- * ----------

    /**
     * Absolute path to images directory.
     * @protected
     */
    protected string $picture_path;

    /**
     * Relative path to images directory.
     * @protected
     */
    protected string $picture_path_httpd;

    /**
     * Path to temporary directory.
     * @protected
     */
    protected string $tmp_dir;

    /**
     * Path to LATEX.
     * @protected
     */
    protected string $latex_path;

    /**
     * Path to DVIPS.
     * @protected
     */
    protected string $dvips_path;

    /**
     * Path to ImageMagick convert.
     * @protected
     */
    protected string $convert_path;

    /**
     * Path to ImageMagick identify.
     * @protected
     */
    protected string $identify_path;

    /**
     * Formula density (used by ImageMagick)
     * @protected
     */
    protected int $formula_density;

    /**
     * Image width limit in pixels.
     * @protected
     */
    protected int $width_limit;

    /**
     * Image height limit in pixels.
     * @protected
     */
    protected int $height_limit;

    /**
     * Size limit for input string.
     * @protected
     */
    protected int $string_length_limit;

    /**
     * Font size.
     * @protected
     */
    protected int $font_size;

    /**
     * LaTeX class.
     * @protected
     */
    protected string $latexclass;

    /**
     * Filename prefix for chached images.
     * @protected
     */
    protected string $img_prefix;

    /**
     * Image format (default = PNG).
     * @protected
     */
    protected string $image_format;

    /**
     * List of unauthorized LaTeX commands.
     * @protected
     */
    /** @var list<string> */
    protected array $latex_tags_blacklist = [
        'include',
        'def',
        'command',
        'loop',
        'repeat',
        'open',
        'toks',
        'output',
        'input',
        'catcode',
        'name',
        '^^',
        '\every',
        '\errhelp',
        '\errorstopmode',
        '\scrollmode',
        '\nonstopmode',
        '\batchmode',
        '\read',
        '\write',
        'csname',
        '\newhelp',
        '\uppercase',
        '\lowercase',
        '\relax',
        '\aftergroup',
        '\afterassignment',
        '\expandafter',
        '\noexpand',
        '\special',
    ];

    // ------ private ------

    /**
     * Error code.
     * @private
     */
    private int $errorcode = 0;

    /**
     * Temporary filename.
     * @private
     */
    private string $tmp_filename = '';

    /**
     * Image width.
     * @private
     */
    private int|string $img_width = 0;

    /**
     * Image height.
     * @private
     */
    private int|string $img_height = 0;

    //  ---------- constructor / destructor functions ---------- * ---------- * ----------

    /** @throws \Random\RandomException */
    public function __construct()
    {
        $this->picture_path = (string) K_LATEX_PATH_PICTURE;
        $this->picture_path_httpd = (string) K_LATEX_PATH_PICTURE_HTTPD;
        $this->tmp_dir = K_PATH_CACHE;
        $this->latex_path = '/usr/bin/latex';
        $this->dvips_path = '/usr/bin/dvips';
        $this->convert_path = K_LATEX_PATH_CONVERT;
        $this->identify_path = '/usr/bin/identify';
        $this->formula_density = (int) K_LATEX_FORMULA_DENSITY;
        $this->width_limit = (int) K_LATEX_MAX_WIDTH;
        $this->height_limit = (int) K_LATEX_MAX_HEIGHT;
        $this->string_length_limit = (int) K_LATEX_MAX_LENGHT;
        $this->font_size = (int) K_LATEX_FONT_SIZE;
        $this->latexclass = K_LATEX_CLASS;
        $this->img_prefix = K_LATEX_IMG_PREFIX;
        $this->image_format = K_LATEX_IMG_FORMAT;
        $this->tmp_filename = md5((string) random_int(0, mt_getrandmax()));
    }

    /**
     * Default destructor.
     */
    public function __destruct() {}

    // ---------- public functions ---------- * ---------- * ---------- * ----------

    // ---------- set functions ----------

    /**
     * Set the absolute path to images directory.
     * @param $picture_path (string) absolute path to images directory.
     */
    public function setPathToPicturesDir(string $picture_path): void
    {
        $this->picture_path = $picture_path;
    }

    /**
     * Set relative path to images directory.
     * @param $picture_path_httpd (string) relative path to images directory.
     */
    public function setPathToPicturesDirHttpd(string $picture_path_httpd): void
    {
        $this->picture_path_httpd = $picture_path_httpd;
    }

    /**
     * Set path to temporary directory.
     * @param $tmp_dir (string) path to temporary directory.
     */
    public function setPathToTempDir(string $tmp_dir): void
    {
        $this->tmp_dir = $tmp_dir;
    }

    /**
     * Set path to LATEX.
     * @param $latex_path (string) path to LATEX.
     */
    public function setPathToLatex(string $latex_path): void
    {
        $this->latex_path = $latex_path;
    }

    /**
     * Set path to DVIPS.
     * @param $dvips_path (string) path to DVIPS.
     */
    public function setPathToDvips(string $dvips_path): void
    {
        $this->dvips_path = $dvips_path;
    }

    /**
     * Set path to ImageMagick convert.
     * @param $convert_path (string) path to ImageMagick convert.
     */
    public function setPathToImageMagicConvert(string $convert_path): void
    {
        $this->convert_path = $convert_path;
    }

    /**
     * Set path to ImageMagick identify.
     * @param $identify_path (string) path to ImageMagick identify.
     */
    public function setPathToImageMagicIdentify(string $identify_path): void
    {
        $this->identify_path = $identify_path;
    }

    /**
     * Set formula density (used by ImageMagick)
     * @param $formula_density (int) formula density.
     */
    public function setFormulaDensity(int $formula_density): void
    {
        $this->formula_density = $formula_density;
    }

    /**
     * Set image width limit in pixels.
     * @param $width_limit (string) Max image width in pixels.
     */
    public function setMaxWidth(int $width_limit): void
    {
        $this->width_limit = $width_limit;
    }

    /**
     * Set image height limit in pixels.
     * @param $height_limit (string) Max image height in pixels.
     */
    public function setMaxHeight(int $height_limit): void
    {
        $this->height_limit = $height_limit;
    }

    /**
     * Set size limit for input string.
     * @param $string_length_limit (string) max length for LaTeX string.
     */
    public function setMaxLength(int $string_length_limit): void
    {
        $this->string_length_limit = $string_length_limit;
    }

    /**
     * Set font size.
     * @param $font_size (int) font size in points.
     */
    public function setFontSize(int $font_size): void
    {
        $this->font_size = $font_size;
    }

    /**
     * Set LaTeX class.
     * Install extarticle class if you wish to have smaller font sizes.
     * @param $latexclass (string) LaTeX class.
     */
    public function setLatexClass(string $latexclass): void
    {
        $this->latexclass = $latexclass;
    }

    /**
     * Set filename prefix for chached images.
     * @param $img_prefix (string) filename prefix.
     */
    public function setFilenamePrefix(string $img_prefix): void
    {
        $this->img_prefix = $img_prefix;
    }

    /**
     * Set the image format (default = PNG).
     * @param $image_format (string) image format(e.g.: png).
     */
    public function setImageFormat(string $image_format): void
    {
        $this->image_format = $image_format;
    }

    /**
     * Set the list of unauthorized LaTeX commands.
     * @param $latex_tags_blacklist (array) array of blacklisted commands.
     */
    /** @param list<string> $latex_tags_blacklist */
    public function setLatexBlackList(array $latex_tags_blacklist): void
    {
        $this->latex_tags_blacklist = $latex_tags_blacklist;
    }

    // ---------- get functions ----------

    /**
     * Tries to match the LaTeX Formula given as argument against the
     * formula cache. If the picture has not been rendered before, it'll
     * try to render the formula and drop it in the picture cache directory.
     *
     * @param $latex_formula (string) formula in LaTeX format
     * @returns the webserver based URL to a picture which contains the
     * requested LaTeX formula. If anything fails, the result value is false.
     */
    public function getFormulaURL(string $latex_formula): string|false
    {
        // circumvent certain security functions of web-software which
        // is pretty pointless right here
        $latex_formula = str_ireplace(['&gt;', '&lt;'], ['>', '<'], $latex_formula);

        $filename = $this->getFilename($latex_formula);
        $full_path_filename = $this->picture_path . '' . $filename;

        if (is_file($full_path_filename)) {
            return $this->picture_path_httpd . '' . $filename;
        }

        // security filter: reject too long formulas
        if (strlen($latex_formula) > $this->string_length_limit) {
            $this->errorcode = 1;
            return false;
        }

        // security filter: try to match against LaTeX-Tags Blacklist
        // security filter: try to match against LaTeX-Tags Blacklist
        foreach ($this->latex_tags_blacklist as $latex_tag) {
            if (stristr($latex_formula, $latex_tag)) {
                $this->errorcode = 2;
                return false;
            }
        }

        // security checks assume correct formula, let's render it
        if ($this->renderLatex($latex_formula)) {
            return $this->picture_path_httpd . '' . $filename;
        }

        return false;
    }

    /**
     * Returns Image width
     * @returns image width in pixels.
     */
    public function getImageWidth(): int|string
    {
        return $this->img_width;
    }

    /**
     * Returns Image height
     * @returns image height in pixels.
     */
    public function getImageHeight(): int|string
    {
        return $this->img_height;
    }

    /**
     * Returns the error code
     * @returns int error code.
     */
    public function getErrorCode(): int
    {
        return $this->errorcode;
    }

    //  --- private functions --------------------------------------------------

    /**
     * Wraps a minimalistic LaTeX document around the formula and returns a string
     * containing the whole document as string.
     * Customize if you want other fonts for example.
     *
     * @param $latex_formula (string) formula in LaTeX format
     * @returns minimalistic LaTeX document containing the given formula
     */
    private function getFilename(string $latex_formula): string
    {
        return $this->img_prefix . md5($latex_formula) . '.' . $this->image_format;
    }

    /**
     * Wraps a minimalistic LaTeX document around the formula and returns a string
     * containing the whole document as string.
     * Customize if you want other fonts for example.
     *
     * @param $latex_formula (string) formula in LaTeX format
     * @returns minimalistic LaTeX document containing the given formula
     */
    private function wrapFormula(string $latex_formula): string
    {
        $string = '\documentclass[' . $this->font_size . 'pt]{' . $this->latexclass . '}' . "\n";
        $string .= '\usepackage[latin1]{inputenc}' . "\n";
        $string .= '\usepackage{amsmath}' . "\n";
        $string .= '\usepackage{amsfonts}' . "\n";
        $string .= '\usepackage{amssymb}' . "\n";
        $string .= '\pagestyle{empty}' . "\n";
        $string .= '\begin{document}' . "\n";
        $string .= '$' . $latex_formula . '$' . "\n";
        return $string . ('\end{document}' . "\n");
    }

    /**
     * Removes temporary files.
     * @param $current_dir (string) current directory.
     * @param $error_code (int) error code.
     */
    private function cleanTemporaryDirectory(string $current_dir, int $error_code = 0): void
    {
        chdir($this->tmp_dir);
        unlink($this->tmp_dir . '' . $this->tmp_filename . '.tex');
        unlink($this->tmp_dir . '' . $this->tmp_filename . '.aux');
        unlink($this->tmp_dir . '' . $this->tmp_filename . '.log');
        unlink($this->tmp_dir . '' . $this->tmp_filename . '.dvi');
        unlink($this->tmp_dir . '' . $this->tmp_filename . '.ps');
        unlink($this->tmp_dir . '' . $this->tmp_filename . '.' . $this->image_format);
        chdir($current_dir);
        $this->errorcode = $error_code;
    }

    /**
     * Check the dimensions of a picture file using 'identify' of the
     * ImageMagick tools.
     *
     * @param $filename (string) path to a picture
     * @returns array containing the picture dimensions
     */
    private function checkImageDimensions(string $filename): bool
    {
        $output = exec($this->identify_path . ' ' . $filename);
        if ($output === '' || $output === false) {
            return false;
        }

        $result = explode(' ', $output);
        if (!isset($result[2])) {
            return false;
        }

        $dim = explode('x', $result[2]);
        if (!isset($dim[1])) {
            return false;
        }

        $this->img_width = $dim[0];
        $this->img_height = $dim[1];
        return $this->img_width <= $this->width_limit && $this->img_height <= $this->height_limit;
    }

    /**
     * Renders a LaTeX formula by the using the following method:
     *  - write the formula into a wrapped tex-file in a temporary directory
     *    and change to it
     *  - Create a DVI file using latex (tetex)
     *  - Convert DVI file to Postscript (PS) using dvips (tetex)
     *  - convert, trim and add transparancy by using 'convert' from the
     *    ImageMagick package.
     *  - Save the resulting image to the picture cache directory using an
     *    md5 hash as filename. Already rendered formulas can be found directly
     *    this way.
     *
     * @param $latex_formula (string) LaTeX formula
     * @returns true if the picture has been successfully saved to the picture
     *          cache directory
     */
    private function renderLatex(string $latex_formula): bool
    {
        $latex_document = $this->wrapFormula($latex_formula);

        $current_dir = getcwd();
        if (!is_string($current_dir)) {
            return false;
        }

        chdir($this->tmp_dir);

        // create temporary latex file
        $fp = fopen($this->tmp_dir . '' . $this->tmp_filename . '.tex', 'a+');
        fwrite($fp, $latex_document);
        fclose($fp);

        // create temporary DVI file
        $command = $this->latex_path . ' --interaction=nonstopmode ' . $this->tmp_filename . '.tex';
        $status_code = exec($command);
        if (!$status_code) {
            $this->cleanTemporaryDirectory($current_dir, 4);
            return false;
        }

        // convert DVI file to postscript using DVIPS
        $command = $this->dvips_path . ' -E ' . $this->tmp_filename . '.dvi -o ' . $this->tmp_filename . '.ps';
        $status_code = exec($command);

        // ImageMagick convert PS to image and trim picture
        $command =
            $this->convert_path
            . ' -density '
            . $this->formula_density
            . ' -background "#FFFFFF" -depth 8 '
            . $this->tmp_filename
            . '.ps '
            . $this->tmp_filename
            . '.'
            . $this->image_format;
        $status_code = exec($command);

        // check picture dimensions
        if (!$this->checkImageDimensions($this->tmp_filename . '.' . $this->image_format)) {
            $this->cleanTemporaryDirectory($current_dir, 7);
            return false;
        }

        // copy temporary formula file to cached formula directory
        $filename = $this->getFilename($latex_formula);
        $status_code = copy($this->tmp_filename . '.' . $this->image_format, $filename);

        if (!$status_code) {
            $this->cleanTemporaryDirectory($current_dir, 8);
            return false;
        }

        $this->cleanTemporaryDirectory($current_dir, 0);

        return true;
    }
} // end of class
