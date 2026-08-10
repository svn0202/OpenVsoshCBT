<?php

//============================================================+
// File name   : XMLQuestionImporter.php
// Begin       : 2006-03-12
// Last Update : 2023-11-30
//
// Description : Class to import questions from an XML file.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Class to import questions from an XML file.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-12
 */

/**
 * @class XMLQuestionImporter
 * This PHP Class imports question data directly from an XML file.
 * @package com.tecnick.tcexam.admin
 * @version 1.1.000
 */
class XMLQuestionImporter
{
    public XMLParser $parser;

    /**
     * Current level: 'module', 'subject', 'question', 'answer'.
     * @private
     */
    private string $level = '';

    /**
     * Array to store current level data.
     * @var array<string, array<string, int|float|string|false>>
     * @private
     */
    private array $level_data = [];

    /**
     * Current data element.
     * @private
     */
    private string $current_element = '';

    /**
     * Current data value.
     * @private
     */
    private string $current_data = '';

    /**
     * Boolean values.
     * @var array<string, string>
     * @private
     */
    private array $boolval = [
        'false' => '0',
        'true' => '1',
    ];

    /**
     * Type of questions.
     * @var array<string, string>
     * @private
     */
    private array $qtype = [
        'single' => '1',
        'multiple' => '2',
        'text' => '3',
        'ordering' => '4',
        'matching' => '5',
    ];

    /**
     * Store hash values of question descriptions.
     * This is used to avoid the 255 chars limitation for string indexes on MySQL
     * @var list<string>
     * @private
     */
    private array $questionhash = [];

    /**
     * Class constructor.
     * @param $xmlfile (string) xml (XML) file name
     * @return true or die for parsing error
     */
    public function __construct(
        /**
         * XML file.
         * @private
         */
        private string $xmlfile,
    ) {
        // creates a new XML parser to be used by the other XML functions
        $this->parser = xml_parser_create();
        // disable case-folding for this XML parser
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        // sets the element handler functions for the XML parser
        xml_set_element_handler(
            $this->parser,
            [$this, 'startElementHandler'],
            [$this, 'endElementHandler'],
        );
        // sets the character data handler function for the XML parser
        xml_set_character_data_handler($this->parser, [$this, 'segContentHandler']);
        // start parsing an XML document
        $xml = file_get_contents($xmlfile);
        if ($xml === false || xml_parse($this->parser, $xml) === 0) {
            die(sprintf(
                'ERROR xmlResourceBundle :: XML error: %s at line %d',
                xml_error_string(xml_get_error_code($this->parser)),
                xml_get_current_line_number($this->parser),
            ));
        }

        return true;
    }

    public function __destruct()
    {
        // delete uploaded file
        if (is_file($this->xmlfile) || is_link($this->xmlfile)) {
            unlink($this->xmlfile);
        }
    }

    /**
     * Sets the start element handler function for the XML parser parser.start_element_handler.
     * @param XMLParser $_parser XML parser calling the handler.
     * @param $name (string) The second parameter, name, contains the name of the element for which this handler is called. If case-folding is in effect for this parser, the element name will be in uppercase letters.
     * @param array<string, string> $_attribs Element attributes keyed by attribute name.
     * @private
     */
    private function startElementHandler(XMLParser $_parser, string $name, array $_attribs): void
    {
        $name = strtolower($name);
        switch ($name) {
            case 'module':
            case 'subject':
            case 'question':
            case 'answer':
                $this->level = $name;
                $this->level_data[$name] = [];
                $this->current_data = '';
                switch ($name) {
                    case 'module':
                        $this->level_data['module']['module_name'] = 'default';
                        $this->level_data['module']['module_enabled'] = 'false';
                        $this->level_data['module']['module_user_id'] = '1';
                        break;
                    case 'subject':
                        $this->addModule();
                        $this->level_data['subject']['subject_name'] = 'default';
                        $this->level_data['subject']['subject_description'] = 'default';
                        $this->level_data['subject']['subject_enabled'] = 'false';
                        $this->level_data['subject']['subject_user_id'] = '1';
                        $this->level_data['subject']['subject_module_id'] = '1';
                        break;
                    case 'question':
                        $this->addSubject();
                        $this->level_data['question']['question_subject_id'] = '1';
                        $this->level_data['question']['question_description'] = 'default';
                        $this->level_data['question']['question_explanation'] = '';
                        $this->level_data['question']['question_type'] = 'single';
                        $this->level_data['question']['question_difficulty'] = '0';
                        $this->level_data['question']['question_enabled'] = 'false';
                        $this->level_data['question']['question_position'] = 0;
                        $this->level_data['question']['question_timer'] = 0;
                        $this->level_data['question']['question_fullscreen'] = 'false';
                        $this->level_data['question']['question_inline_answers'] = 'false';
                        $this->level_data['question']['question_auto_next'] = 'false';
                        $this->level_data['question']['question_shuffle_answers'] = 'false';
                        break;
                    case 'answer':
                        $this->addQuestion();
                        $this->level_data['answer']['answer_question_id'] = '1';
                        $this->level_data['answer']['answer_description'] = 'default';
                        $this->level_data['answer']['answer_explanation'] = '';
                        $this->level_data['answer']['answer_isright'] = 'false';
                        $this->level_data['answer']['answer_enabled'] = 'false';
                        $this->level_data['answer']['answer_position'] = '0';
                        $this->level_data['answer']['answer_keyboard_key'] = '';
                        $this->level_data['answer']['answer_weight'] = '';
                        break;
                }

                break;
            default:
                $this->current_element = $this->level . '_' . $name;
                $this->current_data = '';
                break;
        }
    }

