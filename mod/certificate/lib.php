<?PHP //$Id: version.php,v 3.1.0
require_once($CFG->dirroot.'/course/lib.php');

// STANDARD FUNCTIONS ////////////////////////////////////////////////////////

/************************************************************************
 * Given an object containing all the necessary data,                   * 
 * (defined by the form in mod.html) this function                      *
 * will create a new instance and return the id number                  *
 * of the new instance.                                                 *
 ************************************************************************/

function certificate_add_instance($certificate) {
    
    $certificate->timemodified = time();
    if ($returnid = insert_record("certificate", $certificate)) {

        $event = NULL;
        $event->name        = $certificate->name;
        $event->courseid    = $certificate->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'certificate';
        $event->instance    = $returnid;
        add_event($event);
    }

    return $returnid;
}

/************************************************************************
 * Updates an instance of a certificate                                 *
 ************************************************************************/
function certificate_update_instance($certificate) {
    
    $certificate->timemodified = time();
    $certificate->id = $certificate->instance;
    if ($returnid = update_record('certificate', $certificate)) {

                if ($event->id = get_field('event', 'id', 'modulename', 'certificate', 'instance', $certificate->id)) {
                    $event->name        = $certificate->name;

                    update_event($event);
                } else {
                    $event = NULL;
                    $event->name        = $certificate->name;
                    $event->courseid    = $certificate->course;
                    $event->groupid     = 0;
                    $event->userid      = 0;
                    $event->modulename  = 'certificate';
                    $event->instance    = $certificate->id;

                    add_event($event);
                }
            } else {
                delete_records('event', 'modulename', 'certificate', 'instance', $certificate->id);
            }

        return $returnid;
    }

/************************************************************************
 * Deletes an instance of a certificate                                 *
 ************************************************************************/
function certificate_delete_instance($id) {
if (! $certificate = get_record('certificate', 'id', $id)) {
return false;
}
$result = true;

delete_records('certificate_issues', 'certificateid', $certificate->id);

if (! delete_records('certificate', 'id', $certificate->id)) {
$result = false;
}

if ($events = get_records_select('event', "modulename = 'certificate' and instance = '$certificate->id'")) {
foreach($events as $event) {
delete_event($event->id);
}
}

return $result;
}

/************************************************************************
 * Deletes any files associated with this field                          *
 ************************************************************************/
    function delete_certificate_files($certificate='') {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');

        $dir = $CFG->dataroot.'/'.$certificate->course.'/'.$CFG->moddata.'/certificate/'.$certificate->id.'/'.$user->id;
        if ($certificateid) {
            $dir .= '/'.$certificateid;
        }

        return fulldelete($dir);
    }

/************************************************************************
 * Returns information about received certificate.                      * 
 * Used for user activity reports.                                      *
 ************************************************************************/
 function certificate_user_outline($course, $user, $mod, $certificate) {
    if ($issue = get_record('certificate_issues', 'certificateid', $certificate->id, 'userid', $user->id)) {
        $result->info = get_string('issued', 'certificate');
        $result->time = $issue->timecreated;
     } 
        if (!$issue = get_record('certificate_issues', 'certificateid', $certificate->id, 'userid', $user->id)) {
        $result->info = get_string('notissued', 'certificate');
     }
     return $result;
    }
    return NULL;


/************************************************************************
 * Returns information about received certificate.                      * 
 * Used for user activity reports.                                      *
 ************************************************************************/
function certificate_user_complete($course, $user, $mod, $certificate) {
    if ($issue = get_record('certificate_issues', 'certificateid', $certificate->id, 'userid', $user->id)) {

            print_simple_box_start();
            echo get_string('issued', 'certificate').": ";
            echo userdate($issue->timecreated);
    
            certificate_print_user_files($user->id);
    
            echo '<br />';
    
           } else {
           print_string('notissuedyet', 'certificate');
    }           
            print_simple_box_end();
}

/************************************************************************
 * Must return an array of user records (all data) who are participants *
 * for a given instance of certificate.                                 *
 ************************************************************************/
