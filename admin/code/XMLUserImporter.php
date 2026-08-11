<?php

//============================================================+
// File name   : XMLUserImporter.php
// Begin       : 2006-03-17
// Last Update : 2023-11-30
//
// Description : Import users from an XML file or tab-delimited
//               TSV file.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Import users from an XML file or TSV (Tab delimited text file).
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-17
 */

require_once '../config/tce_config.php';

$submitted_file_type = $_POST['file_type'] ?? '';
$file_type = is_string($submitted_file_type) ? $submitted_file_type : '';

$pagelevel = (int) K_AUTH_IMPORT_USERS;
require_once '../../shared/code/tce_authorization.php';

/** @var array{
 *   t_user_importer: string, m_importing_complete: string, w_upload_file: string,
 *   h_upload_file: string, h_file_type: string, w_type: string, h_file_type_xml: string,
 *   h_file_type_tsv: string, w_upload: string, h_submit_file: string, hp_import_xml_users: string
 * } $l
 */
/** @var string $menu_mode */
$thispage_title = $l['t_user_importer'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';

switch ($menu_mode) {
    case 'upload':
            $userfile = $_FILES['userfile'] ?? null;
            if (is_array($userfile) && !empty($userfile['name'])) {
                require_once '../code/tce_functions_upload.php';
                // upload file
                /** @var false|string $uploadedfile */
                $uploadedfile = f_upload_file('userfile', K_PATH_CACHE);
                if ($uploadedfile !== false) {
                    switch ($file_type) {
                        case 1:
                                $xmlimporter = new XMLUserImporter(K_PATH_CACHE . $uploadedfile);
                                F_print_error('MESSAGE', $l['m_importing_complete']);
                                break;
                        case 2:
                                if (F_import_tsv_users(K_PATH_CACHE . $uploadedfile)) {
                                    F_print_error('MESSAGE', $l['m_importing_complete']);
                                }

                                break;
                    }
                }
            }

            break;

    default:
            break;
}

//end of switch
?>

<div class="container">

<div class="tceformbox">
<form action="<?php echo
    htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
; ?>" method="post" enctype="multipart/form-data" id="form_importusers">

<div class="row">
<span class="label">
<label for="userfile"><?php echo $l['w_upload_file']; ?></label>
</span>
<span class="formw">
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo K_MAX_UPLOAD_SIZE ?>" />
<input type="file" name="userfile" id="userfile" size="20" title="<?php echo $l['h_upload_file']; ?>" />
</span>
&nbsp;
</div>

<div class="row">
<div class="formw">
<fieldset class="noborder">
<legend title="<?php echo $l['h_file_type']; ?>"><?php echo $l['w_type']; ?></legend>

<input type="radio" name="file_type" id="file_type_xml" value="1" checked="checked" title="<?php echo
    $l['h_file_type_xml']
; ?>" />
<label for="file_type_xml">XML</label>
<br />
<input type="radio" name="file_type" id="file_type_tsv" value="2" title="<?php echo $l['h_file_type_tsv']; ?>" />
<label for="file_type_tsv">TSV</label>
</fieldset>
</div>
</div>

<div class="row">
<?php

// show buttons by case
F_submit_button('upload', $l['w_upload'], $l['h_submit_file']);
echo f_get_csrf_token_field() . K_NEWLINE;
?>
</div>

</form>

</div>
<?php

echo '<div class="pagehelp">' . $l['hp_import_xml_users'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

// ------------------------------------------------------------

/**
 * @class XMLUserImporter
 * This PHP Class imports users and groups data directly from a XML file.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni [tecnick.com]
 * @version 1.0.000
 */
class XMLUserImporter
{
    public \XMLParser $parser;

    /**
     * String Current data element.
     * @private
     */
    private string $current_element = '';

    /**
     * String Current data value.
     * @private
     */
    private string $current_data = '';

    /**
     * Array Array for storing user data.
     * @private
     */
    /** @var array<string, int|string> */
    private array $user_data = [];

    /**
     * Array for storing user's group data.
     * @private
     */
    /** @var list<int> */
    private array $group_data = [];

    /**
     * Class constructor.
     * @param $xmlfile (string) XML file name
     * @throws RuntimeException when the XML file cannot be read
     */
    public function __construct(
        /**
         * String XML file
         * @private
         */
        private string $xmlfile,
    ) {
        // creates a new XML parser to be used by the other XML functions
        $this->parser = xml_parser_create();
        // disable case-folding for this XML parser
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        // sets the element handler functions for the XML parser
        xml_set_element_handler($this->parser, $this->startElementHandler(...), $this->endElementHandler(...));
        // sets the character data handler function for the XML parser
        xml_set_character_data_handler($this->parser, $this->segContentHandler(...));
        // start parsing an XML document
        if (!is_file($xmlfile) || !is_readable($xmlfile)) {
            throw new RuntimeException('Unable to read XML user import file.');
        }

        $xml = file_get_contents($xmlfile);
        if ($xml === false) {
            throw new RuntimeException('Unable to read XML user import file.');
        }

        if (xml_parse($this->parser, $xml) === 0) {
            die(sprintf(
                'ERROR xmlResourceBundle :: XML error: %s at line %d',
                xml_error_string(xml_get_error_code($this->parser)),
                xml_get_current_line_number($this->parser),
            ));
        }
    }

    public function __destruct()
    {
        // delete uploaded file
        $xmlfile = $this->xmlfile;
        if (is_file($xmlfile)) {
            unlink($xmlfile);
        }
    }

    /**
     * Sets the start element handler function for the XML parser parser.start_element_handler.
     * @param mixed $_parser The XML parser calling the handler.
     * @param $name (string) The second parameter, name, contains the name of the element for which this handler is called. If case-folding is in effect for this parser, the element name will be in uppercase letters.
     * @param array<string, string> $_attribs The element attributes supplied by ext-xml.
     * @private
     */
    private function startElementHandler(mixed $_parser, string $name, array $_attribs): void
    {
        $name = strtolower($name);
        switch ($name) {
            case 'user':
                    $this->user_data = [
                        'user_name' => '',
                        'user_password' => '',
                        'user_email' => '',
                        'user_regdate' => '',
                        'user_ip' => '',
                        'user_firstname' => '',
                        'user_lastname' => '',
                        'user_birthdate' => '',
                        'user_birthplace' => '',
                        'user_regnumber' => '',
                        'user_ssn' => '',
                        'user_level' => '',
                        'user_verifycode' => '',
                        'user_otpkey' => '',
                    ];
                    $this->group_data = [];
                    $this->current_data = '';
                    break;
            case 'name':
            case 'password':
            case 'email':
            case 'regdate':
            case 'ip':
            case 'firstname':
            case 'lastname':
            case 'birthdate':
            case 'birthplace':
            case 'regnumber':
            case 'ssn':
            case 'level':
            case 'verifycode':
            case 'otpkey':
                    $this->current_element = 'user_' . $name;
                    $this->current_data = '';
                    break;
            case 'group':
                    $this->current_element = 'group_name';
                    $this->current_data = '';
                    break;
            default:
                    break;
        }
    }

    /**
     * Sets the end element handler function for the XML parser parser.end_element_handler.
     * @param mixed $_parser The XML parser calling the handler.
     * @param $name (string) The second parameter, name, contains the name of the element for which this handler is called. If case-folding is in effect for this parser, the element name will be in uppercase letters.
     * @private
     */
    private function endElementHandler(mixed $_parser, string $name): mixed
    {
        global $l, $db;
        require_once '../config/tce_config.php';
        require_once 'tce_functions_user_select.php';

        switch (strtolower($name)) {
            case 'name':
            case 'password':
            case 'email':
            case 'regdate':
            case 'ip':
            case 'firstname':
            case 'lastname':
            case 'birthdate':
            case 'birthplace':
            case 'regnumber':
            case 'ssn':
            case 'level':
            case 'verifycode':
            case 'otpkey':
                    $this->current_data = F_escape_sql($db, f_xml_to_text($this->current_data));
                    $this->user_data[$this->current_element] = $this->current_data;
                    $this->current_element = '';
                    $this->current_data = '';
                    break;
            case 'group':
                    $group_name = F_escape_sql($db, f_xml_to_text($this->current_data));
                    // check if group already exist
                    $sql = 'SELECT group_id
					FROM ' . K_TABLE_GROUPS . '
					WHERE group_name=\'' . $group_name . '\'
					LIMIT 1';
                    /** @var mixed $r */
                    $r = F_db_query($sql, $db);
                    if ($r) {
                        /** @var mixed $m */
                        $m = F_db_fetch_array($r);
                        if (is_array($m)) {
                            // the group has been already added
                            $this->group_data[] = (int) ($m['group_id'] ?? 0);
                        } else {
                            // add new group
                            $sqli = 'INSERT INTO ' . K_TABLE_GROUPS . ' (
							group_name
							) VALUES (
							\'' . $group_name . '\'
							)';
                            /** @var mixed $ri */
                            $ri = F_db_query($sqli, $db);
                            if (!$ri) {
                                F_display_db_error(false);
                            } else {
                                $this->group_data[] = (int) F_db_insert_id($db, K_TABLE_GROUPS, 'group_id');
                            }
                        }
                    } else {
                        F_display_db_error();
                    }

                    break;
            case 'user':
                    // insert users
                    if (!empty($this->user_data['user_name'])) {
                        if (empty($this->user_data['user_regdate'])) {
                            $this->user_data['user_regdate'] = date(K_TIMESTAMP_FORMAT);
                        }

                        if (empty($this->user_data['user_ip'])) {
                            $this->user_data['user_ip'] = (string) get_normalized_ip($_SERVER['REMOTE_ADDR']);
                        }

                        if ((string) $this->user_data['user_level'] === '') {
                            $this->user_data['user_level'] = 1;
                        }

                        $session_user_level = (int) ($_SESSION['session_user_level'] ?? 0);
                        $session_user_id = (int) ($_SESSION['session_user_id'] ?? 0);
                        if ($session_user_level < K_AUTH_ADMINISTRATOR) {
                            // you cannot edit a user with a level equal or higher than yours
                            $this->user_data['user_level'] = min(
                                max(0, $session_user_level - 1),
                                (int) $this->user_data['user_level'],
                            );
                            // non-administrator can access only to his/her groups
                            if (empty($this->group_data)) {
                                break;
                            }

                            $common_groups = array_intersect(
                                F_get_user_groups($session_user_id),
                                $this->group_data,
                            );
                            if ($common_groups === []) {
                                break;
                            }
                        }

                        // check if user already exist
                        $sql =
                            'SELECT user_id,user_level
						FROM '
                            . K_TABLE_USERS
                            . '
						WHERE user_name=\''
                            . $this->user_data['user_name']
                            . '\'
							OR user_regnumber=\''
                            . $this->user_data['user_regnumber']
                            . '\'
							OR user_ssn=\''
                            . $this->user_data['user_ssn']
                            . '\'
						LIMIT 1';
                        /** @var mixed $r */
                        $r = F_db_query($sql, $db);
                        if ($r) {
                            /** @var mixed $m */
                            $m = F_db_fetch_array($r);
                            if (is_array($m)) {
                                // the user has been already added
                                $user_id = (int) ($m['user_id'] ?? 0);
                                if (
                                    $session_user_level >= K_AUTH_ADMINISTRATOR
                                    || $session_user_level > (int) ($m['user_level'] ?? 0)
                                ) {
                                    //update user data
                                    $sqlu =
                                        'UPDATE '
                                        . K_TABLE_USERS
                                        . ' SET
									user_regdate=\''
                                        . $this->user_data['user_regdate']
                                        . '\',
									user_ip=\''
                                        . $this->user_data['user_ip']
                                        . '\',
									user_name=\''
                                        . $this->user_data['user_name']
                                        . '\',
									user_email='
                                        . f_empty_to_null($this->user_data['user_email'])
                                        . ',';
                                    // update password only if it is specified
                                    if (!empty($this->user_data['user_password'])) {
                                        $sqlu .=
                                            " user_password='"
                                            . F_escape_sql($db, get_password_hash((string) $this->user_data['user_password']))
                                            . "',";
                                    }

                                    $sqlu .=
                                        '
									user_regnumber='
                                        . f_empty_to_null($this->user_data['user_regnumber'])
                                        . ',
									user_firstname='
                                        . f_empty_to_null($this->user_data['user_firstname'])
                                        . ',
									user_lastname='
                                        . f_empty_to_null($this->user_data['user_lastname'])
                                        . ',
									user_birthdate='
                                        . f_empty_to_null($this->user_data['user_birthdate'])
                                        . ',
									user_birthplace='
                                        . f_empty_to_null($this->user_data['user_birthplace'])
                                        . ',
									user_ssn='
                                        . f_empty_to_null($this->user_data['user_ssn'])
                                        . ',
									user_level=\''
                                        . $this->user_data['user_level']
                                        . '\',
									user_verifycode='
                                        . f_empty_to_null($this->user_data['user_verifycode'])
                                        . ',
									user_otpkey='
                                        . f_empty_to_null($this->user_data['user_otpkey'])
                                        . '
									WHERE user_id='
                                        . $user_id
                                        . '';
                                    /** @var mixed $ru */
                                    $ru = F_db_query($sqlu, $db);
                                    if (!$ru) {
                                        F_display_db_error(false);
                                        return false;
                                    }
                                } else {
                                    // no user is updated, so empty groups
                                    $this->group_data = [];
                                }
                            } else {
                                // add new user
                                $sqlu =
                                    'INSERT INTO '
                                    . K_TABLE_USERS
                                    . ' (
								user_regdate,
								user_ip,
								user_name,
								user_email,
								user_password,
								user_regnumber,
								user_firstname,
								user_lastname,
								user_birthdate,
								user_birthplace,
								user_ssn,
								user_level,
								user_verifycode,
								user_otpkey
								) VALUES (
								'
                                    . f_empty_to_null($this->user_data['user_regdate'])
                                    . ',
								\''
                                    . $this->user_data['user_ip']
                                    . '\',
								\''
                                    . $this->user_data['user_name']
                                    . '\',
								'
                                    . f_empty_to_null($this->user_data['user_email'])
                                    . ',
								\''
                                    . F_escape_sql($db, get_password_hash((string) $this->user_data['user_password']))
                                    . '\',
								'
                                    . f_empty_to_null($this->user_data['user_regnumber'])
                                    . ',
								'
                                    . f_empty_to_null($this->user_data['user_firstname'])
                                    . ',
								'
                                    . f_empty_to_null($this->user_data['user_lastname'])
                                    . ',
								'
                                    . f_empty_to_null($this->user_data['user_birthdate'])
                                    . ',
								'
                                    . f_empty_to_null($this->user_data['user_birthplace'])
                                    . ',
								'
                                    . f_empty_to_null($this->user_data['user_ssn'])
                                    . ',
								\''
                                    . $this->user_data['user_level']
                                    . '\',
								'
                                    . f_empty_to_null($this->user_data['user_verifycode'])
                                    . ',
								'
                                    . f_empty_to_null($this->user_data['user_otpkey'])
                                    . '
								)';
                                /** @var mixed $ru */
                                $ru = F_db_query($sqlu, $db);
                                if (!$ru) {
                                    F_display_db_error(false);
                                    return false;
                                }

                                $user_id = (int) F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                            }
                        } else {
                            F_display_db_error(false);
                            return false;
                        }

                        // user's groups
                        if (!empty($this->group_data)) {
                            foreach ($this->group_data as $group_id) {
                                // check if user-group already exist
                                $sqls =
                                    'SELECT *
								FROM '
                                    . K_TABLE_USERGROUP
                                    . '
								WHERE usrgrp_group_id=\''
                                    . $group_id
                                    . '\'
									AND usrgrp_user_id=\''
                                    . $user_id
                                    . '\'
								LIMIT 1';
                                /** @var mixed $rs */
                                $rs = F_db_query($sqls, $db);
                                if ($rs) {
                                    /** @var mixed $ms */
                                    $ms = F_db_fetch_array($rs);
                                    if (!$ms) {
                                        // associate group to user
                                        $sqlg =
                                            'INSERT INTO '
                                            . K_TABLE_USERGROUP
                                            . ' (
										usrgrp_user_id,
										usrgrp_group_id
										) VALUES (
										'
                                            . $user_id
                                            . ',
										'
                                            . $group_id
                                            . '
										)';
                                        /** @var mixed $rg */
                                        $rg = F_db_query($sqlg, $db);
                                        if (!$rg) {
                                            F_display_db_error(false);
                                            return false;
                                        }
                                    }
                                } else {
                                    F_display_db_error(false);
                                    return false;
                                }
                            }
                        }
                    }

                    break;
            default:
                    break;
        }

        return null;
    }

    /**
     * Sets the character data handler function for the XML parser parser.handler.
     * @param $parser (resource) The first parameter, parser, is a reference to the XML parser calling the handler.
     * @param $data (string) The second parameter, data, contains the character data as a string.
     * @private
     */
    // @mago-expect analysis:unused-parameter -- callback signature is defined by ext-xml
    private function segContentHandler(mixed $parser, string $data): void
    {
        if (strlen($this->current_element) > 0) {
            // we are inside an element
            $this->current_data .= $data;
        }
    }
} // END OF CLASS

