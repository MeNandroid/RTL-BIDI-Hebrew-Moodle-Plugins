<?php  // $Id: view.php,v 1.9 2009/03/25 22:36:36 deeknow Exp $

    require_once("../../config.php");
    require_once("lib.php");
    require_once("locallib.php");
    require_once("dialogue_open_form.php");

    $params = new stdClass();
    $params->id = required_param('id',PARAM_INT);
    $params->action = optional_param('action','',PARAM_ALPHA);
    $params->pane = optional_param('pane',-1,PARAM_INT);
    $params->group = optional_param('group',-1,PARAM_INT);
    $params->order = optional_param('order','',PARAM_ALPHA);

    if (! $cm = get_record("course_modules", "id", $params->id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    if (! $dialogue = get_record("dialogue", "id", $cm->instance)) {
        error("Course module is incorrect");
    }

    require_login($course->id, false, $cm);

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $hascapopen = has_capability('mod/dialogue:open', $context);
    $hascapparticipate = has_capability('mod/dialogue:participate', $context);
    $hascapviewall = has_capability('mod/dialogue:viewall', $context);
    $hascapmanage = has_capability('mod/dialogue:manage', $context);

    add_to_log($course->id, "dialogue", "view", "view.php?id=$cm->id", $dialogue->id, $cm->id);

    if (! $cw = get_record("course_sections", "id", $cm->section)) {
        error("Course module is incorrect");
    }

    // set up some general variables
    $usehtmleditor = can_use_html_editor();

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");
    
    $navlinks = array(array('name' => $strdialogues, 'link' => "index.php?id=$course->id", 'type' => 'activity'),
                      array('name' => $dialogue->name, 'link' => '', 'type' => 'activityinstance')
                     );
    $navigation = build_navigation($navlinks);
    
    print_header_simple("$dialogue->name", "", $navigation,
                 "", "", true,
                  update_module_button($cm->id, $course->id, $strdialogue), navmenu($course, $cm));

    // ...and if necessary set default action

    if ($hascapparticipate) { // it's a teacher or student
        if (!$cm->visible and !$hascapmanage) {
            $params->action = 'notavailable';
        }
        if (empty($params->action)) {
            $params->action = 'view';
        }
    }
    else { // it's a guest, oh no!
        $params->action = 'notavailable';
    }



/*********************** dialogue not available (for gusets mainly)***********************/
    if ($params->action == 'notavailable') {
        print_heading(get_string("notavailable", "dialogue"));
    }


    /************ view **************************************************/
    elseif ($params->action == 'view') {

        print_simple_box(format_text($dialogue->intro), 'center', '70%', '', 5, 'generalbox', 'intro');
        echo "<br />";
        // get some stats

        $countclosed = dialogue_count_closed($dialogue, $USER, $hascapviewall);

        // set the default pane if not specified 
        if ($params->pane<0) {
           $params->pane = 1;
        }


        // set up tab table
        $names[0] = get_string("pane0", "dialogue");
        $names[1] = get_string("pane1", "dialogue");

/*        if ($countclosed == 1) {
            $names[3] = get_string("pane3one", "dialogue");
        } else {
            $names[3] = get_string("pane3", "dialogue", $countclosed);
        }
	$names[4] = get_string("pane4", "dialogue");
*/
	$names[5] = get_string("pane5", "dialogue");

        $tabs = array();
        if ($hascapopen) {
            $URL = "view.php?id=$cm->id&amp;pane=0";
            if ((groupmode($course, $cm) == SEPARATEGROUPS) || (groupmode($course, $cm) == VISIBLEGROUPS) ) {
                // pass the user's groupid if in groups mode to view.php
                // NOTE: this defaults to users first group, needs to be updated to handle grouping mode also
                if ($usergroups = groups_get_all_groups($course->id, $USER->id, 0)) {
                    $firstgroup = reset($usergroups);
                    $URL .= "&amp;group=" . $firstgroup->id;
                }
            }
            $tabs[0][] =  new tabobject (0, $URL, $names[0]);
        }
        $tabs[0][] =  new tabobject (1, "view.php?id=$cm->id&amp;pane=1", $names[1]);
        //$tabs[0][] =  new tabobject (3, "view.php?id=$cm->id&amp;pane=3", $names[3]);
        //$tabs[0][] =  new tabobject (4, "view.php?id=$cm->id&amp;pane=4", $names[4]);
	$tabs[0][] =  new tabobject (5, "view.php?id=$cm->id&amp;pane=5", $names[5]);

        print_tabs($tabs, $params->pane, $inactive=NULL, $activated=NULL, $return=false);
        
        echo "<br />\n";


        switch ($params->pane) {
            case 0: // Open dialogue
            
                if (!$hascapopen) {
                    print_heading(get_string("opendenied", "dialogue"));
                    print_continue("view.php?id=$cm->id");
                    break;
                }
                if (has_capability('mod/dialogue:manage', $context)) {
                    /// Check to see if groups are being used in this dialogue
                    /// and if so, set $currentgroup to reflect the current group
                    groups_print_activity_menu($cm, "view.php?id=$cm->id&amp;pane=0");
                    echo '<br />';
                    $currentgroup = groups_get_activity_group($cm, true);
                    $groupmode = groups_get_activity_groupmode($cm);
                }
                if ($names = dialogue_get_available_users($dialogue, $context, 0)) {
                    
                    $mform = new mod_dialogue_open_form('dialogues.php', array('names' => $names));
                    $mform->set_data(array('id' => $cm->id,
                                           'action' => 'openconversation'));
                    $mform->display();

                } else {
                    print_heading(get_string("noavailablepeople", "dialogue"));
                    print_continue("view.php?id=$cm->id");
                }
                break;
            case 1: // Current dialogues
            case 2:
                // print active conversations requiring a reply from the other person.
                //dialogue_list_conversations($dialogue);

		// display INCOMING active conversations from current course
		dialogue_list_conversations_inout($dialogue,'incoming');
                break;
            //case 3: // Closed dialogues
            //    dialogue_list_conversations_closed($dialogue, $USER);
	    //    break;
	    //case 4: // other courses / all courses
            //    dialogue_list_conversations_general();
            case 5:
		// display OUTGOING active conversations from current course
                dialogue_list_conversations_inout($dialogue,'outgoing');
                break;

        }
    }

    /*************** no man's land **************************************/
    else {
        error("Fatal Error: Unknown Action: ".$params->action."\n");
    }

    print_footer($course);

?>