function certificate_get_participants($certificateid) {
    global $CFG;

    //Get students
    $participants = get_records_sql("SELECT DISTINCT u.id, u.id
                                 FROM {$CFG->prefix}user u,
                                      {$CFG->prefix}certificate_issues a
                                 WHERE a.certificateid = '$certificateid' and
                                       u.id = a.userid");
    return $participants;
}

// NON-STANDARD FUNCTIONS ////////////////////////////////////////////////

/************************************************************************
 * Prints the header in view.  Used to help prevent FPDF header errors. *
 ************************************************************************/    
function view_header($course, $certificate, $cm) {
    global $CFG;
      
    $strcertificates = get_string('modulenameplural', 'certificate');
    $strcertificate  = get_string('modulename', 'certificate');      
    $navigation = "<a href=\"index.php?id=$course->id\">$strcertificates</a> ->";
    print_header_simple(format_string($certificate->name), "",
                 "$navigation ".format_string($certificate->name), "", "", true, update_module_button($cm->id, $course->id, $strcertificate), navmenu($course, $cm));

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (has_capability('mod/certificate:manage', $context)) {
        $numusers = certificate_count_issues($certificate);
        echo "<div class=\"reportlink\"><a href=\"report.php?id=$cm->id\">".
              get_string('viewcertificateviews', 'certificate', $numusers)."</a></div>";
    }
} 

/************************************************************************
 * Creates a directory file name, suitable for make_upload_directory()  *
 * @param $userid int The user id                                       *
 * @return string path to file area                                     *
 ************************************************************************/
function certificate_file_area_name($userid) {
    global $course, $certificate, $CFG;
    return $course->id.'/moddata/certificate/'.$certificate->id.'/'.$userid;
}

/************************************************************************
 * Makes an upload directory                                            *
 * @param $userid int The user id                                       *
 ************************************************************************/    
function certificate_file_area($userid) {
    return make_upload_directory(certificate_file_area_name($userid));
}

/************************************************************************
 * Function to be run periodically according to the moodle cron         *
 * This needs to be done                                                *
 ************************************************************************/    
function certificate_cron () {
    global $CFG;

    return true;
}

/************************************************************************
 * Return list of certificate issues that have not been mailed out      *
 * for currently enrolled students                                      *
 * @return array                                                        *
 ************************************************************************/  
function certificate_get_unmailed_certificates($course, $user) {

    global $CFG, $course;
    return get_records_sql("SELECT s.*, a.course, a.name
                              FROM {$CFG->prefix}certificate_issues s, 
                                   {$CFG->prefix}certificate a,
                                   {$CFG->prefix}user_students us
                             WHERE s.mailed = 0 
                               AND s.certificate = a.id
                               AND s.userid = us.userid
                               AND a.course = us.course");
}

/************************************************************************
 * Alerts teachers by email of received certificates. First checks      *
 * whether the option to email teachers is set for this certificate.    *
 * Sends an email to ALL teachers in the course.                        *
 ************************************************************************/    
function certificate_email_teachers($certificate) {
    global $course, $USER, $CFG;

    if ($certificate->emailteachers == 0) {          // No need to do anything
            return;
        }
       $certrecord = certificate_get_issue($course, $USER, $certificate->id);
       $student = $certrecord->studentname;
       $cm = get_coursemodule_from_instance("certificate", $certificate->id, $course->id);
        if (groupmode($course, $cm) == SEPARATEGROUPS) {   // Separate groups are being used
            if (!$group = user_group($course->id, $user->id)) {             // Try to find a group
                $group->id = 0;                                             // Not in a group, never mind
            }
            $teachers = get_group_teachers($course->id, $group->id);        // Works even if not in group
        } else {
            $teachers = get_course_teachers($course->id);
        }

        if ($teachers) {

            $strcertificates = get_string('modulenameplural', 'certificate');
            $strcertificate  = get_string('modulename', 'certificate');
            $strawarded  = get_string('awarded', 'certificate');

            foreach ($teachers as $teacher) {
                unset($info);

                $info->student = $student;
                $info->course = format_string($course->fullname,true);     
                $info->certificate = format_string($certificate->name,true);
                $info->url = $CFG->wwwroot.'/mod/certificate/report.php?id='.$cm->id;
                $from = $student;
                $postsubject = $strawarded.': '.$info->student.' -> '.$certificate->name;
                $posttext = certificate_email_teachers_text($info);
                $posthtml = certificate_email_teachers_html($info);
                $posthtml = ($teacher->mailformat == 1) ? certificate_email_teachers_html($info) : '';

                @email_to_user($teacher, $from, $postsubject, $posttext, $posthtml);  // If it fails, oh well, too bad.
                set_field("certificate_issues","mailed","1","certificateid", $certificate->id, "userid", $USER->id);

        }
    }
}                

/************************************************************************
 * Creates the text content for emails to teachers -- needs to be finished with cron
 * @param $info object The info used by the 'emailteachermail' language string
 * @return string                                                       *
 ************************************************************************/    
function certificate_email_teachers_text($info) {
        $posttext = get_string('emailteachermail', 'certificate', $info)."\n";
        return $posttext;
}

/************************************************************************
 * Creates the html content for emails to teachers                      *
 * @param $info object The info used by the 'emailteachermailhtml' language string
 * @return string                                                       *
 ************************************************************************/    
function certificate_email_teachers_html($info) {
    global $CFG;

        $posthtml  = '<font face="sans-serif">';
        $posthtml .= '<p>'.get_string('emailteachermailhtml', 'certificate', $info).'</p>';
        $posthtml .= '</font>';
        return $posthtml;
}

/************************************************************************
 * Sends the student their issued certificate from moddata as an email  *
 * attachment.                                                          *
 ************************************************************************/   
function certificate_email_students($USER) {
   global $course, $certificate, $CFG; 
   $certrecord = certificate_get_issue($course, $USER);
   if ($certrecord->sent > 0)    {
    return;
     }
    $teacher = get_teacher($course->id);
    $strawarded = get_string('awarded', 'certificate');
	$stremailstudenttext = get_string('emailstudenttext', 'certificate');
    $info->username = fullname($user);
    $info->certificate = format_string($certificate->name,true);
    $info->course = format_string($course->fullname,true);         
    $from = fullname($teacher);
    $subject = $info->course.': '.$info->certificate;
    $message = get_string('emailstudenttext', 'certificate', $info)."\n";
   
    // Make the HTML version more XHTML happy  (&amp;)
    $messagehtml = text_to_html(get_string('emailstudenttext', 'certificate', $info));
    $user->mailformat = 0;  // Always send HTML version as well
    $attachment= $course->id.'/moddata/certificate/'.$certificate->id.'/'.$USER->id.'/certificate.pdf';
    $attachname= "certificate.pdf";
    set_field("certificate_issues","sent","1","certificateid", $certificate->id, "userid", $USER->id);
    return email_to_user($USER, $from, $subject, $message, $messagehtml, $attachment, $attachname);
}

/************************************************************************
 * Count certificates issued. Used for report link.                     *
 ************************************************************************/
function certificate_count_issues($certificate, $groupid=0) {
    global $CFG;

    if ($groupid) {     /// How many in a particular group?
        return count_records_sql("SELECT COUNT(DISTINCT g.userid, g.groupid)
                                     FROM {$CFG->prefix}certificate_issues a,
                                          {$CFG->prefix}groups_members g
                                    WHERE a.certificateid = $cerificate->id 
                                      AND a.timecreated > 0
                                      AND g.groupid = '$groupid' 
                                      AND a.userid = g.userid ");
    } else {
        $cm = get_coursemodule_from_instance('certificate', $certificate->id);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
	 if ($users = get_users_by_capability($context, 'mod/certificate:view')) {
            foreach ($users as $user) {
                $array[] = $user->id;
            }

            $userlists = '('.implode(',',$array).')';

            return count_records_sql("SELECT COUNT(*)
                                      FROM {$CFG->prefix}certificate_issues
                                     WHERE certificateid = '$certificate->id' 
                                       AND timecreated > 0
                                       AND userid IN $userlists ");
        } else {
            return 0; // no users enroled in course
        }
    }
}

/************************************************************************
 * Produces a list of links to the issued certificates.  Used for report.*
 * @param $userid int optional id of the user. If 0 then $USER->id is used.*
 * @param $return boolean optional defaults to false.                   *
 * @return string optional                                              *
 ************************************************************************/
function certificate_print_user_files($userid=0) {
    global $CFG, $USER;
    
        $filearea = certificate_file_area_name($userid);

        $output = '';
    
        if ($basedir = certificate_file_area($userid)) {
            if ($files = get_directory_list($basedir)) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $file) {
                    
                    $icon = mimeinfo('icon', $file);
                    
                    if ($CFG->slasharguments) {
                        $ffurl = "$CFG->wwwroot/file.php/$filearea/$file";
                    } else {
                        $ffurl = "$CFG->wwwroot/file.php?file=/$filearea/$file";
                    }
                
                    $output .= '<img align="middle" src="'.$CFG->pixpath.'/f/'.$icon.'" height="16" width="16" alt="'.$icon.'" />'.
                            '<a href="'.$ffurl.'" target="_blank">'.$file.'</a><br />';
                }
            }
        }

        $output = '<div class="files">'.$output.'</div>';

        return $output;
}

/************************************************************************
 * Returns user information about issued certificates.  Used for index and review.*
 ************************************************************************/
function certificate_get_issue($course, $user) {
    global $certificate;
    if (record_exists("certificate_issues", "certificateid", $certificate->id, "userid", $user->id)) {
    $issue = get_record("certificate_issues", "certificateid", $certificate->id, "userid", $user->id);
 }
    return get_record("certificate_issues", "certificateid", $certificate->id, "userid", $user->id);
}

/************************************************************************
 * Returns a list of issued certificates - sorted for report.           *
 ************************************************************************/
function certificate_get_issues($certificate, $user, $sort="u.studentname ASC") {
    global $CFG;

    return get_records_sql("SELECT u.*,u.picture, s.code, s.timecreated, s.certdate, s.studentname
                              FROM {$CFG->prefix}certificate_issues s, 
                                   {$CFG->prefix}user u
                             WHERE s.certificateid = '$certificate' 
                               AND s.userid = u.id 
                             ORDER BY $sort");
}

// FUNCTIONS NEEDED TO PRINT A CERTIFICATE///////////////////////////////////////////////////////

/************************************************************************
 * Returns an array of installed certificate types indexed and sorted   * 
 * by name.                                                             *
 * @return array The index is the name of the certificate type, the     *
 * value its name from the language string                              *
 ************************************************************************/
function certificate_types() {
    $types = array();
    $names = get_list_of_plugins('mod/certificate/type');
    foreach ($names as $name) {
        $types[$name] = get_string('type'.$name, 'certificate');
    }
    asort($types);
    return $types;
}

/************************************************************************
 * Search through all the modules, pulling out grade data               *
 * David Cannon                                                         *
 ************************************************************************/
function certificate_get_mod_grades() {
    global $course, $CFG;

    /// Collect modules data
    get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);

    $printgrade = array();
    $sections = get_all_sections($course->id); // Sort everything the same as the course
    for ($i=0; $i<=$course->numsections; $i++) {
        // should always be true
        if (isset($sections[$i])) {
            $section = $sections[$i];
            if ($section->sequence) {
                switch ($course->format) {
                    case "topics":
                    $sectionlabel = get_string("topic");
                    break;
                    case "weeks":
                    $sectionlabel = get_string("week");
                    break;
                    default:
                    $sectionlabel = get_string("section");
                }

                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    $mod = $mods[$sectionmod];
                    // no labels
                    if ( $mod->modname != "label") {
                        $instance = get_record("$mod->modname", "id", "$mod->instance");
                        $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
                        if (file_exists($libfile)) {
                            require_once($libfile);
                            $gradefunction = $mod->modname."_grades";
                            // Modules with grade function (excluding forums)
                            //if (function_exists($gradefunction) and $mod->modname != "forum") {
			    if (function_exists($gradefunction)) {
                                // Modules with grading set
                                //if ($modgrades = $gradefunction($mod->instance) and !empty($modgrades->maxgrade)) {
                                    $printgrade[$mod->id] = $sectionlabel.' '.$section->section.' : '.$instance->name;
                                //}
                            }
                            else { //Modules without a grade set but with a grade function
                            }

                        } // libfile
                    } // no labels
                } //close foreach
            } // if section
        } // isset section
    }
    if(isset($printgrade)) {
        $gradeoptions['1'] = get_string('coursegrade', 'certificate');
        foreach($printgrade as $key => $value) {
            $gradeoptions[$key] = $value;
//echo ' debug: $gradeoptions[$key]='.$gradeoptions[$key].'<br>';
        }
    } else { $gradeoptions['0'] = get_string('nogrades', 'certificate'); }
//echo 'debug: '.$printgrades;
    return ($gradeoptions);
}

/************************************************************************
 * Prepare mod grade for printing                                       *
 ************************************************************************/
function certificate_mod_grade($course, $moduleid) {
    global $USER, $CFG;
    $cm = get_record("course_modules", "id", $moduleid);
    $module = get_record("modules", "id", $cm->module);
    $libfile = $CFG->dirroot."/mod/".$module->name."/lib.php";
    if (file_exists($libfile)) {
        require_once($libfile);
        $gradefunction = $module->name."_grades";
        if ($modgrades = $gradefunction($cm->instance)) {
    $modinfo->name = utf8_decode(get_field($module->name, 'name', 'id', $cm->instance));
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (has_capability('mod/certificate:manage', $context)) {
        $modgrades->grades[$USER->id] = '';
}
    $modinfo->percentage = round(($modgrades->grades[$USER->id]*100/$modgrades->maxgrade),2);
    $modinfo->points = $modgrades->grades[$USER->id];

    return $modinfo;
        }
    }
}

/************************************************************************
 * Search through all the modules, pulling out grade data               *
 * and prepare to print the course grade.                               * 
   Patrick Jeitler                                                      *
 ************************************************************************/
function get_course_grade($id){
    global $course, $CFG, $USER;
    $course = get_record("course", "id", $id);
    $strgrades = get_string("grades");
    $strgrade = get_string("grade");
    $strmax = get_string("maximumshort");
    $stractivityreport = get_string("activityreport");

/// Get a list of all students

    $columnhtml = array();  // Accumulate column html in this array.
    $grades = array();      // Collect all grades in this array
    $maxgrades = array();   // Collect all max grades in this array
    $totalgrade = 0;
    $totalmaxgrade = .000001;

/// Collect modules data
$test=get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);

/// Search through all the modules, pulling out grade data
    $sections = get_all_sections($course->id); // Sort everything the same as the course
    for ($i=0; $i<=$course->numsections; $i++) {
        if (isset($sections[$i])) {   // should always be true
            $section = $sections[$i];
            if (!empty($section->sequence)) {
                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    $mod = $mods[$sectionmod];
                    if ($mod->visible) {
                        $instance = get_record("$mod->modname", "id", "$mod->instance");
                        $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
                        if (file_exists($libfile)) {
                            require_once($libfile);
                            $gradefunction = $mod->modname."_grades";
                            if (function_exists($gradefunction)) {   // Skip modules without grade function
                                if ($modgrades = $gradefunction($mod->instance)) {
                                    if (empty($modgrades->grades[$USER->id])) {
                                        $grades[]  = "";
                                    } else {
                                        $grades[]  = $modgrades->grades[$USER->id];
                                        $totalgrade += (float)$modgrades->grades[$USER->id];
                                    }
                                    if (empty($modgrades->maxgrade) || empty($modgrades)) {
                                        $maxgrades[] = "";
                                    } else {
                                        $maxgrades[]    = $modgrades->maxgrade;
                                        $totalmaxgrade += $modgrades->maxgrade;
                                    }
                                }
                            }
                            $gradefunction = $mod->modname."_get_user_grades";
                            if (function_exists($gradefunction)) {   // Skip modules without grade function
                                if ($modgrades = $gradefunction($mod->instance)) {
/*                                    if (empty($modgrades->grades[$USER->id])) {
                                        $grades[]  = "";
                                    } else {
                                        $grades[]  = $modgrades->grades[$USER->id];
                                        $totalgrade += (float)$modgrades->grades[$USER->id];
                                    }
                                    if (empty($modgrades->maxgrade) || empty($modgrades)) {
                                        $maxgrades[] = "";
                                    } else {
                                        $maxgrades[]    = $modgrades->maxgrade;
                                        $totalmaxgrade += $modgrades->maxgrade;
                                    }*/
                                }

			    }
                        }
                    }
                }
            }
        }
    }
	$coursegrade->percentage = round(($totalgrade*100/$totalmaxgrade),2);
    $coursegrade->points = $totalgrade;  
	return $coursegrade;
}

