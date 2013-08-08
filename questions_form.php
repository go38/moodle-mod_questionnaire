<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @authors Mike Churchward & Joseph Rézeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionnaire
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

class questionnaire_questions_form extends moodleform {

    public function __construct($action, $moveq=false) {
        $this->moveq = $moveq;
        return parent::__construct($action);
    }

    public function definition() {
        global $CFG, $questionnaire, $SESSION, $OUTPUT;
        global $DB;

        $sid = $questionnaire->survey->id;
        $mform    =& $this->_form;

        $mform->addElement('header', 'questionhdr', get_string('addquestions', 'questionnaire'));
        $mform->addHelpButton('questionhdr', 'questiontypes', 'questionnaire');

        $stredit = get_string('edit', 'questionnaire');
        $strremove = get_string('remove', 'questionnaire');
        $strmove = get_string('move');
        $strmovehere = get_string('movehere');
        $stryes = get_string('yes');
        $strno = get_string('no');
        $strposition = get_string('position', 'questionnaire');

        // Set up question positions.
        if (!isset($questionnaire->questions)) {
            $questionnaire->questions = array();
        }
        $quespos = array();
        $max = count($questionnaire->questions);
        $sec = 0;
        for ($i = 1; $i <= $max; $i++) {
            $quespos[$i] = "$i";
        }

        $pos = 0;
        $numq = count($questionnaire->questions);
        $attributes = 'onChange="this.form.submit()"';

        $select = '';
        if (!($qtypes = $DB->get_records_select_menu('questionnaire_question_type', $select, null, '', 'typeid,type'))) {
            $qtypes = array();
        }
        // Needed for non-English languages.
        foreach ($qtypes as $key => $qtype) {
            $qtypes[$key] = questionnaire_get_type($key);
        }
        natsort($qtypes);
        $addqgroup = array();
        $addqgroup[] =& $mform->createElement('select', 'type_id', '', $qtypes);

        // The 'sticky' type_id value for further new questions.
        if (isset($SESSION->questionnaire->type_id)) {
                $mform->setDefault('type_id', $SESSION->questionnaire->type_id);
        }

        $addqgroup[] =& $mform->createElement('submit', 'addqbutton', get_string('addselqtype', 'questionnaire'));

        $questionnairehasdependencies = questionnaire_has_dependencies($questionnaire->questions);

        // TODO maybe this check can be removed if all needed page breaks are correctly inserted
        // when questions are created/edited/moved/deleted. Just keeping it available until further tests.
        /* if ($questionnairehasdependencies) {
            $addqgroup[] =& $mform->createElement('submit', 'validate', get_string('validate', 'questionnaire'));
        } */

        $mform->addGroup($addqgroup, 'addqgroup', '', ' ', false);

        if (isset($SESSION->questionnaire->validateresults) &&  $SESSION->questionnaire->validateresults != '') {
            $mform->addElement('static', 'validateresult', '', '<div class="qdepend warning">'.
                $SESSION->questionnaire->validateresults.'</div>');
            $SESSION->questionnaire->validateresults = '';
        }

        $qnum = 0;

        // JR skip logic :: to prevent moving child higher than parent OR parent lower than child
        // we must get now the parent and child positions.

        if ($questionnairehasdependencies) {
            $parentpositions = questionnaire_get_parent_positions ($questionnaire->questions);
            $childpositions = questionnaire_get_child_positions ($questionnaire->questions);
        }

        $mform->addElement('header', 'manageq', get_string('managequestions', 'questionnaire'));
        // TODO write specific help here.
        $mform->addHelpButton('manageq', 'managequestions', 'questionnaire');

        $mform->addElement('html', '<div class="qcontainer">');

        foreach ($questionnaire->questions as $question) {

            $manageqgroup = array();

            $qid = $question->id;
            $tid = $question->type_id;
            $qtype = $question->type;
            $required = $question->required;

            // Does this questionnaire contain branching questions already?
            $dependency = '';
            if ($questionnairehasdependencies) {
                if ($question->dependquestion != 0) {
                    $parent = questionnaire_get_parent ($question);
                    $dependency = '<strong>'.get_string('dependquestion', 'questionnaire').'</strong> : '.
                        $strposition.' '.$parent[$qid]['parentposition'].' ('.$parent[$qid]['parent'].')';
                }
            }

            $pos = $question->position;
            if ($tid != QUESPAGEBREAK && $tid != QUESSECTIONTEXT) {
                $qnum++;
            }


            // Needed for non-English languages JR.
            $qtype = '['.questionnaire_get_type($tid).']';
            $content = '';
            if ($tid == QUESPAGEBREAK) {
                $sec++;
                //$content = '<hr class="questionnaire_pagebreak">';

            } else {
                // Needed to print potential media in question text.
                $content = format_text(file_rewrite_pluginfile_urls($question->content, 'pluginfile.php',
                                $question->context->id, 'mod_questionnaire', 'question', $question->id), FORMAT_HTML);
            }

            $moveqgroup = array();

            $spacer = $OUTPUT->pix_url('spacer');

            if (!$this->moveq) {
                $mform->addElement('html', '<div class="qn-container">'); // Begin div qn-container.
                $mextra = array('value' => $question->id,
                                'alt' => $strmove,
                                'title' => $strmove);
                $eextra = array('value' => $question->id,
                                'alt' => get_string('edit', 'questionnaire'),
                                'title' => get_string('edit', 'questionnaire'));
                $rextra = array('value' => $question->id,
                                'alt' => $strremove,
                                'title' => $strremove);

                if ($tid == QUESPAGEBREAK) {
                    $esrc = $CFG->wwwroot.'/mod/questionnaire/images/editd.gif';
                    $eextra = array('disabled' => 'disabled');
                } else {
                    $esrc = $CFG->wwwroot.'/mod/questionnaire/images/edit.gif';
                }

                if ($tid == QUESPAGEBREAK) {
                    $esrc = $spacer;
                    $eextra = array('disabled' => 'disabled');
                } else {
                    $esrc = $OUTPUT->pix_url('t/edit');
                }
                $rsrc = $OUTPUT->pix_url('t/delete');
                        $qreq = '';

                // Question numbers.
                $manageqgroup[] =& $mform->createElement('static', 'qnums', '',
                                '<div class="qnums">'.$strposition.' '.$pos.'</div>');

                // Need to index by 'id' since IE doesn't return assigned 'values' for image inputs.
                $manageqgroup[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '');
                $msrc = $OUTPUT->pix_url('t/move');

                if ($questionnairehasdependencies) {
                    // Do not allow moving parent question at position #1 to be moved down if it has a child at position < 4.
                    if ($pos == 1) {
                        if (isset($childpositions[$qid])) {
                            $maxdown = $childpositions[$qid];
                            if ($maxdown < 4) {
                                $strdisabled = get_string('movedisabled', 'questionnaire');
                                $msrc = $OUTPUT->pix_url('t/block');
                                $mextra = array('value' => $question->id,
                                                'alt' => $strdisabled,
                                                'title' => $strdisabled);
                                $mextra += array('disabled' => 'disabled');
                            }
                        }
                    }
                    // Do not allow moving or deleting a page break if immediately followed by a child question
                    // or immediately preceded by a question with a dependency and followed by a non-dependent question.
                    if ($tid == QUESPAGEBREAK) {
                        if ($nextquestion = $DB->get_record('questionnaire_question', array('survey_id' => $sid,
                                        'position' => $pos + 1, 'deleted' => 'n' ), $fields='dependquestion, name, content') ) {
                            if ($previousquestion = $DB->get_record('questionnaire_question', array('survey_id' => $sid,
                                            'position' => $pos - 1, 'deleted' => 'n' ), $fields='dependquestion, name, content')) {
                                if ($nextquestion->dependquestion != 0
                                                || ($previousquestion->dependquestion != 0
                                                    && $nextquestion->dependquestion == 0) ) {
                                    $strdisabled = get_string('movedisabled', 'questionnaire');
                                    $msrc = $OUTPUT->pix_url('t/block');
                                    $mextra = array('value' => $question->id,
                                                    'alt' => $strdisabled,
                                                    'title' => $strdisabled);
                                    $mextra += array('disabled' => 'disabled');

                                    $rsrc = $msrc;
                                    $strdisabled = get_string('deletedisabled', 'questionnaire');
                                    $rextra = array('value' => $question->id,
                                                    'alt' => $strdisabled,
                                                    'title' => $strdisabled);
                                    $rextra += array('disabled' => 'disabled');
                                }
                            }
                        }
                    }
                }
                $manageqgroup[] =& $mform->createElement('image', 'movebutton['.$question->id.']',
                                $msrc, $mextra);
                $manageqgroup[] =& $mform->createElement('image', 'editbutton['.$question->id.']', $esrc, $eextra);
                $manageqgroup[] =& $mform->createElement('image', 'removebutton['.$question->id.']', $rsrc, $rextra);

                if ($tid != QUESPAGEBREAK && $tid != QUESSECTIONTEXT) {
                    if ($required == 'y') {
                        $reqsrc = $OUTPUT->pix_url('t/stop');
                        $strrequired = get_string('required', 'questionnaire');
                    } else {
                        $reqsrc = $OUTPUT->pix_url('t/go');
                        $strrequired = get_string('notrequired', 'questionnaire');
                    }
                    $strrequired .= ' '.get_string('clicktoswitch', 'questionnaire');
                    $reqextra = array('value' => $question->id,
                                    'alt' => $strrequired,
                                    'title' => $strrequired);
                    $manageqgroup[] =& $mform->createElement('image', 'requiredbutton['.$question->id.']', $reqsrc, $reqextra);
                }
                $manageqgroup[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '');

            } else {
                $manageqgroup[] =& $mform->createElement('static', 'qnum', '', '<div class="qnums">'.$strposition.' '.$pos.'</div>');
                $moveqgroup[] =& $mform->createElement('static', 'qnum', '', '');

                $display = true;
                if ($questionnairehasdependencies) {
                    // Prevent moving child to higher position than its parent.
                    if (isset($parentpositions[$this->moveq])) {
                        $maxup = $parentpositions[$this->moveq];
                        if ($pos <= $maxup) {
                            $display = false;
                        }
                    }
                    // Prevent moving parent to lower position than its (first) child.
                    if (isset($childpositions[$this->moveq])) {
                        $maxdown = $childpositions[$this->moveq];
                        if ($pos >= $maxdown) {
                            $display = false;
                        }
                    }
                }

                $typeid = $DB->get_field('questionnaire_question', 'type_id', array('id' => $this->moveq));

                if ($display) {
                    // Do not move a page break to first position.
                    if ($typeid == QUESPAGEBREAK && $pos == 1) {
                        $manageqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                    } else {
                        $mextra = array('value' => $question->id,
                                        'alt' => $strmove,
                                        'title' => $strmovehere);
                        $msrc = $OUTPUT->pix_url('movehere');
                        $moveqgroup[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '');
                        $moveqgroup[] =& $mform->createElement('image', 'moveherebutton['.$pos.']', $msrc, $mextra);
                        $moveqgroup[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '');
                    }
                } else {
                    $manageqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                    $moveqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                }
            }
            if ($question->name) {
                $qname = '('.$question->name.')';
            } else {
                $qname = '';
            }
            $manageqgroup[] =& $mform->createElement('static', 'qtype_'.$question->id, '', $qtype);
            $manageqgroup[] =& $mform->createElement('static', 'qname_'.$question->id, '', $qname);

            if ($this->moveq) {
                $mform->addGroup($moveqgroup, 'moveqgroup', '', '', false);
                if ($this->moveq == $question->id && $display) {
                    $mform->addElement('html', '<div class="moving" title="'.$strmove.'">'); // Begin div qn-container.
                } else {
                    $mform->addElement('html', '<div class="qn-container">'); // Begin div qn-container.
                }
            }

            $mform->addGroup($manageqgroup, 'manageqgroup', '', '&nbsp;', false);

            if ($dependency) {
                $mform->addElement('static', 'qdepend_'.$question->id, '', '<div class="qdepend">'.$dependency.'</div>');
            }
            if ($tid != QUESPAGEBREAK) {
                if ($tid == QUESSECTIONTEXT) {
                    $qnumtxt = '';
                } else {
                    $qnumtxt = $qnum;
                }
                $mform->addElement('static', 'qcontent_'.$question->id, '',
                                '<div class="qn-info"><h2 class="qn-number">'.$qnumtxt.'</div></h2>'.
                                '<div class="qn-question">'.$content.'</div>');
            }

            $pos++;
            $mform->addElement('html', '</div>'); // End div qn-container.
        }

        // If we are moving a question, display one more line for the end.
        if ($this->moveq) {
            $mform->addElement('hidden', 'moveq', $this->moveq);
        }

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'main');
        $mform->setType('action', PARAM_RAW);
        $mform->setType('moveq', PARAM_RAW);