    /**
     * Sets the end element handler function for the XML parser parser.end_element_handler.
     * @param $parser (resource) The first parameter, parser, is a reference to the XML parser calling the handler.
     * @param $name (string) The second parameter, name, contains the name of the element for which this handler is called. If case-folding is in effect for this parser, the element name will be in uppercase letters.
     * @private
     */
    private function endElementHandler(XMLParser $_parser, string $name): void
    {
        global $l, $db;
        require_once '../config/tce_config.php';
        $name = strtolower($name);
        switch ($name) {
            case 'module':
                $this->addModule();
                $this->level = '';
                break;
            case 'subject':
                $this->addSubject();
                $this->level = 'module';
                break;
            case 'question':
                $this->addQuestion();
                $this->level = 'subject';
                break;
            case 'answer':
                $this->addAnswer();
                $this->level = 'question';
                break;
            default:
                $elname = $this->level . '_' . $name;
                if ($this->current_element === $elname) {
                    // convert XML special chars
                    $value = f_xml_to_text(utrim($this->current_data));
                    if (
                        $this->current_element === 'question_description'
                        || $this->current_element === 'answer_description'
                    ) {
                        // normalize UTF-8 string based on settings
                        $value = f_utf8_normalizer($value, K_UTF8_NORMALIZATION_MODE);
                    }

                    // escape for SQL
                    $this->level_data[$this->level][$this->current_element] = $this->stringValue(
                        F_escape_sql($db, $value, false),
                    );
                }

                break;
        }
    }

    /**
     * Sets the character data handler function for the XML parser parser.handler.
     * @param $parser (resource) The first parameter, parser, is a reference to the XML parser calling the handler.
     * @param $data (string) The second parameter, data, contains the character data as a string.
     * @private
     */
    private function segContentHandler(XMLParser $_parser, string $data): void
    {
        if (strlen($this->current_element) > 0) {
            // we are inside an element
            $this->current_data .= $data;
        }
    }