/************************************************************************
* Sends text to output given the following params.                      *
* @param int $x horizontal position in pixels                           *
* @param int $y vertical position in pixels                             *
* @param char $align L=left, C=center, R=right                          *
* @param string $font any available font in font directory              *
*        Courier  Helvetica Times Symbol  ZapfDingbats                  *
* @param char $style ''=normal, B=bold, I=italic, U=underline           *
* @param int $size font size in points                                  *
* @param string $text the text to print                                 *
* @return null                                                          *
 ************************************************************************/
function cert_printtext( $x, $y, $align, $font, $style, $size, $text) {
    global $pdf;
    $pdf->setFont("$font", "$style", $size);
    $pdf->SetXY( $x, $y);
    $pdf->Cell( 500, 0, "$text", 0, 1, "$align");
}

/************************************************************************
 * Colors for line border.                                              *
 ************************************************************************/
function set_color($color) {
    global $pdf;

    switch($color) {
        case get_string('borderblack', 'certificate'):
        $pdf->SetFillColor( 0, 0, 0);
        break;
        case get_string('borderbrown', 'certificate'):
        $pdf->SetFillColor(153, 102, 51 );
        break;
        case get_string('borderblue', 'certificate'):
        $pdf->SetFillColor( 0, 51, 204);
        break;
        case get_string('bordergreen', 'certificate'):
        $pdf->SetFillColor( 0, 180, 0);
        break;
    }
}