        // Buttons.

        $mform->addElement('html', '</div>');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

}

class questionnaire_edit_question_form extends moodleform {

    public function definition() {
        global $CFG, $COURSE, $questionnaire, $question, $questionnairerealms, $SESSION;
        global $DB;

        // The 'sticky' required response value for further new questions.
        if (isset($SESSION->questionnaire->required) && !isset($question->qid)) {
            $question->required = $SESSION->questionnaire->required;
        }
        if (!isset($question->type_id)) {
            print_error('undefinedquestiontype', 'questionnaire');
        }

        // Initialize question type defaults.
        switch ($question->type_id) {
            case QUESTEXT:
                $deflength = 20;
                $defprecise = 25;
                $lhelpname = 'fieldlength';
                $phelpname = 'maxtextlength';
                break;
            case QUESESSAY:
                $deflength = '';
                $defprecise = '';
                $lhelpname = 'textareacolumns';
                $phelpname = 'textarearows';
                break;
            case QUESCHECK:
                $deflength = 0;
                $defprecise = 0;
                $lhelpname = 'minforcedresponses';
                $phelpname = 'maxforcedresponses';
                $olabelname = 'possibleanswers';
                $ohelpname = 'checkboxes';
                break;
            case QUESRADIO:
                $deflength = 0;
                $defprecise = 0;
                $lhelpname = 'alignment';
                $olabelname = 'possibleanswers';
                $ohelpname = 'radiobuttons';
                break;
            case QUESRATE:
                $deflength = 5;
                $defprecise = 0;
                $lhelpname = 'numberscaleitems';
                $phelpname = 'kindofratescale';
                $olabelname = 'possibleanswers';
                $ohelpname = 'ratescale';
                break;
            case QUESNUMERIC:
                $deflength = 10;
                $defprecise = 0;
                $lhelpname = 'maxdigitsallowed';
                $phelpname = 'numberofdecimaldigits';
                break;
            case QUESDROP:
                $deflength = 0;
                $defprecise = 0;
                $olabelname = 'possibleanswers';
                $ohelpname = 'dropdown';
                break;
            default:
                $deflength = 0;
                $defprecise = 0;
        }

        $defdependquestion = 0;
        $defdependchoice = 0;
        $dlabelname = 'dependquestion';

        $mform    =& $this->_form;

        // Display different messages for new question creation and existing question modification.
        if (isset($question->qid)) {
            $streditquestion = get_string('editquestion', 'questionnaire', questionnaire_get_type($question->type_id));
        } else {
            $streditquestion = get_string('addnewquestion', 'questionnaire', questionnaire_get_type($question->type_id));
        }
		switch ($question->type_id) {
		    case 1:
		        $qtype='yesno';
		        break;
		    case 2:
		        $qtype='textbox';
                break;
	        case 3:
		        $qtype='essaybox';
                break;
		    case 4:
		        $qtype='radiobuttons';
		        break;
            case 5:
		        $qtype='checkboxes';
                break;
		    case 6:
		        $qtype='dropdown';
                break;
		    case 8:
		        $qtype='ratescale';
                break;
		    case 9:
		        $qtype='date';
		        break;
		    case 10:
		        $qtype='numeric';
		        break;
		    case 100:
		        $qtype='sectiontext';
		        break;
            case 99:
		        $qtype='sectionbreak';
		}

        $mform->addElement('header', 'questionhdredit', $streditquestion);
        $mform->addHelpButton('questionhdredit', $qtype, 'questionnaire');

        // Name and required fields.
        if ($question->type_id != QUESSECTIONTEXT && $question->type_id != '') {
            $stryes = get_string('yes');
            $strno  = get_string('no');

            $mform->addElement('text', 'name', get_string('optionalname', 'questionnaire'), array('size'=>'30', 'maxlength'=>'30'));
            $mform->setType('name', PARAM_TEXT);
            $mform->addHelpButton('name', 'optionalname', 'questionnaire');

            $reqgroup = array();
            $reqgroup[] =& $mform->createElement('radio', 'required', '', $stryes, 'y');
            $reqgroup[] =& $mform->createElement('radio', 'required', '', $strno, 'n');
            $mform->addGroup($reqgroup, 'reqgroup', get_string('required', 'questionnaire'), ' ', false);
            $mform->addHelpButton('reqgroup', 'required', 'questionnaire');
        }

        // Length field.
        if ($question->type_id == QUESYESNO || $question->type_id == QUESDROP || $question->type_id == QUESDATE ||
            $question->type_id == QUESSECTIONTEXT) {
            $mform->addElement('hidden', 'length', $deflength);
            $mform->setType('length', PARAM_INT);
        } else if ($question->type_id == QUESRADIO) {
            $lengroup = array();
            $lengroup[] =& $mform->createElement('radio', 'length', '', get_string('vertical', 'questionnaire'), '0');
            $lengroup[] =& $mform->createElement('radio', 'length', '', get_string('horizontal', 'questionnaire'), '1');
            $mform->addGroup($lengroup, 'lengroup', get_string($lhelpname, 'questionnaire'), ' ', false);
            $mform->addHelpButton('lengroup', $lhelpname, 'questionnaire');
        } else { // QUESTEXT or QUESESSAY or QUESRATE.
            $question->length = isset($question->length) ? $question->length : $deflength;
            $mform->addElement('text', 'length', get_string($lhelpname, 'questionnaire'), array('size'=>'1'));
            $mform->setType('length', PARAM_TEXT);
            $mform->addHelpButton('length', $lhelpname, 'questionnaire');
        }

        // Precision field.
        if ($question->type_id == QUESYESNO || $question->type_id == QUESDROP || $question->type_id == QUESDATE ||
            $question->type_id == QUESSECTIONTEXT || $question->type_id == QUESRADIO) {
            $mform->addElement('hidden', 'precise', $defprecise);
        } else if ($question->type_id == QUESRATE) {
            $precoptions = array("0" => get_string('normal', 'questionnaire'),
                                 "1" => get_string('notapplicablecolumn', 'questionnaire'),
                                 "2" => get_string('noduplicates', 'questionnaire'),
                                 "3" => get_string('osgood', 'questionnaire'));
            $mform->addElement('select', 'precise', get_string($phelpname, 'questionnaire'), $precoptions);
            $mform->addHelpButton('precise', $phelpname, 'questionnaire');
        } else {
            $question->precise = isset($question->precise) ? $question->precise : $defprecise;
            $mform->addElement('text', 'precise', get_string($phelpname, 'questionnaire'), array('size'=>'1'));
        }
        $mform->setType('precise', PARAM_INT);

        // Dependence fields.

        if ($questionnaire->navigate) {
            $position = isset($question->position) ? $question->position : count($questionnaire->questions) + 1;
            $dependencies = questionnaire_get_dependencies($questionnaire->questions, $position);
            if (count($dependencies) > 1) {
                $mform->addElement('hidden', 'cannotchangeparentifchildren');
                $mform->setType('cannotchangeparentifchildren', PARAM_INT);
                $mform->setDefault('cannotchangeparentifchildren', false);
                if (isset($question->qid)) {
                    $haschildren = questionnaire_get_descendants ($questionnaire->questions, $question->qid);
                    if (count($haschildren) !== 0) {
                        $mform->setDefault('cannotchangeparentifchildren', true);
                    }
                }
                $question->dependquestion = isset($question->dependquestion) ? $question->dependquestion.','.
                                $question->dependchoice : '0,0';
                $group = array($mform->createElement('selectgroups', 'dependquestion', '', $dependencies) );
                $mform->addGroup($group, 'selectdependency', get_string('dependquestion', 'questionnaire'), '', false);
                $mform->addHelpButton('selectdependency', 'dependquestion', 'questionnaire');
                $mform->disabledIf('selectdependency', 'cannotchangeparentifchildren', 'eq', 1);
            }
        }

        // Content field.
        $modcontext    = $this->_customdata['modcontext'];
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$modcontext);
        $mform->addElement('editor', 'content', get_string('text', 'questionnaire'), null, $editoroptions);
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');

