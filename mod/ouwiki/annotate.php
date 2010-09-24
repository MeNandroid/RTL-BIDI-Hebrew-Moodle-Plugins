<?php
/**
 * Annotate page. Allows user to add and edit wiki annotations.
 *
 * @copyright &copy; 2009 The Open University
 * @author b.j.waddington@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ouwiki
 *//** */

// Validate request
require_once(dirname(__FILE__).'/../../config.php');

// Get action types
$actionsave=array_key_exists('submitbutton',$_POST);
$actioncancel=array_key_exists('cancel',$_POST);

if($actionsave && class_exists('ouflags')) {
    $DASHBOARD_COUNTER=DASHBOARD_WIKI_EDIT;
}

if (!empty($_POST) && !confirm_sesskey()) {
    print_error('invalidrequest');
}

// We even display header etc. for preview posts (default is to hide for post)
require('basicpage.php');
require_once(dirname(__FILE__).'/../../lib/ajax/ajaxlib.php');

// Get the current page version, creating page if needed
$pageversion=ouwiki_get_current_page($subwiki,$pagename,OUWIKI_GETPAGE_ACCEPTNOVERSION);
$wikiformfields=ouwiki_display_wiki_parameters($pagename,$subwiki,$cm,OUWIKI_PARAMS_FORM);



// Check permission
require_capability('mod/ouwiki:annotate',$context);
if(!(ouwiki_get_commenting($subwiki->commenting) == OUWIKI_COMMENTS_ANNOTATION || ouwiki_get_commenting($subwiki->commenting) == OUWIKI_COMMENTS_BOTH)) {
    $redirect = 'view.php?'.ouwiki_display_wiki_parameters($pagename,$subwiki,$cm,OUWIKI_PARAMS_URL);
    print_error('You do not have permission to annotate this wiki page','error',$redirect);
}

// Need list of known sections on current version
//$knownsections=ouwiki_find_sections($pageversion->xhtml);

// For everything except cancel we need to obtain a lock.
if(!$actioncancel) {
    if (!$pageversion) {
        error (get_string('startpagedoesnotexist', 'ouwiki'));
    }
    // Get lock
    list($lockok,$lock)=ouwiki_obtain_lock($ouwiki,$pageversion->pageid);
}