/************************************************************************
 * Creates rectangles for line border.                                  *
 ************************************************************************/
function draw_frame($color, $orientation) {
    global $pdf;

    switch ($orientation) {
        case 'L':
        // create box border
        set_color($color);
        $pdf->Rect( 26, 30, 790, 530, 'F'); //outer rectangle in selected color
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 32, 36, 778, 518, 'F'); //white rectangle
        set_color($color);                  //middle rectangles
        $pdf->Rect( 41, 45, 760, 500, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 42, 46, 758, 498, 'F');
        set_color($color);                  //inside rectangles
        $pdf->Rect( 52, 56, 738, 478, 'F');
        $pdf->SetFillColor( 255, 255, 255);  
        $pdf->Rect( 56, 60, 730, 470, 'F');
        $pdf->SetFillColor( 0, 0, 0);  
        break;
        case 'P':
        // create box border
        set_color($color);
        $pdf->Rect( 20, 20, 560, 800, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 26, 26, 548, 788, 'F');
        set_color($color);
        $pdf->Rect( 35, 35, 530, 770, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 36, 36, 528, 768, 'F');
        set_color($color);
        $pdf->Rect( 46, 46, 508, 748, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 50, 50, 500, 740, 'F');
        $pdf->SetFillColor( 0, 0, 0);
        break;
    }
}