        // Options section:
        // has answer options ... so show that part of the form.
        if ($DB->get_field('questionnaire_question_type', 'has_choices', array('typeid' => $question->type_id)) == 'y' ) {
            if (!empty($question->choices)) {
                $numchoices = count($question->choices);
            } else {
                $numchoices = 0;
            }

            if (!empty($question->choices)) {
                foreach ($question->choices as $choiceid => $choice) {
                    if (!empty($question->allchoices)) {
                        $question->allchoices .= "\n";
                    }
                    $question->allchoices .= $choice->content;
                }
            } else {
                $question->allchoices = '';
            }

            $mform->addElement('html', '<div class="qoptcontainer">');

            $options = array('wrap' => 'virtual', 'class' => 'qopts');
            $mform->addElement('textarea', 'allchoices', get_string('possibleanswers', 'questionnaire'), $options);
            $mform->setType('allchoices', PARAM_RAW);
            $mform->addRule('allchoices', null, 'required', null, 'client');
            $mform->addHelpButton('allchoices', $ohelpname, 'questionnaire');

            $mform->addElement('html', '</div>');

            $mform->addElement('hidden', 'num_choices', $numchoices);
            $mform->setType('num_choices', PARAM_INT);
        }

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'qid', 0);
        $mform->setType('qid', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'type_id', $question->type_id);
        $mform->setType('type_id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'question');
        $mform->setType('action', PARAM_RAW);

        // Buttons.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if (isset($question->qid)) {
            $buttonarray[] = &$mform->createElement('submit', 'makecopy', get_string('saveasnew', 'questionnaire'));
        }
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