// Handle save 
if($actionsave) {
    // If we don't have a lock...    
    if(!$lockok) {
        ouwiki_release_lock($pageversion->pageid);
        print_error('cannotlockpage','ouwiki','view.php?'.ouwiki_display_wiki_parameters($pagename,$subwiki,$cm,OUWIKI_PARAMS_URL));
    }

    global $USER;
    $userid = $USER->id;
    $neednewversion = false;

    // get the form data
    $new_annotations = array();
    $edited_annotations = array();
    foreach($_POST as $key=>$value){
        if (strpos($key, 'edit') === 0) {
            $edited_annotations[substr($key,4)] = optional_param($key, null, PARAM_TEXT);            
        } elseif (strpos($key, 'new') === 0) {
            $new_annotations[substr($key,3)] = optional_param($key, null, PARAM_TEXT);
        }
    }

    // intitiate the transaction wrapper
    $tw = new transaction_wrapper();

    // get the existing annotations to check for changes
    $stored = ouwiki_get_annotations($pageversion);
    $updated = $stored;
    $deleted_annotations = array();

    // do we need to delete orphaned annotations
    $deleteorphaned = optional_param('deleteorphaned', 0, PARAM_BOOL);
    if ($deleteorphaned) {
        foreach($stored as $annotation) {
            if ($annotation->orphaned) {
                try {
                    delete_records('ouwiki_annotations', 'id', $annotation->id);
                    $deleted_annotations[$annotation->id] = '';
                } catch (Exception $e) {
                    $tw->rollback();
                    ouwiki_dberror('annotations');
                }   
            }
        }    
    }

    foreach($edited_annotations as $key=>$value) {
        if ($value == '') {
            try {
                delete_records('ouwiki_annotations', 'id', $key);
                $deleted_annotations[$key] = '';
            } catch (Exception $e) {
                $tw->rollback();
                ouwiki_dberror('annotations');
            }   
        } elseif ($value != $stored[$key]->content) {
            $dataobject->id = $key;
            $dataobject->pageid = $pageversion->pageid;
            $dataobject->userid = $userid;
            $dataobject->timemodified = time();
            $dataobject->content = addslashes($value);
            try {
                update_record('ouwiki_annotations', $dataobject);
            } catch (Exception $e) {
                $tw->rollback();
                ouwiki_dberror('annotations');
            }
        }
    }

    $updated = array_diff_key($updated, $deleted_annotations);

    // we need to work backwords through this lot in order to maintain charactor position
    krsort($new_annotations, SORT_NUMERIC);
    $prevkey = '';
    $spanlength = 0;
    foreach($new_annotations as $key=>$value) {
        if ($value != '') {
            $dataobject->pageid = $pageversion->pageid;
            $dataobject->userid = $userid;
            $dataobject->timemodified = time();
            $dataobject->content = addslashes($value);
            try {
                $newannoid = insert_record('ouwiki_annotations', $dataobject);
                $updated[$newannoid] = '';
            } catch (Exception $e) {
                $tw->rollback();
                ouwiki_dberror('annotations');
            }

            // we're still going so insert the new annotation into the xhtml
            $replace = '<span id="annotation'.$newannoid.'"></span>';
            $position = $key;
            if ($key == $prevkey) {
                $position = $key + $spanlength;
            } else {
                $position = $key;
            }
            
            $pageversion->xhtml = substr_replace($pageversion->xhtml, $replace, $position, 0);
            $neednewversion = true;
            $spanlength = strlen($replace);
            $prevkey = $key;
        }
    }

    // if we have got this far then commit the transaction, remove any unwanted spans
    // and save a new wiki version if required
    $neednewversion = ouwiki_cleanup_annotation_tags($updated, $pageversion->xhtml) ? true:$neednewversion;

    $lockunlock = (optional_param('lockediting', 0, PARAM_BOOL))? true:false;
    ouwiki_lock_editing($pageversion->pageid, $lockunlock);

    if ($neednewversion) {
        ouwiki_save_new_version($course,$cm,$ouwiki,$subwiki,$pagename,$pageversion->xhtml);
    }

    $tw->commit();
}

// Redirect for save or cancel
if($actionsave || $actioncancel) {
    ouwiki_release_lock($pageversion->pageid);
    redirect('view.php?'.ouwiki_display_wiki_parameters($pagename,$subwiki,$cm,OUWIKI_PARAMS_URL),'',0);
}
// OK, not redirecting...

// Handle case where page is locked by someone else
if(!$lockok) {
    // Print header etc
    ouwiki_print_start($ouwiki,$cm,$course,$subwiki,$pagename,$context);

    $details=new StdClass;
    $lockholder=get_record('user','id',$lock->userid);
    $details->name=fullname($lockholder);
    $details->lockedat=ouwiki_nice_date($lock->lockedat);
    $details->seenat=ouwiki_nice_date($lock->seenat);
    $pagelockedtitle=get_string('pagelockedtitle','ouwiki');
    $pagelockedtimeout='';
    if($lock->seenat > time()) {
        // When the 'seen at' value is greater than current time, that means
        // their lock has been automatically confirmed in advance because they
        // don't have JavaScript support.
        $details->nojs=ouwiki_nice_date($lock->seenat+OUWIKI_LOCK_PERSISTENCE);
        $pagelockeddetails=get_string('pagelockeddetailsnojs','ouwiki',$details);
    } else {
        $pagelockeddetails=get_string('pagelockeddetails','ouwiki',$details);
        if($lock->expiresat) {
            $pagelockedtimeout=get_string('pagelockedtimeout','ouwiki',userdate($lock->expiresat));
        }
    }
    $canoverride=has_capability('mod/ouwiki:overridelock',$context);
    $pagelockedoverride=$canoverride ? '<p>'.get_string('pagelockedoverride','ouwiki').'</p>' : '';
    $overridelock=get_string('overridelock','ouwiki');
    $overridebutton=$canoverride ? "
<form class='ouwiki_overridelock' action='override.php' method='post'>
  <input type='hidden' name='redirpage' value='annotate'>
  $wikiformfields
  <input type='submit' value='$overridelock' />
</form>
" : '';    
    $cancel=get_string('cancel');
    $tryagain=get_string('tryagain','ouwiki');
    print "
<div id='ouwiki_lockinfo'>
  <h2>$pagelockedtitle</h2>
  <p>$pagelockeddetails $pagelockedtimeout</p>
  $pagelockedoverride
  <div class='ouwiki_lockinfobuttons'>
    <form action='edit.php' method='get'>
      $wikiformfields
      <input type='submit' value='$tryagain' />
    </form>
    <form action='view.php' method='get'>
      $wikiformfields
      <input type='submit' value='$cancel' />
    </form>
    $overridebutton
  </div>
</div>";
    print_footer($course);
    exit;
} 