/************************************************************************
 * Creates rectangles for line border for letter size paper.            *
 ************************************************************************/
function draw_frame_letter($color, $orientation) {
    global $pdf;
    
    switch ($orientation) {
        case 'L':
        // create box border
        set_color($color);
        $pdf->Rect( 26, 25, 741, 555, 'F'); //outer rectangle in selected color
        $pdf->SetFillColor( 255, 255, 255); 
        $pdf->Rect( 32, 31, 729, 542, 'F'); //white rectangle
        set_color($color);                  //middle rectangles  
        $pdf->Rect( 41, 40, 711, 525, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 42, 41, 709, 523, 'F');
        set_color($color);                  // inside rectangles
        $pdf->Rect( 52, 51, 689, 503, 'F');
        $pdf->SetFillColor( 255, 255, 255);  
        $pdf->Rect( 56, 55, 681, 495, 'F');
        $pdf->SetFillColor( 0, 0, 0);
        break;
        case 'P':
        set_color($color);
        $pdf->Rect( 25, 20, 561, 751, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 31, 26, 549, 739, 'F');
        set_color($color);
        $pdf->Rect( 40, 35, 531, 721, 'F');
        $pdf->SetFillColor( 255, 255, 255);
        $pdf->Rect( 41, 36, 529, 719, 'F');
        set_color($color);
        $pdf->Rect( 51, 46, 509, 699, 'F');
        $pdf->SetFillColor( 255, 255, 255);  
        $pdf->Rect( 55, 50, 501, 691, 'F');
        $pdf->SetFillColor( 0, 0, 0);
        break;
    }
}