/**
 * Import users from TSV file (tab delimited text).
 * The format of TSV is the same obtained by exporting data from Users Selection Form.
 * @param $tsvfile (string) TSV (tab delimited text) file name
 * @return boolean TRUE in case of success, FALSE otherwise
 */
function f_import_tsv_users(mixed $tsvfile): bool
{
    global $l, $db;
    require_once '../config/tce_config.php';

    // get file content as array
    $tsvrows = file((string) $tsvfile); // array of TSV lines
    if ($tsvrows === false) {
        return false;
    }

    foreach (array_slice($tsvrows, 1) as $rowdata) {
        // get user data into array
        $userdata = explode("\t", $rowdata);
        /** @var array{
         *   0: string, 1: string, 2: string, 3: string, 4: string, 5: string,
         *   6: string, 7: string, 8: string, 9: string, 10: string, 11: string,
         *   12: string, 13: string, 14: string, 15: string
         * } $userdata
         */

        // set some default values
        if (empty($userdata[4])) {
            $userdata[4] = date(K_TIMESTAMP_FORMAT);
        }

        if (empty($userdata[5])) {
            $userdata[5] = get_normalized_ip($_SERVER['REMOTE_ADDR']);
        }

        // user level
        if ($userdata[12] === '') {
            $userdata[12] = '1';
        }

        $session_user_level = (int) ($_SESSION['session_user_level'] ?? 0);
        $session_user_id = (int) ($_SESSION['session_user_id'] ?? 0);
        if ($session_user_level < K_AUTH_ADMINISTRATOR) {
            // you cannot edit a user with a level equal or higher than yours
            $userdata[12] = (string) min(max(0, $session_user_level - 1), (int) $userdata[12]);
            // non-administrator can access only to his/her groups
            if (empty($userdata[15])) {
                break;
            }

            $usrgroups = explode(',', addslashes($userdata[15]));
            $available_groups = F_get_user_groups($session_user_id);
            $common_groups = array_intersect($available_groups, $usrgroups);
            if ($common_groups === []) {
                break;
            }
        }

        // check if user already exist
        $sql =
            'SELECT user_id,user_level
			FROM '
            . K_TABLE_USERS
            . '
			WHERE user_name=\''
            . F_escape_sql($db, $userdata[1])
            . '\'
				OR user_regnumber='
            . f_empty_to_null($userdata[10])
            . '
				OR user_ssn='
            . f_empty_to_null($userdata[11])
            . '
			LIMIT 1';
        /** @var mixed $r */
        $r = F_db_query($sql, $db);
        if ($r) {
            /** @var mixed $m */
            $m = F_db_fetch_array($r);
            if (is_array($m)) {
                // the user has been already added
                $user_id = (int) ($m['user_id'] ?? 0);
                if (
                    $session_user_level >= K_AUTH_ADMINISTRATOR
                    || $session_user_level > (int) ($m['user_level'] ?? 0)
                ) {
                    //update user data
                    $sqlu = 'UPDATE ' . K_TABLE_USERS . ' SET
						user_name=\'' . F_escape_sql($db, $userdata[1]) . "',";
                    // update password only if it is specified
                    if (!empty($userdata[2])) {
                        $sqlu .= " user_password='" . F_escape_sql($db, get_password_hash($userdata[2])) . "',";
                    }

                    $sqlu .=
                        '
						user_email='
                        . f_empty_to_null($userdata[3])
                        . ',
						user_regdate=\''
                        . F_escape_sql($db, $userdata[4])
                        . '\',
						user_ip=\''
                        . F_escape_sql($db, $userdata[5])
                        . '\',
						user_firstname='
                        . f_empty_to_null($userdata[6])
                        . ',
						user_lastname='
                        . f_empty_to_null($userdata[7])
                        . ',
						user_birthdate='
                        . f_empty_to_null($userdata[8])
                        . ',
						user_birthplace='
                        . f_empty_to_null($userdata[9])
                        . ',
						user_regnumber='
                        . f_empty_to_null($userdata[10])
                        . ',
						user_ssn='
                        . f_empty_to_null($userdata[11])
                        . ',
						user_level=\''
                        . (int) $userdata[12]
                        . '\',
						user_verifycode='
                        . f_empty_to_null($userdata[13])
                        . ',
						user_otpkey='
                        . f_empty_to_null($userdata[14])
                        . '
						WHERE user_id='
                        . $user_id
                        . '';
                    /** @var mixed $ru */
                    $ru = F_db_query($sqlu, $db);
                    if (!$ru) {
                        F_display_db_error(false);
                        return false;
                    }
                } else {
                    // no user is updated, so empty groups
                    $userdata[15] = '';
                }
            } else {
                // add new user
                $sqlu =
                    'INSERT INTO '
                    . K_TABLE_USERS
                    . ' (
					user_name,
					user_password,
					user_email,
					user_regdate,
					user_ip,
					user_firstname,
					user_lastname,
					user_birthdate,
					user_birthplace,
					user_regnumber,
					user_ssn,
					user_level,
					user_verifycode,
					user_otpkey
					) VALUES (
					\''
                    . F_escape_sql($db, $userdata[1])
                    . '\',
					\''
                    . F_escape_sql($db, get_password_hash($userdata[2]))
                    . '\',
					'
                    . f_empty_to_null($userdata[3])
                    . ',
					\''
                    . F_escape_sql($db, $userdata[4])
                    . '\',
					\''
                    . F_escape_sql($db, $userdata[5])
                    . '\',
					'
                    . f_empty_to_null($userdata[6])
                    . ',
					'
                    . f_empty_to_null($userdata[7])
                    . ',
					'
                    . f_empty_to_null($userdata[8])
                    . ',
					'
                    . f_empty_to_null($userdata[9])
                    . ',
					'
                    . f_empty_to_null($userdata[10])
                    . ',
					'
                    . f_empty_to_null($userdata[11])
                    . ',
					\''
                    . (int) $userdata[12]
                    . '\',
					'
                    . f_empty_to_null($userdata[13])
                    . ',
					'
                    . f_empty_to_null($userdata[14])
                    . '
					)';
                /** @var mixed $ru */
                $ru = F_db_query($sqlu, $db);
                if (!$ru) {
                    F_display_db_error(false);
                    return false;
                }

                $user_id = (int) F_db_insert_id($db, K_TABLE_USERS, 'user_id');
            }
        } else {
            F_display_db_error(false);
            return false;
        }

        // user's groups
        if (!empty($userdata[15])) {
            $groups = preg_replace("/[\r\n]+/", '', $userdata[15]) ?? $userdata[15];
            $groups = explode(',', addslashes($groups));
            foreach ($groups as $group_name) {
                $group_name = F_escape_sql($db, $group_name);
                // check if group already exist
                $sql = 'SELECT group_id
					FROM ' . K_TABLE_GROUPS . '
					WHERE group_name=\'' . $group_name . '\'
					LIMIT 1';
                /** @var mixed $r */
                $r = F_db_query($sql, $db);
                if ($r) {
                    /** @var mixed $m */
                    $m = F_db_fetch_array($r);
                    if (is_array($m)) {
                        // the group already exist
                        $group_id = (int) ($m['group_id'] ?? 0);
                    } else {
                        // create a new group
                        $sqli = 'INSERT INTO ' . K_TABLE_GROUPS . ' (
							group_name
							) VALUES (
							\'' . $group_name . '\'
							)';
                        /** @var mixed $ri */
                        $ri = F_db_query($sqli, $db);
                        if (!$ri) {
                            F_display_db_error(false);
                            return false;
                        }

                        $group_id = (int) F_db_insert_id($db, K_TABLE_GROUPS, 'group_id');
                    }
                } else {
                    F_display_db_error(false);
                    return false;
                }

                // check if user-group already exist
                $sqls =
                    'SELECT *
					FROM '
                    . K_TABLE_USERGROUP
                    . '
					WHERE usrgrp_group_id=\''
                    . $group_id
                    . '\'
						AND usrgrp_user_id=\''
                    . $user_id
                    . '\'
					LIMIT 1';
                /** @var mixed $rs */
                $rs = F_db_query($sqls, $db);
                if ($rs) {
                    /** @var mixed $ms */
                    $ms = F_db_fetch_array($rs);
                    if (!$ms) {
                        // associate group to user
                        $sqlg =
                            'INSERT INTO ' . K_TABLE_USERGROUP . ' (
							usrgrp_user_id,
							usrgrp_group_id
							) VALUES (
							' . $user_id . ',
							' . $group_id . '
							)';
                        /** @var mixed $rg */
                        $rg = F_db_query($sqlg, $db);
                        if (!$rg) {
                            F_display_db_error(false);
                            return false;
                        }
                    }
                } else {
                    F_display_db_error(false);
                    return false;
                }
            }
        }
    }

    return true;
}