    /**
     * Add a new module if not exist.
     * @private
     */
    private function addModule(): void
    {
        global $l, $db;
        require_once '../config/tce_config.php';
        require_once '../../shared/code/tce_functions_auth_sql.php';
        if (!isset($this->level_data['module'])) {
            return;
        }

        /** @var array{module_id?:int|string|false,module_name:string,module_enabled:string,module_user_id:int|string} $module */
        $module = &$this->level_data['module'];
        /** @var array{session_user_id:int|string} $session */
        $session = $_SESSION;
        if (isset($module['module_id']) && $module['module_id'] !== false && $module['module_id'] > 0) {
            return;
        }

        // check if this module already exist
        $sql =
            'SELECT module_id
			FROM '
            . K_TABLE_MODULES
            . '
			WHERE module_name=\''
            . $module['module_name']
            . '\'
			LIMIT 1';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if ($r) {
            $m = $this->databaseRow(F_db_fetch_array($r));
            if ($m) {
                /** @var array{module_id:int|string} $m */
                // get existing module ID
                if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $m['module_id'], 'module_user_id')) {
                    // unauthorized user
                    $module['module_id'] = false;
                } else {
                    $module['module_id'] = $m['module_id'];
                }
            } else {
                // insert new module
                $sql =
                    'INSERT INTO '
                    . K_TABLE_MODULES
                    . ' (
					module_name,
					module_enabled,
					module_user_id
					) VALUES (
					\''
                    . $module['module_name']
                    . '\',
					\''
                    . ($this->boolval[$module['module_enabled']] ?? '0')
                    . '\',
					\''
                    . $session['session_user_id']
                    . '\'
					)';
                /** @var object|resource|bool $r */
                $r = F_db_query($sql, $db);
                if (!$r) {
                    F_display_db_error();
                } else {
                    // get new module ID
                    /** @var int|numeric-string $module_id */
                    $module_id = F_db_insert_id($db, K_TABLE_MODULES, 'module_id');
                    $module['module_id'] = $module_id;
                }
            }
        } else {
            F_display_db_error();
        }
    }

    /**
     * Add a new subject if not exist.
     * @private
     */
    private function addSubject(): void
    {
        global $l, $db;
        require_once '../config/tce_config.php';
        if (!isset($this->level_data['module'], $this->level_data['subject'])) {
            return;
        }

        /** @var array{module_id:int|string|false} $module */
        $module = &$this->level_data['module'];
        /** @var array{subject_id?:int|string|false,subject_name:string,subject_description:string,subject_enabled:string,subject_user_id:int|string,subject_module_id:int|string} $subject */
        $subject = &$this->level_data['subject'];
        /** @var array{session_user_id:int|string} $session */
        $session = $_SESSION;
        if ($module['module_id'] === false) {
            return;
        }

        if (isset($subject['subject_id']) && $subject['subject_id'] !== false && $subject['subject_id'] > 0) {
            return;
        }

        // check if this subject already exist
        $sql =
            'SELECT subject_id
			FROM '
            . K_TABLE_SUBJECTS
            . '
			WHERE subject_name=\''
            . $subject['subject_name']
            . '\'
				AND subject_module_id='
            . $module['module_id']
            . '
			LIMIT 1';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if ($r) {
            $m = $this->databaseRow(F_db_fetch_array($r));
            if ($m) {
                /** @var array{subject_id:int|string} $m */
                // get existing subject ID
                $subject['subject_id'] = $m['subject_id'];
            } else {
                // insert new subject
                $sql =
                    'INSERT INTO '
                    . K_TABLE_SUBJECTS
                    . ' (
					subject_name,
					subject_description,
					subject_enabled,
					subject_user_id,
					subject_module_id
					) VALUES (
					\''
                    . $subject['subject_name']
                    . '\',
					'
                    . f_empty_to_null($subject['subject_description'])
                    . ',
					\''
                    . ($this->boolval[$subject['subject_enabled']] ?? '0')
                    . '\',
					\''
                    . $session['session_user_id']
                    . '\',
					'
                    . $module['module_id']
                    . '
					)';
                /** @var object|resource|bool $r */
                $r = F_db_query($sql, $db);
                if (!$r) {
                    F_display_db_error();
                } else {
                    // get new subject ID
                    /** @var int|numeric-string $subject_id */
                    $subject_id = F_db_insert_id($db, K_TABLE_SUBJECTS, 'subject_id');
                    $subject['subject_id'] = $subject_id;
                }
            }
        } else {
            F_display_db_error();
        }
    }

    /**
     * Add a new question if not exist.
     * @private
     */
    private function addQuestion(): void
    {
        global $l, $db;
        require_once '../config/tce_config.php';
        if (!isset($this->level_data['module'], $this->level_data['subject'], $this->level_data['question'])) {
            return;
        }

        /** @var array{module_id:int|string|false} $module */
        $module = &$this->level_data['module'];
        /** @var array{subject_id:int|string|false} $subject */
        $subject = &$this->level_data['subject'];
        /** @var array{question_id?:int|string|false,question_subject_id:int|string,question_description:string,question_explanation:string,question_type:string,question_difficulty:int|string,question_enabled:string,question_position:int|string,question_timer:int|string,question_fullscreen:string,question_inline_answers:string,question_auto_next:string,question_shuffle_answers:string} $question */
        $question = &$this->level_data['question'];
        if ($module['module_id'] === false) {
            return;
        }

        if ($subject['subject_id'] === false) {
            return;
        }

        if (isset($question['question_id']) && $question['question_id'] !== false && $question['question_id'] > 0) {
            return;
        }

        $database_type = $this->databaseType(K_DATABASE_TYPE);

        // check if this question already exist
        $sql = 'SELECT question_id
			FROM ' . K_TABLE_QUESTIONS . '
			WHERE ';
        if (strcmp($database_type, 'ORACLE') === 0) {
            $sql .=
                "dbms_lob.instr(question_description,'"
                . $question['question_description']
                . "',1,1)>0";
        } elseif ($database_type === 'MYSQL' && $this->booleanConfig(K_MYSQL_QA_BIN_UNIQUITY)) {
            $sql .=
                "question_description='"
                . $question['question_description']
                . "' COLLATE "
                . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
        } else {
            $sql .= "question_description='" . $question['question_description'] . "'";
        }

        $sql .= ' AND question_subject_id=' . $subject['subject_id'] . ' LIMIT 1';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if ($r) {
            $m = $this->databaseRow(F_db_fetch_array($r));
            if ($m) {
                /** @var array{question_id:int|string} $m */
                // get existing question ID
                $question['question_id'] = $m['question_id'];
                return;
            }
        } else {
            F_display_db_error();
        }

        $strkeylimit = 0;
        if ($database_type === 'MYSQL') {
            // this section is to avoid the problems on MySQL string comparison
            $maxkey = 240;
            $strkeylimit = min($maxkey, strlen($question['question_description']));
            $stop = intdiv($maxkey, 3);
            while (
                in_array(
                    md5(strtolower(substr(
                        $subject['subject_id'] . $question['question_description'],
                        0,
                        $strkeylimit,
                    ))),
                    $this->questionhash,
                )
                && $stop > 0
            ) {
                // a similar question was already imported from this XML, so we change it a little bit to avoid duplicate keys
                $question['question_description'] = '_' . $question['question_description'];
                $strkeylimit = min($maxkey, $strkeylimit + 1);
                --$stop; // variable used to avoid infinite loop
            }

            if ($stop === 0) {
                F_print_error('ERROR', 'Unable to get unique question ID');
                return;
            }
        }

        $sql = 'START TRANSACTION';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if (!$r) {
            F_display_db_error();
        }

        // insert question
        $sql =
            'INSERT INTO '
            . K_TABLE_QUESTIONS
            . ' (
			question_subject_id,
			question_description,
			question_explanation,
			question_type,
			question_difficulty,
			question_enabled,
			question_position,
			question_timer,
			question_fullscreen,
			question_inline_answers,
			question_auto_next,
			question_shuffle_answers
			) VALUES (
			'
            . $subject['subject_id']
            . ',
			\''
            . $question['question_description']
            . '\',
			'
            . f_empty_to_null($question['question_explanation'])
            . ',
			\''
            . ($this->qtype[$question['question_type']] ?? '1')
            . '\',
			\''
            . $question['question_difficulty']
            . '\',
			\''
            . ($this->boolval[$question['question_enabled']] ?? '0')
            . '\',
			'
            . f_zero_to_null((int) $question['question_position'])
            . ',
			\''
            . $question['question_timer']
            . '\',
			\''
            . ($this->boolval[$question['question_fullscreen']] ?? '0')
            . '\',
			\''
            . ($this->boolval[$question['question_inline_answers']] ?? '0')
            . '\',
			\''
            . ($this->boolval[$question['question_auto_next']] ?? '0')
            . '\',
			\''
            . ($this->boolval[$question['question_shuffle_answers']] ?? '0')
            . '\'
			)';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if (!$r) {
            F_display_db_error(false);
        } else {
            // get new question ID
            /** @var int|numeric-string $question_id */
            $question_id = F_db_insert_id($db, K_TABLE_QUESTIONS, 'question_id');
            $question['question_id'] = $question_id;
            if ($database_type === 'MYSQL') {
                $this->questionhash[] = md5(strtolower(substr(
                    $subject['subject_id'] . $question['question_description'],
                    0,
                    $strkeylimit,
                )));
            }
        }

        $sql = 'COMMIT';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if (!$r) {
            F_display_db_error();
        }
    }

    /**
     * Add a new answer if not exist.
     * @private
     */
    private function addAnswer(): void
    {
        global $l, $db;
        require_once '../config/tce_config.php';
        if (
            !isset(
                $this->level_data['module'],
                $this->level_data['subject'],
                $this->level_data['question'],
                $this->level_data['answer'],
            )
        ) {
            return;
        }

        /** @var array{module_id:int|string|false} $module */
        $module = &$this->level_data['module'];
        /** @var array{subject_id:int|string|false} $subject */
        $subject = &$this->level_data['subject'];
        /** @var array{question_id:int|string|false} $question */
        $question = &$this->level_data['question'];
        /** @var array{answer_id?:int|string|false,answer_question_id:int|string,answer_description:string,answer_explanation:string,answer_isright:string,answer_enabled:string,answer_position:int|string,answer_keyboard_key:string,answer_weight:int|float|string} $answer */
        $answer = &$this->level_data['answer'];
        if ($module['module_id'] === false) {
            return;
        }

        if ($subject['subject_id'] === false || $question['question_id'] === false) {
            return;
        }

        if (isset($answer['answer_id']) && $answer['answer_id'] !== false && $answer['answer_id'] > 0) {
            return;
        }

        $database_type = $this->databaseType(K_DATABASE_TYPE);

        // check if this answer already exist
        $sql = 'SELECT answer_id
			FROM ' . K_TABLE_ANSWERS . '
			WHERE ';
        if (strcmp($database_type, 'ORACLE') === 0) {
            $sql .=
                "dbms_lob.instr(answer_description, '" . $answer['answer_description'] . "',1,1)>0";
        } elseif ($database_type === 'MYSQL' && $this->booleanConfig(K_MYSQL_QA_BIN_UNIQUITY)) {
            $sql .=
                "answer_description='"
                . $answer['answer_description']
                . "' COLLATE "
                . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
        } else {
            $sql .= "answer_description='" . $answer['answer_description'] . "'";
        }

        $sql .= ' AND answer_question_id=' . $question['question_id'] . ' LIMIT 1';
        /** @var object|resource|bool $r */
        $r = F_db_query($sql, $db);
        if ($r) {
            $m = $this->databaseRow(F_db_fetch_array($r));
            if ($m) {
                /** @var array{answer_id:int|string} $m */
                // get existing subject ID
                $answer['answer_id'] = $m['answer_id'];
            } else {
                $sql = 'START TRANSACTION';
                /** @var object|resource|bool $r */
                $r = F_db_query($sql, $db);
                if (!$r) {
                    F_display_db_error();
                }

                $sql =
                    'INSERT INTO '
                    . K_TABLE_ANSWERS
                    . ' (
					answer_question_id,
					answer_description,
					answer_explanation,
					answer_isright,
						answer_enabled,
						answer_position,
						answer_keyboard_key,
						answer_weight
					) VALUES (
					'
                    . $question['question_id']
                    . ',
					\''
                    . $answer['answer_description']
                    . '\',
					'
                    . f_empty_to_null($answer['answer_explanation'])
                    . ',
					\''
                    . ($this->boolval[$answer['answer_isright']] ?? '0')
                    . '\',
					\''
                    . ($this->boolval[$answer['answer_enabled']] ?? '0')
                    . '\',
					'
                    . f_zero_to_null((int) $answer['answer_position'])
                    . ',
					'
                    . f_empty_to_null($answer['answer_keyboard_key'])
                    . ',
						'
                    . f_empty_to_null($answer['answer_weight'])
                    . '
						)';
                /** @var object|resource|bool $r */
                $r = F_db_query($sql, $db);
                if (!$r) {
                    F_display_db_error(false);
                    F_db_query('ROLLBACK', $db);
                } else {
                    // get new answer ID
                    /** @var int|numeric-string $answer_id */
                    $answer_id = F_db_insert_id($db, K_TABLE_ANSWERS, 'answer_id');
                    $answer['answer_id'] = $answer_id;
                }

                $sql = 'COMMIT';
                /** @var object|resource|bool $r */
                $r = F_db_query($sql, $db);
                if (!$r) {
                    F_display_db_error();
                }
            }
        } else {
            F_display_db_error();
        }
    }

    /** @return array<array-key,mixed>|null */
    private function databaseRow(mixed $row): ?array
    {
        return is_array($row) ? $row : null;
    }

    private function databaseType(mixed $database_type): string
    {
        return is_array($database_type) ? 'Array' : (string) $database_type;
    }

    private function stringValue(mixed $value): string
    {
        return is_array($value) ? 'Array' : (string) $value;
    }

    private function booleanConfig(bool $value): bool
    {
        return $value;
    }
} // END OF CLASS