/************************************************************************
 * Prints line borders or border images.                                *
 ************************************************************************/
function print_border($border, $color, $orientation) {
    global $CFG, $pdf;

    switch($border) {
        case '0':
        break;
        case '1':
        draw_frame($color, $orientation);
        break;
        default:
        switch ($orientation) {
            case 'L':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg")) {
            $pdf->Image( "$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg", 10, 10, 820, 580);
        }
        break;
            case 'P':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg")) {
            $pdf->Image( "$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg", 10, 10, 580, 820);
            }
            break;
        }
        break;
    }
}

/************************************************************************
 * Prints line borders or border images for letter size paper.          *
 ************************************************************************/
function print_border_letter($border, $color, $orientation) {
    global $CFG, $pdf;

    switch($border) {
        case '0':
        break;
        case '1':
        draw_frame_letter($color, $orientation);
        break;
        default:
        switch ($orientation) {
            case 'L':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg")) {
            $pdf->Image( "$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg", 12, 10, 771, 594);
        }
        break;
            case 'P':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg")) {
            $pdf->Image( "$CFG->dirroot/mod/certificate/pix/borders/$border-$color.jpg", 10, 10, 594, 771);
            }
            break;
        }
        break;
    }
}

/************************************************************************
 * Prints watermark images.                                             *
 ************************************************************************/