// The page is now locked to us! Go ahead and print edit form

// get title of the page

$title = get_string('annotatingpage','ouwiki');
$wikiname=format_string(htmlspecialchars($ouwiki->name));
$name = '';
$name = $pagename; 
$title = $wikiname.' - '.$title.' : '.$name;

// Print header
ouwiki_print_start($ouwiki,$cm,$course,$subwiki,$pagename,$context,
    array(array('name'=>get_string('annotatingpage','ouwiki'),
        'type'=>'ouwiki')), false, false, '', $title);

if(ajaxenabled() || class_exists('ouflags')) {
    // YUI and basic script
    require_js(array('yui_yahoo','yui_connection','yui_dom','yui_dom-event','yui_animation','yui_container','yui_dragdrop','yui_element','yui_button'));

    // Print javascript
    print '<script type="text/javascript" src="annotate.js"></script><script type="text/javascript">
    strCloseComments="'.addslashes_js(get_string('closecomments','ouwiki')).'";
    strCloseCommentForm="'.addslashes_js(get_string('closecommentform','ouwiki')).'";
    ouwikiStrings = {save : "'.addslashes_js(get_string('add')).'",
        cancel : "'.addslashes_js(get_string('cancel')).'"};
    </script>';
}

// Tabs
ouwiki_print_tabs('annotate',$pagename,$subwiki,$cm,$context,$pageversion->versionid?true:false,$pageversion->locked);

ouwiki_print_editlock($lock, $ouwiki);


if($ouwiki->timeout && $js) {
    $countdowntext=get_string('countdowntext','ouwiki',$ouwiki->timeout/60);
    print "<script type='text/javascript'>
document.write('<p><div id=\"ouw_countdown\"></div>$countdowntext<span id=\"ouw_countdownurgent\"></span></p>');
</script>";
}

print get_string('advice_annotate','ouwiki');
$gewgaws = false;
$data = ouwiki_display_page($subwiki,$cm,$pageversion,$gewgaws,'annotate');
print($data[0]);
$annotations = $data[1];


require_once('annotate_form.php');
$customdata[0] = $annotations;
$customdata[1] = $pageversion;
$customdata[2] = $pagename;
$customdata[3] = optional_param('user', 0, PARAM_INT);
$annotateform = new mod_ouwiki_annotate_form('annotate.php?id='.$id, $customdata);

$annotateform->display();
$usedannotations = array();
foreach($annotations as $annotation) {
    if (!$annotation->orphaned) {
        $usedannotations[$annotation->id] = $annotation;
    }
}

echo '<div id="annotationcount" style="display:none;">'.count($usedannotations).'</div>';

echo '<div class="yui-skin-sam">';
echo '    <div id="ouwiki-annotation-dialog" class="yui-pe-content">';
echo '        <div class="hd">'.get_string('addannotation','ouwiki').'</div>';
echo '        <div class="bd">';
echo '            <form method="POST" action="post.php">';
echo '                <label for="annotationtext">'.get_string('addannotation','ouwiki').':</label><textarea name="annotationtext" "rows="4" cols="30"></textarea>';
echo '            </form>';
echo '        </div>';
echo '    </div>';
echo '</div>';

// Footer
ouwiki_print_footer($course,$cm,$subwiki,$pagename);
?>
