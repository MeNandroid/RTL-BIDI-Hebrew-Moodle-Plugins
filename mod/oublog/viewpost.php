<?php
/**
 * This page prints a particular post from an oublog, including any comments.
 *
 * @author Matt Clarkson <mattc@catalyst.net.nz>
 * @author Sam Marshall <s.marshall@open.ac.uk>
 * @package oublog
 */
// This code tells OU authentication system to let the public access this page
// (subject to Moodle restrictions below and with the accompanying .sams file).
global $DISABLESAMS;
$DISABLESAMS = 'opt';

    require_once("../../config.php");
    require_once("locallib.php");

    if(class_exists('ouflags')) {
        $DASHBOARD_COUNTER=DASHBOARD_BLOG_VIEW;
        require_once('../../local/mobile/ou_lib.php');
        
        global $OUMOBILESUPPORT;
        $OUMOBILESUPPORT = true;
        ou_set_is_mobile(ou_get_is_mobile_from_cookies());
    
        $blogdets = optional_param('blogdets', null, PARAM_TEXT);
    }

    $postid = required_param('post', PARAM_INT);       // Post id

    // This query based on the post id is so that we can get the blog etc to 
    // check permissions before calling oublog_get_post
    if (!$oublog = oublog_get_blog_from_postid($postid)) {
        error("Post ID is incorrect");
    }

    if (!$cm = get_coursemodule_from_instance('oublog', $oublog->id)) {
        error("Course module ID was incorrect");
    }

    if (!$course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    oublog_check_view_permissions($oublog, $context, $cm);

/// Check security
    $canmanageposts    = has_capability('mod/oublog:manageposts', $context);
    $canmanagecomments = has_capability('mod/oublog:managecomments', $context);
    $canaudit          = has_capability('mod/oublog:audit', $context);

    if (!$post = oublog_get_post($postid, $canaudit)) {
        error("Post ID was incorrect");
    }

    if (!$oubloginstance = get_record('oublog_instances', 'id', $post->oubloginstancesid)) {
        error("Blog instance not found");
    }

    if(!oublog_can_view_post($post,$USER,$context,$oublog->global)) {
        print_error('accessdenied','oublog');
    }

/// Get strings
    $stroublogs     = get_string('modulenameplural', 'oublog');
    $stroublog      = get_string('modulename', 'oublog');
    $straddpost     = get_string('newpost', 'oublog');
    $strdelete      = get_string('delete', 'oublog');
    $strtags        = get_string('tags', 'oublog');
    $strcomments    = get_string('comments', 'oublog');
    $strlinks       = get_string('links', 'oublog');
    $strfeeds       = get_string('feeds', 'oublog');
    
/// Set-up groups
    $groupmode = oublog_get_activity_groupmode($cm, $course);
    $currentgroup = oublog_get_activity_group($cm, true);

/// Check permissions for group (of post)
    if($groupmode==VISIBLEGROUPS && !groups_is_member($post->groupid) &&
        !has_capability('moodle/site:accessallgroups',$context)) {
        $canpost=false;
        $canmanageposts=false;
        $canaudit=false;
    } 

    /// Generate extra navigation
    $extranav = oublog_get_post_extranav($post, false);

/// Print the header
    if (class_exists('ouflags') && ou_get_is_mobile()){
        ou_mobile_configure_theme();
    }
    
    if ($oublog->global) {
        $blogtype = 'personal';
        $returnurl = 'view.php?user='.$oubloginstance->userid;
        $blogname = format_string($oubloginstance->name);

        if (!$oubloguser = get_record('user', 'id', $oubloginstance->userid)) {
            error("User not found");
        }

        $navlinks = array();
        $navlinks[] = array('name' => fullname($oubloguser), 'link' => "../../user/view.php?id=$oubloguser->id", 'type' => 'misc');
        $navlinks[] = array('name' => $blogname, 'link' => $returnurl, 'type' => 'activityinstance');
        if ($extranav) {
            $navlinks[] = $extranav;
        }

        $navigation = build_navigation($navlinks);
        print_header_simple(format_string($oublog->name), "", $navigation, "", oublog_get_meta_tags($oublog, $oubloginstance, $currentgroup, $cm), true,
                    update_module_button($cm->id, $course->id, $stroublog), navmenu($course, $cm));
    } else {
        $blogtype = 'course';
        $returnurl = 'view.php?id='.$cm->id;
        $blogname = $oublog->name;

        $navlinks = array();
        if ($extranav) {
            $navlinks[] = $extranav;
        }
        $navigation = build_navigation($navlinks, $cm);

        print_header_simple(format_string($oublog->name), "", $navigation, "", oublog_get_meta_tags($oublog, $oubloginstance, $currentgroup, $cm), true,
                      update_module_button($cm->id, $course->id, $blogname), navmenu($course, $cm));
    }

/// Print the main part of the page
    echo '<div class="oublog-topofpage"></div>';

    if (class_exists('ouflags') && ou_get_is_mobile() && $blogdets == 'show'){
        print '<div id="middle-column">';

        ou_print_mobile_navigation(null,$blogdets,$postid);
    }
    else {
        // The right column, BEFORE the middle-column.
        print '<div id="right-column">';
    }
    
// Title & Print summary
    // Name, summary, related links
    oublog_print_summary_block($oublog, $oubloginstance, $canmanageposts);
        
    // Tag Cloud
    if ($tags = oublog_get_tag_cloud($returnurl, $oublog, $currentgroup, $cm, $oubloginstance->id)) {
        print_side_block($strtags, $tags, NULL, NULL, NULL, array('id' => 'oublog-tags'));
    }


/// Links
    if ($links = oublog_get_links($oublog, $oubloginstance, $context)) {
        print_side_block($strlinks, $links, NULL, NULL, NULL, array('id' => 'oublog-links'));
    }


    $individual = optional_param('individual', false, PARAM_INT);
    if ($feeds = oublog_get_feedblock($oublog, $oubloginstance, $currentgroup, false, $cm, $individual)) {
        $feedicon = ' <img src="'.$CFG->pixpath.'/i/rss.gif" alt="'.get_string('blogfeed', 'oublog').'"  class="feedicon" />';
        print_side_block($strfeeds . $feedicon, $feeds, NULL, NULL, NULL, array('id' => 'oublog-feeds'), $strfeeds);
    }

    if (class_exists('ouflags') && ou_get_is_mobile() && $blogdets == 'show'){
        ou_print_mobile_navigation(null,$blogdets,$postid);
    }
    
    print '</div>';

    if (class_exists('ouflags') && ou_get_is_mobile() && $blogdets == 'show'){
        print_footer($course);
        exit;
    }
    
// Print blog posts
    if (class_exists('ouflags') && ou_get_is_mobile()){
        echo '<div id="middle-column">';
        
        ou_print_mobile_navigation(null,$blogdets,$postid);
    }
    else {
        echo '<div id="middle-column" class="has-right-column">';
    }
    
    oublog_print_post($cm, $oublog, $post, $returnurl, $blogtype, $canmanageposts, $canaudit, false);

    if (!empty($post->comments)) {
        echo "<h2>$strcomments</h2>";

        foreach($post->comments as $comment) {
            $extraclasses = $comment->deletedby ? 'oublog-deleted':'';
            if($CFG->oublog_showuserpics) {
                $extraclasses.=' oublog-hasuserpic';
            }
            ?>
            <div class="oublog-comment <?php print $extraclasses; ?>"><?php
            if ($comment->deletedby) {
                $deluser = new stdClass();
                $deluser->firstname = $comment->delfirstname;
                $deluser->lastname  = $comment->dellastname;

                $a = new stdClass();
                $a->fullname = '<a href="../../user/view.php?id=' . $comment->deletedby . '">' . fullname($deluser) . '</a>';
                $a->timedeleted = oublog_date($comment->timedeleted);

                echo '<div class="oublog-comment-deletedby">'.get_string('deletedby', 'oublog', $a).'</div>';
            }
            if($CFG->oublog_showuserpics && $comment->userid) {
                print '<div class="oublog-userpic">';
                $commentuser = new object();
                $commentuser->id        = $comment->userid;
                $commentuser->firstname = $comment->firstname;
                $commentuser->lastname  = $comment->lastname;
                $commentuser->imagealt  = $comment->imagealt;
                $commentuser->picture   = $comment->picture;
                print_user_picture($commentuser,$oublog->course);
                print '</div>';
            }
            ?>
                <?php if(trim(format_string($comment->title))!=='') { ?><h2 class="oublog-comment-title"><?php print format_string($comment->title); ?></h2><?php } ?>
                <div class="oublog-comment-date">
                    <?php print oublog_date($comment->timeposted); ?>
                </div>
                <div class="oublog-posted-by"><?php
            if ($comment->userid) {
                print get_string('postedby', 'oublog',
                        '<a href="../../user/view.php?id=' . $comment->userid .
                        '&amp;course=' . $oublog->course . '">' .
                        fullname($comment) . '</a>');
            } else {
                print get_string(
                        $canaudit ? 'postedbymoderatedaudit' : 'postedbymoderated', 
                        'oublog', (object)array(
                        'commenter' => s($comment->authorname),
                        'approver' => '<a href="../../user/view.php?id=' .
                            $comment->userid . '&amp;course=' . $oublog->course .
                            '">' . fullname($post) . '</a>',
                        'approvedate' => oublog_date($comment->timeapproved),
                        'ip' => s($comment->authorip)));
            }
                ?></div>
                <div class="oublog-comment-content"><?php print format_text($comment->message, FORMAT_MOODLE); ?></div>
                <div class="oublog-post-links">              
            <?php
            if (!$comment->deletedby) {
                // You can delete your own comments, or comments on your own
                // personal blog, or if you can manage comments
                if (($comment->userid && $comment->userid == $USER->id) || 
                    ($oublog->global && $post->userid == $USER->id) ||
                    $canmanagecomments) {
                    echo '<a href="deletecomment.php?comment='.$comment->id.'">'.$strdelete.'</a>';
                }
            }
                ?>
                </div>
            </div>
        <?php
        }
    }

// Note: This section is indented correctly. For now, I left the rest of the
// code with the incorrect initial indent

// If it is your own post, then see if there are any moderated comments -
// for security reasons, you must also be allowed to comment on the post in 
// order to moderate it (because 'approving' a comment is basically equivalent
// to commenting)
if ($post->userid == $USER->id && 
        $post->allowcomments >= OUBLOG_COMMENTS_ALLOWPUBLIC && 
        oublog_can_comment($cm, $oublog, $post)) {
    $moderated = oublog_get_moderated_comments($oublog, $post, $canaudit);
    $display = array();
    foreach($moderated as $comment) {
        if ($comment->approval != OUBLOG_MODERATED_APPROVED) {
            $display[] = $comment;
        }
    }
    if (count($display)) {
        print '<h2 id="awaiting">' . get_string('moderated_awaiting', 'oublog') . '</h2>';
        print '<p>' . get_string('moderated_awaitingnote', 'oublog') . '</p>';
        print '<div class="oublog-awaiting">';
        foreach ($display as $comment) {
            if ($comment->approval == OUBLOG_MODERATED_APPROVED) {
                continue; // Don't bother showing approved comments as they
                          // appear above
            }

            $extraclasses = '';
            $extramessage = '';
            if ($comment->approval == OUBLOG_MODERATED_REJECTED) {
                $extraclasses='oublog-rejected';
                $extramessage = '<div class="oublog-rejected-info">' .
                        get_string('moderated_rejectedon', 'oublog',
                            oublog_date($comment->timeset)) . ' </div>';
            }
            if($CFG->oublog_showuserpics) {
                $extraclasses.=' oublog-hasuserpic';
            }

            // Start of comment
            print '<div class="oublog-comment ' . $extraclasses . '">' .
                    $extramessage;

            // Title
            if(trim(format_string($comment->title))!=='') { 
                print '<h2 class="oublog-comment-title">' .
                        format_string($comment->title) . '</h2>';
            }

            // Date and author
            print '<div class="oublog-comment-date">' . 
                    oublog_date($comment->timeposted) . ' </div>';
            print '<div class="oublog-posted-by">' . 
                    get_string('moderated_postername', 'oublog',
                        s($comment->authorname)) .
                    ($canaudit ? ' (' . s($comment->authorip) . ')' : '') . '</div>';

            print '<div class="oublog-comment-content">' .
                    format_text($comment->message, FORMAT_MOODLE) . '</div>';

            // You can only approve/reject it once; and we don't let admins
            // approve/reject (because there's no way of tracking who did it
            // and it displays the post owner as having approved it)...
            if ($comment->approval == OUBLOG_MODERATED_UNSET && 
                    $post->userid == $USER->id) {
                print '<form action="approve.php" method="post"><div>' .
                        '<input type="hidden" name="sesskey" value="' . sesskey() . '" />' .
                        '<input type="hidden" name="mcomment" value="' . $comment->id . '" />';
                if (count($moderated) == 1) {
                    // Track if this is the last comment so we can jump to the
                    // top of the page instead of the moderating bit
                    print '<input type="hidden" name="last" value="1" />';
                }
                print '<input type="submit" name="bapprove" value="' .
                    get_string('moderated_approve', 'oublog') . '" /> ';
                print '<input type="submit" name="breject" value="' .
                    get_string('moderated_reject', 'oublog') . '" />';
                print '</div></form>';
            }

            // End of comment
            print '</div>';
        }
        // End of comments awaiting approval
        print '</div>';
    }
}

// End correct indentation

    echo '</div>';
    
    if (class_exists('ouflags') && ou_get_is_mobile()){
        ou_print_mobile_navigation(null,$blogdets,$postid);
    }

/// Finish the page
    echo '<div class="clearfix"></div>';
    print_footer($course);
?>