function print_watermark($wmark, $orientation) {
    global $CFG, $pdf;

    switch($wmark) {
        case '0':
        break;
        default:
        switch ($orientation) {
            case 'L':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", 100, 100, 600, 420);
            }
            break;
            case 'P':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", 78, 130, 450, 480);
            }
            break;
        }
        break;
    }
}

/************************************************************************
 * Prints watermark images for letter size paper.                       *
 ************************************************************************/
function print_watermark_letter($wmark, $orientation) {
    global $CFG, $pdf;

    switch($wmark) {
        case '0':
        break;
        default:
        switch ($orientation) {
            case 'L':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", 160, 110, 500, 400);
            }
            break;
            case 'P':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", 83, 130, 450, 480);
            }
            break;
        }
        break;
    }
}

/************************************************************************
 * Prints signature images or a line.                                   *
 ************************************************************************/
function print_signature($sig, $orientation, $x, $y, $w, $h) {
    global $CFG, $pdf;

    switch ($orientation) {
        case 'L':
        switch($sig) {
            case '0':
            break;
            default:
            if(file_exists("$CFG->dirroot/mod/certificate/pix/signatures/$sig")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/signatures/$sig", $x, $y, $w, $h);
            }
            break;
        }
        break;
        case 'P':
        switch($sig) {
            case '0':
            break;
            default:
            if(file_exists("$CFG->dirroot/mod/certificate/pix/signatures/$sig")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/signatures/$sig", $x, $y, $w, $h);
            }
            break;
        }
        break;
    }
}

/************************************************************************
 * Prints seal images.                                                  *
 ************************************************************************/
function print_seal($seal, $orientation, $x, $y, $w, $h) {
    global $CFG, $pdf;

    switch($seal) {
        case '0':
        break;
        default:
        switch ($orientation) {
            case 'L':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/seals/$seal")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/seals/$seal", $x, $y, $w, $h);
            }
            break;
            case 'P':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/seals/$seal")) {
                $pdf->Image( "$CFG->dirroot/mod/certificate/pix/seals/$seal", $x, $y, $w, $h);
            }
            break;
        }
        break;
    }
}

/************************************************************************
 * Generates the date to be printed on the certificate.                 *
 ************************************************************************/
function certificate_generate_date($certificate, $course) {
	$certdate = time();
if($certificate->printdate > 0)    {
    if ($certificate->printdate == 1)    {
    $certdate = time();
    }
    if ($certificate->printdate == 2) {
    $certdate = $course->enrolenddate;
    }
   return $certdate;
 }
}

/************************************************************************
 * Generates the student name to be printed on the certificate.         *
 ************************************************************************/
function certificate_generate_studentname($course, $user) {
    $studentname = fullname($user);
    return $studentname;
}

/************************************************************************
 * Generates a 10-digit code of random letters and numbers.             *
 ************************************************************************/
function certificate_generate_code() {
$randoncode = random_string(10);
while (record_exists("certificate_issues", "code", $randoncode)) {
$randoncode = random_string(10);
}
    return $randoncode;
}

/************************************************************************
 * Grade Condition                                .                     *
 ************************************************************************/
function certificate_grade_condition() {
	global $certificate, $course;
	if ($certificate->printgrade == 1) {
		$coursegrade = get_course_grade($course->id);
	} else if ($certificate->printgrade > 1) {
		$coursegrade = certificate_mod_grade($course, $certificate->printgrade);
	}
	
	if ($certificate->activecondition == 0) {
		$aproved = true; 
	} else if ($certificate->gradefmt == 1 AND $coursegrade->percentage >= $certificate->gradecondition) { 
		$aproved = true;
	} else if ($certificate->gradefmt == 2 AND $coursegrade->points >= $certificate->gradecondition) {
		$aproved = true;
	} else if ($certificate->gradefmt == 3 AND $coursegrade->percentage >= certificate_get_grade_from_letter($certificate->gradecondition)) {
		$aproved = true;
	} else {
		$aproved = false;
	} 
return $aproved;
}

/************************************************************************
 * Inserts user data when a certificate is created.                     *
 ************************************************************************/
function certificate_prepare_issue($course, $user) {
    global $certificate;
    if (record_exists("certificate_issues", "certificateid", $certificate->id, "userid", $user->id)) {
    	return get_record("certificate_issues", "certificateid", $certificate->id, "userid", $user->id);
	} else if (certificate_grade_condition()) {
    	$timecreated = time();
    	$certdate = certificate_generate_date($certificate, $course);
		if (!$certdate) {
		$certdate = $timecreated;
		}
    	$code = certificate_generate_code();
		if ($certificate->printgrade == 1) {
			$coursegrade = get_course_grade($course->id);
		} else if ($certificate->printgrade > 1) {
			$coursegrade = certificate_mod_grade($course, $certificate->printgrade);
		}
		if ($certificate->gradefmt == 1) {
			$gradeinput = $coursegrade->percentage.'%';
		} else if ($certificate->gradefmt == 2) {
			$gradeinput = $coursegrade->points.' %%P%%';
		} else if ($certificate->gradefmt == 3) {
			$gradeinput = certificate_get_gradeletter($coursegrade->percentage);
		} 
    	$studentname = str_replace('\'', '\\\'', certificate_generate_studentname($course, $user));
    	insert_record("certificate_issues", array("certificateid" => $certificate->id, "userid" => $user->id, "timecreated" => $timecreated, "studentname" => $studentname, "code" => $code, "classname" => str_replace('\'', '\\\'', $course->fullname), "certdate" => $certdate, "credits" => $certificate->credithours, "grade" => $gradeinput), false);
    	certificate_email_teachers($certificate);
	} 
}

/************************************************************************
 * Used for course participation report (in case certificate is added) .*
 ************************************************************************/
function certificate_get_view_actions() {
    return array('view','view all','view report');
}
function certificate_get_post_actions() {
    return array('received');
}

/************************************************************************
 * Date Format System                                                   .*
 ************************************************************************/
function certificate_date_format($type, $certrecord) {
	global $certificate;
	if ($certrecord) {
		$certdate = $certrecord->$type;
	}else {
		$certdate = certificate_generate_date($certificate, $course);
	}
	$datestrings = array("%DD", "%dd", "%ss","%MS", "%YY", "%yy", "%mm", "%MM");
	$datereplace = array(date('d', $certdate), date('j', $certdate), date('S', $certdate), userdate($certdate, "%B"), strftime('%Y', $certdate), strftime('%y', $certdate), date('n', $certdate), date('m', $certdate));
	$certificatedate = str_replace($datestrings, $datereplace, $certificate->datefmt);
	return $certificatedate;
} 

function certificate_date_report($user) {
	global $certificate;
	$certdate = usertime($user->timecreated);
	$datestrings = array("%DD", "%dd", "%ss","%MS", "%YY", "%yy", "%mm", "%MM");
	$datereplace = array(date('d', $certdate), date('j', $certdate), date('S', $certdate), userdate($certdate, "%B"), strftime('%Y', $certdate), strftime('%y', $certdate), date('n', $certdate), date('m', $certdate));
	$certificatedate = str_replace($datestrings, $datereplace, $certificate->datefmt).", ".strftime('%X', $certdate);
	return $certificatedate;
}

/************************************************************************
 * Functions to control grade letter                                   .*
 ************************************************************************/
function certificate_get_gradeletter($gradepercent) {
	global $certificate, $course, $CFG;
	$conceito  = get_records_sql("SELECT courseid, letter, grade_high, grade_low
                                FROM {$CFG->prefix}grade_letter
								WHERE courseid=".$course->id." AND grade_high>=".$gradepercent." AND grade_low<=".$gradepercent);
								
	return $conceito[$course->id]->letter;
}

function certificate_get_grade_from_letter($gradeletter) {
	global $certificate, $course, $CFG;
	$gradereturn = get_records_sql("SELECT courseid, letter, grade_high, grade_low
                                FROM {$CFG->prefix}grade_letter
								WHERE courseid=".$course->id." AND letter='".$gradeletter."'");
	return $gradereturn[$course->id]->grade_low;
}
?>